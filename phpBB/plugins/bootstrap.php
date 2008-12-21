<?php
/**
*
* @package plugins
* @version $Id$
* @copyright (c) 2008 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
Plugin documentation:

Rules:
	- Plugins must define one directory in /plugins/
	- This directory must contain a file bootstrap.php with the information about the plugins structure, installation and uninstallation routines
	- Plugins could consist of any additional files or directories

Sample Layout:
	- Plugin setup file: /plugins/plugin_myapp.php
	- Additional directory /plugins/myapp/
	- Additional files /plugins/myapp/functions.php, /plugins/myapp/core_system.php, /plugins/myapp/core_url.php
	- The plugin should add a new method to the core_system class to get the PATH environment variable
	- The plugin should add a new key to the systems page array for the application path
	- The plugin should change the output of URL's in phpBB and transform them to human readable URL's
	- The plugin should also inject the exit_handler to finish it's work
*/

/**
Modules documentation:

Rules:
	- Modules must define one directory in /modules/
	- This directory must contain two files:
		bootstrap.php with the information about the modules structure, installation and uninstallation routines.
		index.php with code to handle the different modes the module is executing
	- Modules may consist of any number of additional files or directories

	ucp/: The user control panel.
	mcp/: The moderator control panel.
	acp/: The administration control panel.
*/

class phpbb_plugin_setup
{
	var $application_name = false;
	var $plugin = false;

	public function set_application($name)
	{
		$this->application_name = $name;
		$this->plugin = array(
			'includes'		=> array(),
			'plugins'		=> array(),
		);
	}

	public function register_includes()
	{
		$arguments = func_get_args();
		$this->plugin['includes'] = $arguments;
	}

	public function register_plugins()
	{
		$arguments = func_get_args();
		$this->plugin['plugins'] = $arguments;
	}

	public function register_function($function, $hook, $mode = phpbb::FUNCTION_INJECT, $action = 'default')
	{
		phpbb::$hooks->register_hook($function, $hook, $mode, $action);
	}

	public function call_plugins()
	{
		// Setup application
		$class = 'phpbb_' . $this->application_name . '_info';
		$application = new $class();
		$application->setup_plugin($this);

		foreach ($this->plugin['includes'] as $file)
		{
			include PHPBB_ROOT_PATH . 'plugins/' . $this->application_name . '/' . $file . '.' . PHP_EXT;
		}

		foreach ($this->plugin['plugins'] as $class)
		{
			$object = new $class();

			if (!property_exists($object, 'phpbb_plugin'))
			{
				trigger_error('Class ' . get_class($object) . ' does not define $phpbb_plugin.', E_USER_ERROR);
			}

			// Is the plugin the mod author wants to influence pluggable?
			if (!is_subclass_of(phpbb::get_instance($object->phpbb_plugin), 'phpbb_plugin_support'))
			{
				trigger_error('The phpBB Class ' . get_class(phpbb::get_instance($object->phpbb_plugin)) . ' defined in ' . get_class($object) . ' is not pluggable.', E_USER_ERROR);
			}

			// Register plugin...
			$object->register_plugin(phpbb::get_instance($object->phpbb_plugin));
		}
	}
}

abstract class phpbb_plugin_support
{
	private $plugin_methods;
	private $plugin_attributes;

