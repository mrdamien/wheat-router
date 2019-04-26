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

namespace Wheat\Router;
use Wheat\Router\Element\Pattern;
use Wheat\Router\Element\Router;

abstract class Element
{
    const REGEX_MAX_SIZE = 16384;

    const TYPE_ELEMENT    = 'element';
    const TYPE_ROUTER     = 'router';
    const TYPE_PATH       = 'path';
    const TYPE_REGEX_PATH = 'regex_path';
    const TYPE_BLANK_PATH = 'blank_path';
    const TYPE_RETURN     = 'return';

    const NEXT_SEGMENT = 'NEXT_SEGMENT';
    const PREV_SEGMENT = 'PREV_SEGMENT';

    const INDENT = "\n-->\n";
    const UNINDENT = "\n<--\n";


    public $localVars = [];

    /**
     * @var Element
     */
    public $parent = null;

    /**
     * @var Element[]
     */
    public $children;

    /**
     * @var array
     */
    public $values = [];

    /**
     * @param string $name
     * @param mixed $value
     * @todo interpret $value
     * @return Element
     */
    public function setVar (string $name, $value): Element
    {
        $this->values[$name] = $value;
        return $this;
    }

    /**
     * @param string $name
     * @return Element
     */
    public function addVar (string $name): Element
    {
        $this->localVars[] = $name;
        return $this;
    }
    
    /**
     * @return array
     */
    public function getVars (): array
    {
        $vars = $this->localVars;
        if ($this->parent) {
            foreach ($this->parent->getVars() as $var) {
                $vars[] = $var;
            }
        }
        return $vars;
    }

    /**
     * 
     */
    public function __construct ()
    {
        $this->children = [];
        $this->localVars = [];
    }

    public function append (Element $child): Element
    {
        $this->children[] = $child;
        $child->setParent($this);
        return $this;
    }

    public function setParent (Element $e): Element
    {
        $this->parent = $e;
        return $this;
    }

    public function getParent (): ?Element
    {
        return $this->parent;
    }

    public function getType (): string
    {
        return self::TYPE_ELEMENT;
    }

    public function getRouter (): Router
    {
        $e = $this;
        while ($e->getType() !== Element::TYPE_ROUTER) {
            $e = $e->getParent();
        }
        return $e;
    }

    private function phpWrite ($var) {
        return var_export($var, true);
    }


    /**
     * @param string $key
     * @return string
     */
    public function interpret (string $key): string
    {
        switch ($key) {
            case 'true': return 'true';
            case 'null': return 'null';
            case 'false': return 'false';
        }

        $index = substr($key, 1, strlen($key)-2);

        // handle cases like: {variable:strtolower:ucfirst}
        $fnStr = '';
        $fnStrEnd = '';
        $functions = \explode(':', $index);

        while (count($functions) > 1) {
            [$fn] = array_splice($functions, 1, 1);
            $fnStr = $fn . '(' . $fnStr;
            $fnStrEnd .= ')';
        }
        $index = $functions[0];

        $code = [];
        $parent = $this;
        $vars = $this->getVars();
        foreach ($vars as $var) {
            $code[] = '$'.$var.'[\''.$index.'\']';
        }
        $code[] = '$get'."['" . $index . "']";
        $code[] = '$this->serverRequest'."['" . $index . "']";
        $code[] = '""';
        return $fnStr . '('.implode(" ?? ", $code).')' . $fnStrEnd;
    }

    public function interpretString (string $string)
    {
        $vars = preg_split('/({[a-z0-9_]+(:[a-z0-9_\\\]+)*})/i', $string,  -1, \PREG_SPLIT_DELIM_CAPTURE);

        for ($i=0; $i<count($vars); $i++) {
            $advance = false;
            if (!empty($vars[$i]) && $vars[$i][0] === '{' && $vars[$i][-1] === '}') {
                $advance = strpos($vars[$i], ':');
                $vars[$i] = $this->interpret($vars[$i]);

                // discard the next var because it contains :functionName
                if ($advance !== false) {
                    array_splice($vars, $i+1, 1);
                }
            } else {
                $vars[$i] = $this->phpWrite($vars[$i]);
            }
        }
        return implode('.', $vars);
    }

    public function toCode ()
    {

        foreach ($this->children as $child) {
            if (!in_array($child->getType(), [self::TYPE_PATH, self::TYPE_BLANK_PATH, self::TYPE_REGEX_PATH, self::TYPE_RETURN])) {
                yield from $child->toCode();
            }
        }


        $regexPaths = [];
        $stringPaths = [];
        if (count($this->values)) {
            $contextId = Parser::makeId();
            $this->addVar($contextId);
            foreach ($this->values as $k=>$v) {
                yield '$'.$contextId .'[' . var_export($k, true) . '] = ' . $this->interpretString($v) . ';';
            }
        }
        foreach ($this->children as $child) {
            if ($child->getType() === self::TYPE_PATH ) {
                $stringPaths[] = $child;
            } else if ($child->getType() === self::TYPE_REGEX_PATH) {
                $regexPaths[] = $child;
            }
        }

        if (count($stringPaths)) {

            yield 'switch ($segment) {';
            yield self::INDENT;
            foreach ($stringPaths as $child) {
                yield from $child->toCode();
            }
            yield self::UNINDENT;
            yield '}';
        }
        
        $regexChilds = [];
        $regexStrings = [];
        $regex = '/(?';
        $namedSegmentsCache = [];
        $namedSegments = [];
        while(\count($regexPaths) > 0) {
            $offset = 0;
            /** @var RegexPath $child */
            $child = array_shift($regexPaths);
            $namedSegmentsCache[] = $child->getSegmentIndexes();
            $markId = Parser::makeId();
            $child->setMarkId($markId);
            $regexChilds[$offset][$markId] = $child;
            $regex .= '|'.$child->getPattern().'(*:'.$markId.')';

            if (strlen($regex) >= self::REGEX_MAX_SIZE || count($regexPaths) === 0) {
                $regex .= ')/';
                $regexStrings[$offset] = $regex;
                $offset++;
                $namedSegments[] = $namedSegmentsCache;
                $namedSegmentsCache = [];
                $regex = '/(?';
            }
        }

        foreach ($regexStrings as $i=>$regex) {
            $varName = Parser::makeId();

            yield 'if (preg_match('.var_export($regex, true).', $segment, $'.$varName.')) {';
            yield self::INDENT;
            yield 'switch ($'.$varName.'[\'MARK\']) {';
                yield self::INDENT;
                foreach ($regexChilds[$i] as $child) {
                    $child->addVar($varName);
                    yield from $child->toCode($varName);
                }
                yield self::UNINDENT;
            yield '}';
            yield self::UNINDENT;

            yield '}';
        }

        foreach ($this->children as $child) {
            if (in_array($child->getType(), [self::TYPE_RETURN])) {
                yield from $child->toCode();
            }
        }

        // yield static::class. "\n";
    }

}