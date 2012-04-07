<?php

class phpbb_twigextension_lexer extends Twig_Lexer
{
	protected function lexData()
    {
        $pos = $this->end;
        if (false !== ($tmpPos = strpos($this->code, $this->options['tag_comment'][0], $this->cursor))  && $tmpPos < $pos) {
            $pos = $tmpPos;
            $token = $this->options['tag_comment'][0];
        }
        if (false !== ($tmpPos = strpos($this->code, $this->options['tag_variable'][0], $this->cursor)) && $tmpPos < $pos) {
			if (preg_match('/\S/', $this->code[$this->cursor]))
			{
				$pos = $tmpPos;
				$token = $this->options['tag_variable'][0];
			}
        }
        if (false !== ($tmpPos = strpos($this->code, $this->options['tag_block'][0], $this->cursor))    && $tmpPos < $pos) {
			if (preg_match('/\s\S/', substr($this->code, $tmpPos + 4, 2)))
			{
				$pos = $tmpPos;
				$token = $this->options['tag_block'][0];
			}
        }

        // if no matches are left we return the rest of the template as simple text token
        if ($pos === $this->end) {
            $this->pushToken(Twig_Token::TEXT_TYPE, substr($this->code, $this->cursor));
            $this->cursor = $this->end;
            return;
        }

        // push the template text first
        $text = substr($this->code, $this->cursor, $pos - $this->cursor);
        $this->pushToken(Twig_Token::TEXT_TYPE, $text);
        $this->moveCursor($text.$token);

        switch ($token) {
            case $this->options['tag_comment'][0]:
                if (false === $pos = strpos($this->code, $this->options['tag_comment'][1], $this->cursor)) {
                    throw new Twig_Error_Syntax('unclosed comment', $this->lineno, $this->filename);
                }

                $this->moveCursor(substr($this->code, $this->cursor, $pos - $this->cursor) . $this->options['tag_comment'][1]);

                // mimicks the behavior of PHP by removing the newline that follows instructions if present
                if ("\n" === substr($this->code, $this->cursor, 1)) {
                    ++$this->cursor;
                    ++$this->lineno;
                }

                break;

            case $this->options['tag_block'][0]:
                // raw data?
                if (preg_match('/\s*raw\s*'.preg_quote($this->options['tag_block'][1], '/').'(.*?)'.preg_quote($this->options['tag_block'][0], '/').'\s*endraw\s*'.preg_quote($this->options['tag_block'][1], '/').'/As', $this->code, $match, null, $this->cursor)) {
                    $this->pushToken(Twig_Token::TEXT_TYPE, $match[1]);
                    $this->moveCursor($match[0]);
                    $this->state = self::STATE_DATA;
                } else {
                    $this->pushToken(Twig_Token::BLOCK_START_TYPE);
                    $this->state = self::STATE_BLOCK;
                }
                break;

            case $this->options['tag_variable'][0]:
                $this->pushToken(Twig_Token::VAR_START_TYPE);
                $this->state = self::STATE_VAR;
                break;
        }
    }
}
