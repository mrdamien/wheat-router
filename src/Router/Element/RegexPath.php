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

class RegexPath extends Path
{
    use NameTrait;

    public $pattern;
    
    public $markId;

    public function setPattern (string $p): Element
    {
        $this->pattern = $p;
        return $this;
    }

    public function getSegmentIndexes (): array
    {
        $segments = [];
        preg_match_all('/({(?<name>[^:}]+?)(:(?<regex>(.+?)))?})/', $this->pattern, $matches);
        $i = 0;
        foreach ($matches['name'] as $i=>$name) {
            $segments[$name] = $i+1;
        }
        return $segments;
    }
    
    public function getPattern (): string
    {
        $parsed = Parser::parsePatternString($this->pattern);
        return $parsed['regex'];
    }
    
    public function getType (): string
    {
        return self::TYPE_REGEX_PATH;
    }

    public function setMarkId (string $m): Element
    {
        $this->markId = $m;
        return $this;
    }
    
    public function toCode (string $contextName = '')
    {
        $segments = $this->getSegmentIndexes();
        yield sprintf(
            'case "%s":',
            $this->markId
        );
        yield self::INDENT;
        yield self::NEXT_SEGMENT;
        foreach ($segments as $name=>$index) {
            yield '$'.$contextName.'['.var_export($name,true).'] = $' . $contextName . '['.$index.'] ?? \'\';';
        }

        yield from Element::toCode();

        yield self::PREV_SEGMENT;
        yield 'break;';

        yield self::UNINDENT;
    }
    
}