<?php
/**
 * MIT License
 *
 * Copyright (c) 2017 Damien Lee
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */
declare (strict_types = 1);

namespace Wheat\Router\Element;
use Wheat\Router\Element;
use Wheat\Router\Parser;

class Path extends Element
{
    use NameTrait;

    public $pattern;
    
    public function setPattern (string $p): Element
    {
        $this->pattern = $p;
        return $this;
    }
    
    public function getPattern (): string
    {
        return $this->pattern;
    }
    
    public function getType (): string
    {
        return self::TYPE_PATH;
    }

    public function getSprintf (): string
    {
        $pattern = $this->pattern;

        // /** @var Parameter $param */
        // foreach ($this->parameters as $param) {
        // }
        $pattern = \preg_replace('/{\w+([^}]*?)}/', '%s', $pattern);

        return $pattern;
    }

    public function addMethod ()
    {
        $paths = [];
        $parent = $this;
        while ($parent->getParent()) {
            if ($parent->getType() === self::TYPE_PATH || $parent->getType() === self::TYPE_REGEX_PATH) {
                \array_unshift($paths, $parent);
            }
            $parent = $parent->getParent();
        }

        $args = [];
        $params = [];
        $sprintf = [''];
        // $j counts the path-segments.
        $j = 0;
        $l = count($paths)-1;
        foreach ($paths as $path) {
            $parsed = Parser::parsePatternString($path->pattern);
            
            $sprintf[] = $parsed['sprintf'];
            foreach ($parsed['names'] as $i=>$name) {
                // prevent underscore from affecting parameters-list unless it 
                // is appears at the end
                if ($name === "_" && $j < $l)  continue;

                $fn_pre = count($parsed['functions'][$i]) > 0 ? implode('(', $parsed['functions'][$i]) . '(' : '\rawurlencode(';
                $fn_post = count($parsed['functions'][$i]) > 0 ? str_repeat(')', count($parsed['functions'][$i])) : ')';
                $params[] = $parsed['types'][$i] . ' $'.$name;
                $args[] = $fn_pre . '$'.$name . $fn_post;
            }
            $j++;
        }

        // sprintf needs at least two arguments. excess ones will be ignored.
        // So if there is one argument, this will have no effect.
        if (count($args) === 0) $args[] = '"error"';


        return sprintf(
            "
            public function url%s (%s): string
            {
                return sprintf(%s, %s);
            }",
            $this->getName(),
            \implode(', ', $params),
            \var_export(\implode("/", $sprintf), true),
            \implode(', ', $args)
        );
    }

    public function getRoute ()
    {
        $paths = [];
        $parent = $this;
        while ($parent->getParent()) {
            if ($parent->getType() === self::TYPE_PATH || $parent->getType() === self::TYPE_REGEX_PATH) {
                \array_unshift($paths, $parent);
            }
            $parent = $parent->getParent();
        }

        $sprintf = [];
        foreach ($paths as $path) {
            $sprintf[] = $path->getSprintf();
        }
        $sprintf = '/'.\implode('/', $sprintf);
        return [$this->getName(), $sprintf];
    }
    
    public function toCode ()
    {
        yield sprintf(
            'case "%s":',
            $this->getPattern()
        );
        yield self::INDENT;
        yield self::NEXT_SEGMENT;

        yield from parent::toCode();

        yield self::PREV_SEGMENT;
        yield 'break;';

        yield self::UNINDENT;
    }
}