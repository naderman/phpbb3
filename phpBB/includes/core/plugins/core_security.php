<?php
if (!defined('IN_PHPBB'))
{
	exit();
}

class phpbb_core_security implements phpbb_plugin
{
	var $phpbb_plugin = 'security';
	var $x = 1;

	function register_plugin(phpbb_plugin_support $object)
	{
		$object->register_method('test', $this);
		$object->register_method('unique_id', $this);

		$object->register_attribute('x', $this);
		$object->register_attribute('hash_type', $this);

		$object->register_append('gen_rand_string', $this);
	}

	public function test(phpbb_security $object)
	{
		echo 'test';
		print_r($object);
	}

	public function gen_rand_string(phpbb_security $object, $result, $extra = 'c')
	{
		$result = $result . ';test2;';
		return $result;
	}

	public function unique_id(phpbb_security $object)
	{
		return md5('tst');
	}
}

$plugin = new phpbb_core_security();

?>