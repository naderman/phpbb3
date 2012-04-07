<?php

class phpbb_twigextension_include_node extends Twig_Node
{
	public function __construct($file, $lineno)
	{
		parent::__construct(array(), array('file' => $file), $lineno);
	}

	public function compile(Twig_Compiler $compiler)
	{
		$compiler
			->addDebugInfo($this)
			->write('$context["__phpbb_template_instance"]->_tpl_include("' . $this->getAttribute('file') . '")')
			//->subcompile($this->getNode('value'))
			->raw(";\n")
		;
	}
}