	public function register_method($name, $method, $object, $mode = phpbb::PLUGIN_ADD, $action = 'default')
	{
		// Method reachable by:
		// For plugin_add: plugin_methods[method] = object
		// For plugin_override: plugin_methods[name][mode][method] = object
		// For plugin_inject: plugin_methods[name][mode][action][method] = object

		// Set to PLUGIN_ADD if method does not exist
		if ($name === false || !method_exists($this, $name))
		{
			$mode = phpbb::PLUGIN_ADD;
		}

		// But if it exists and we try to add one, then print out an error
		if ($mode == phpbb::PLUGIN_ADD && (method_exists($this, $method) || isset($this->plugin_methods[$method])))
		{
			trigger_error('Method ' . $method. ' in class ' . get_class($object) . ' is not able to be added, because it conflicts with the existing method ' . $method . ' in ' . get_class($this) . '.', E_USER_ERROR);
		}

		// Check if the same method name is already used for $name for overriding the method.
		if ($mode == phpbb::PLUGIN_OVERRIDE && isset($this->plugin_methods[$name][$mode][$method]))
		{
			trigger_error('Method ' . $method . ' in class ' . get_class($object) . ' is not able to override . ' . $name . ' in ' . get_class($this) . ', because it is already overridden in ' . get_class($this->plugin_methods[$name][$mode][$method]) . '.', E_USER_ERROR);
		}

		// Check if another method is already defined...
		if ($mode == phpbb::PLUGIN_INJECT && isset($this->plugin_methods[$name][$mode][$action][$method]))
		{
			trigger_error('Method ' . $method . ' in class ' . get_class($object) . ' for ' . $name . ' is already defined in class ' . get_class($this->plugin_methods[$name][$mode][$action][$method]), E_USER_ERROR);
		}

		if (($function_signature = $this->valid_parameter($object, $method, $mode, $action)) !== true)
		{
			trigger_error('Method ' . $method . ' in class ' . get_class($object) . ' has invalid function signature. Please use: ' . $function_signature, E_USER_ERROR);
		}

		if ($mode == phpbb::PLUGIN_ADD)
		{
			$this->plugin_methods[$method] = $object;
		}
		else if ($mode == phpbb::PLUGIN_OVERRIDE)
		{
			$this->plugin_methods[$name][$mode][$method] = $object;
		}
		else
		{
			$this->plugin_methods[$name][$mode][$action][$method] = $object;
		}
	}

	public function register_attribute($name, $object)
	{
		if (property_exists($this, $name))
		{
			unset($this->$name);
		}

		if (isset($this->plugin_attributes[$name]))
		{
			trigger_error('Attribute ' . $name . ' in class ' . get_class($object) . ' already defined in class ' . get_class($this->plugin_attributes[$name]), E_USER_ERROR);
		}

		$this->plugin_attributes[$name] = $object;
	}

	protected function method_override($name)
	{
		return isset($this->plugin_methods[$name][phpbb::PLUGIN_OVERRIDE]);
	}

	protected function method_inject($name, $action = 'default')
	{
		return isset($this->plugin_methods[$name][phpbb::PLUGIN_INJECT][$action]);
	}

	public function call_override()
	{
		$arguments = func_get_args();
		$name = array_shift($arguments);

		list($method, $object) = each($this->plugin_methods[$name][phpbb::PLUGIN_OVERRIDE]);
		return call_user_func_array(array($object, $method), array_merge(array($this), $arguments));
	}

	/**
	* Call injected method.
	*
	* Arguments are layed out in the following way:
	*	action: The action:
	*		'default':	If $action is default, then the plugin is called in the beginning, original parameter passed by reference
	*		'return':	If $action is return, then the plugin is called at the end and the result will be returned. The plugin expects the $result as the first parameter, all other parameters passed by name
	*		If $action is not default and not return it could be a custom string. Please refer to the plugin documentation to determine possible combinations. Parameters are passed by reference.
	*
	* @param string $name Original method name this method is called from
	* @param array $arguments Arguments
	*/
	public function call_inject($name, $arguments)
	{
		$result = NULL;

		if (!is_array($arguments))
		{
			$action = $arguments;
			$arguments = array();
		}
		else
		{
			$action = array_shift($arguments);
		}

		// Return action... handle like override
		if ($action == 'return')
		{
			$result = array_shift($arguments);

			foreach ($this->plugin_methods[$name][phpbb::PLUGIN_INJECT][$action] as $method => $object)
			{
				$args = array_merge(array($this, $result), $arguments);
				$result = call_user_func_array(array($object, $method), $args);
			}

			return $result;
		}

		foreach ($this->plugin_methods[$name][phpbb::PLUGIN_INJECT][$action] as $method => $object)
		{
			call_user_func_array(array($object, $method), array_merge(array($this), $arguments));
		}
	}

	// Getter/Setter
	public function __get($name)
	{
		return $this->plugin_attributes[$name]->$name;
	}

	public function __set($name, $value)
	{
		return $this->plugin_attributes[$name]->$name = $value;
	}

	public function __isset($name)
	{
		return isset($this->plugin_attributes[$name]->$name);
	}

	public function __unset($name)
	{
		unset($this->plugin_attributes[$name]->$name);
	}

	public function __call($name, $arguments)
	{
		array_unshift($arguments, $this);
		return call_user_func_array(array($this->plugin_methods[$name], $name), $arguments);
	}

