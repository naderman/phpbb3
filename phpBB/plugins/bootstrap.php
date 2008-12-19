<?php

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

	public function add_plugin(){}
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

	public function register_function() {}

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
				continue;
			}

			// Is the plugin the mod author wants to influence pluggable?
			if (!is_subclass_of(phpbb::get_instance($object->phpbb_plugin), 'phpbb_plugin_support'))
			{
				continue;
			}

			// Register plugin...
			$object->register_plugin(phpbb::get_instance($object->phpbb_plugin));
		}
	}
}

class phpbb_plugin_support
{
	private $plugin_methods;
	private $plugin_attributes;
	private $plugin_functions;

	private $plugin_modes;

	protected function method_override($name)
	{
		return isset($this->plugin_modes[$name][phpbb::METHOD_OVERRIDE]);
	}

	protected function method_prefix($name)
	{
		return isset($this->plugin_modes[$name][phpbb::METHOD_PREFIX]);
	}

	protected function method_suffix($name)
	{
		return isset($this->plugin_modes[$name][phpbb::METHOD_SUFFIX]);
	}

	public function register_method($name, $method, $object, $mode = phpbb::METHOD_ADD)
	{
		if (method_exists($this, $name) && $mode == phpbb::METHOD_ADD)
		{
			trigger_error('Method ' . $method. ' in class ' . get_class($object) . ' is not able to be added, because it conflicts with the existing method ' . $name, E_USER_ERROR);
		}

		if (isset($this->plugin_methods[$name][$method]))
		{
			trigger_error('Method ' . $method . ' in class ' . get_class($object) . ' for ' . $name . ' is already defined in class ' . get_class($this->plugin_methods[$name][$method]), E_USER_ERROR);
		}

		if (isset($this->plugin_modes[$name][$mode][$method]))
		{
			trigger_error('Method ' . $method . ' in class ' . get_class($object) . ' for ' . $name . ' is already defined.', E_USER_ERROR);
		}

		if ($mode == phpbb::METHOD_ADD)
		{
			$this->plugin_methods[$method] = $object;
		}
		else
		{
			$this->plugin_methods[$name][$method] = $object;
			$this->plugin_modes[$name][$mode][$method] = true;
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

	public function call_override()
	{
		$args = func_get_args();
		$name = array_shift($args);

		foreach ($this->plugin_modes[$name][phpbb::METHOD_OVERRIDE] as $method => $null)
		{
			call_user_func_array(array($this->plugin_methods[$name][$method], $method), array_merge(array($this), $args));
		}
	}

	public function call_prefix()
	{
		$args = func_get_args();
		$name = array_shift($args);

		foreach ($this->plugin_modes[$name][phpbb::METHOD_PREFIX] as $method => $null)
		{
			call_user_func_array(array($this->plugin_methods[$name][$method], $method), array_merge(array($this), $args));
		}
	}

	public function call_suffix()
	{
		$args = func_get_args();
		$name = array_shift($args);
		$result = array_shift($args);

		foreach ($this->plugin_modes[$name][phpbb::METHOD_SUFFIX] as $method => $null)
		{
			$arguments = array_merge(array($this, $result), $args);
			$result = call_user_func_array(array($this->plugin_methods[$name][$method], $method), $arguments);
		}

		return $result;
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

	function __call($name, $arguments)
	{
		array_unshift($arguments, $this);
		return call_user_func_array(array($this->plugin_methods[$name], $name), $arguments);
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

?>