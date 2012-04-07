<?php

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Represents a for node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class phpbb_twigextension_begin_node extends Twig_Node
{
    public function __construct($beginName, Twig_NodeInterface $body, Twig_NodeInterface $else = null, $lineno, $tag = null)
    {
        parent::__construct(array('body' => $body, 'else' => $else), array('beginName' => $beginName), $lineno, $tag);
    }

    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
    public function compile(Twig_Compiler $compiler)
    {
		$compiler
			->write("if (!isset(\$blocks)) {\n")
			->indent()
			->write("\$blocks = array();")
			->write("\$nestingLevel = 0;")
			->outdent()
			->write("}\n")
			->write("\$blocks[\$nestingLevel] = array();\n")
		;

        if (null !== $this->getNode('else')) {
            $compiler->write("\$blocks[\$nestingLevel]['iterated'] = false;\n");
        }
/*
        if ($this->getAttribute('with_loop')) {
            $compiler
                ->write("\$context['loop'] = array(\n")
                ->write("  'parent' => \$context['_parent'],\n")
                ->write("  'index0' => 0,\n")
                ->write("  'index'  => 1,\n")
                ->write("  'first'  => true,\n")
                ->write(");\n")
                ->write("if (is_array(\$context['_seq']) || (is_object(\$context['_seq']) && \$context['_seq'] instanceof Countable)) {\n")
                ->indent()
                ->write("\$length = count(\$context['_seq']);\n")
                ->write("\$context['loop']['revindex0'] = \$length - 1;\n")
                ->write("\$context['loop']['revindex'] = \$length;\n")
                ->write("\$context['loop']['length'] = \$length;\n")
                ->write("\$context['loop']['last'] = 1 === \$length;\n")
                ->outdent()
                ->write("}\n")
            ;
        }
*/
        $compiler
			->write("foreach (\$context['_phpbb_blocks']['")
			->write($this->getAttribute('beginName'))
			->write("'] as \$blocks[\$nestingLevel]['i'] => \$blocks[\$nestingLevel]['values']) {")
			->indent()
        ;

        $compiler->subcompile($this->getNode('body'));

        if (null !== $this->getNode('else')) {
            $compiler->write("\$blocks[\$nestingLevel]['iterated'] = true;\n");
        }
/*
        if ($this->getAttribute('with_loop')) {
            $compiler
                ->write("++\$context['loop']['index0'];\n")
                ->write("++\$context['loop']['index'];\n")
                ->write("\$context['loop']['first'] = false;\n")
                ->write("if (isset(\$context['loop']['length'])) {\n")
                ->indent()
                ->write("--\$context['loop']['revindex0'];\n")
                ->write("--\$context['loop']['revindex'];\n")
                ->write("\$context['loop']['last'] = 0 === \$context['loop']['revindex0'];\n")
                ->outdent()
                ->write("}\n")
            ;
        }
*/
        $compiler
            ->outdent()
            ->write("}\n")
        ;

        if (null !== $this->getNode('else')) {
            $compiler
                ->write("if (!\$blocks[\$nestingLevel]['iterated']) {\n")
                ->indent()
                ->subcompile($this->getNode('else'))
                ->outdent()
                ->write("}\n")
            ;
        }

		$compiler->write("\$nestingLevel--;\n");
    }
}