	private function valid_parameter($object, $method, $mode, $action)
	{
		// We cache the results... no worry. These checks are quite resource intensive, but will hopefully educate and guide developers

		// Check for correct first parameter. This must be an instance of phpbb_$phpbb_plugin
		$instance_of = 'phpbb_' . $object->phpbb_plugin;

		// Define the required function layout
		$function_layout = 'public function ' . $method . '(' . $instance_of . ' $object';

		// Result for PLUGIN_INJECT and action == 'return'
		if ($mode == phpbb::PLUGIN_INJECT && $action == 'return')
		{
			$function_layout .= ', $result';
		}

		$function_layout .= ', [...]) { [...] }';

		$reflection = new ReflectionMethod($object, $method);
		$parameters = $reflection->getParameters();
		$first_param = array_shift($parameters);

		// Try to get class
		if (empty($first_param))
		{
			return $function_layout;
		}

		try
		{
			$first_param->getClass()->name;
		}
		catch (Exception $e)
		{
			return $function_layout;
		}

		if ($first_param->getClass()->name !== $instance_of || $first_param->getName() !== 'object')
		{
			return $function_layout;
		}

		if ($mode == phpbb::PLUGIN_INJECT && $action == 'return')
		{
			$first_param = array_shift($parameters);

			if (empty($first_param) || $first_param->getName() !== 'result' || $first_param->isOptional())
			{
				return $function_layout;
			}
		}

		return true;
	}
}

// Class for the phpbb hooks
class phpbb_hooks
{
	public $hooks = array();

	public function register_hook($function, $hook, $mode = phpbb::FUNCTION_INJECT, $action = 'default')
	{
		// Hooks reachable by:
		// For function_override: hooks[function][mode] = hook
		// For function_inject: hooks[function][mode][action][] = hook

		// Check if the function is already overridden.
		if ($mode == phpbb::FUNCTION_OVERRIDE && isset($this->hooks[$function][$mode]))
		{
			trigger_error('Function ' . $function . ' is already overwriten by ' . $this->hooks[$function][$mode] . '.', E_USER_ERROR);
		}

		// Check for valid parameter?


		if ($mode == phpbb::FUNCTION_OVERRIDE)
		{
			$this->hooks[$function][$mode] = $hook;
		}
		else
		{
			$this->hooks[$function][$mode][$action][] = $hook;
		}
	}

	public function function_override($function)
	{
		return isset($this->hooks[$function][phpbb::FUNCTION_OVERRIDE]);
	}

	public function function_inject($function, $action = 'default')
	{
		return isset($this->hooks[$function][phpbb::FUNCTION_INJECT][$action]);
	}

	public function call_override()
	{
		$arguments = func_get_args();
		$function = array_shift($arguments);

		return call_user_func_array($this->hooks[$function][phpbb::FUNCTION_OVERRIDE], $arguments);
	}

	/**
	* Call injected function.
	*
	* Arguments are layed out in the following way:
	*	action: The action:
	*		'default':	If $action is default, then the hook is called in the beginning, original parameter passed by reference
	*		'return':	If $action is return, then the hook is called at the end and the result will be returned. The hook expects the $result as the first parameter, all other parameters passed by name
	*		If $action is not default and not return it could be a custom string. Please refer to the plugin documentation to determine possible combinations. Parameters are passed by reference.
	*
	* @param string $function Original function name this method is called from
	* @param array $arguments Arguments
	*/
	public function call_inject($function, $arguments)
	{
		$result = NULL;

		if (!is_array($arguments))
		{
			$action = $arguments;
			$arguments = array();
		}
		else
		{
			$action = array_shift($arguments);
		}

		// Return action... handle like override
		if ($action == 'return')
		{
			$result = array_shift($arguments);

			foreach ($this->hooks[$function][phpbb::FUNCTION_INJECT][$action] as $key => $hook)
			{
				$args = array_merge(array($result), $arguments);
				$result = call_user_func_array($hook, $args);
			}

			return $result;
		}

		foreach ($this->hooks[$function][phpbb::FUNCTION_INJECT][$action] as $key => $hook)
		{
			call_user_func_array($hook, $arguments);
		}
	}
}

interface phpbb_plugin
{
	function register_plugin(phpbb_plugin_support $object);
}

interface phpbb_plugin_info
{
	function setup_plugin(phpbb_plugin_setup $object);
}

phpbb::register('hooks');

?>