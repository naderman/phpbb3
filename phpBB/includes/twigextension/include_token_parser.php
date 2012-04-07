<?php


class phpbb_twigextension_include_token_parser extends Twig_TokenParser
{
	public function parse(Twig_Token $token)
	{
		$lineno = $token->getLine();
		$file = $this->parser->getStream()->expect(Twig_Token::NAME_TYPE)->getValue();

		while ($this->parser->getStream()->test(Twig_Token::PUNCTUATION_TYPE, '.'))
		{
			$this->parser->getStream()->expect(Twig_Token::PUNCTUATION_TYPE, '.');
			$file .= '.' . $this->parser->getStream()->expect(Twig_Token::NAME_TYPE)->getValue();
		}

		$this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);

		return new phpbb_twigextension_include_node($file, $lineno, $this->getTag());
	}

	public function getTag()
	{
		return 'INCLUDE';
	}
}
