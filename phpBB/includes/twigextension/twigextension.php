<?php

class phpbb_twigextension extends Twig_Extension
{
	public function getName()
	{
		return 'phpbb';
	}

	public function getTokenParsers()
	{
		return array(
			new phpbb_twigextension_if_token_parser,
			new phpbb_twigextension_include_token_parser,
			new phpbb_twigextension_begin_token_parser,
		);
	}

	public function getOperators()
	{
		return array(
			array(),
			array(
				'eq' => array('precedence' => 20, 'class' => 'Twig_Node_Expression_Binary_Equal', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
			),
		);
	}
}
