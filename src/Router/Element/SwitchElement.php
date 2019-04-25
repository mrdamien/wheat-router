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

class SwitchElement extends Element
{
    public function toCode ()
    {
        $children = [];
        $value = null;

        foreach ($this->children as $child) {
            if ($child instanceof Value) {
                $value = $child;
            } else {
                $children[] = $child;
            }
        }

        if (count($value->children) > 0) {
            yield 'switch (';
                foreach ($value->children as $child) {
                    yield from $child->toCode('');
                }
            yield ') {';
        } else {
            yield 'switch (';
            yield self::INDENT;
            yield from $value->toCode();
            yield self::UNINDENT;
            yield ') {';
        }

        yield self::INDENT;

        foreach ($children as $child) {
            yield from $child->toCode();
        }

        yield self::UNINDENT;
        yield '}';
    }
}