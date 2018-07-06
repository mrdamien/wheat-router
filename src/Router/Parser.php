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

class Parser
{
    const TOKEN_SCOPE_START = 0;
    const TOKEN_SCOPE_END = 1;


    private $root;
    private $regexTree = [];
    private $paths = [];
    private $namedBlocks = [];
    private $matchesStack = [];
    private $patternStack = [];
    private $nameStack = [];

    public function __construct(\DOMNode $root)
    {
        $this->root = $root;
    }

    private static $id = 0;
    public static function makeId ()
    {
        self::$id++;
        return sprintf('id_%04d', self::$id);
    }

    private function variableOrString ($value, array ...$matches)
    {
        switch ($value) {
            case '{scheme}':
                return $this->url['scheme'] ?? '';

            case '{user}':
                return $this->url['user'] ?? '';

            case '{pass}':
                return $this->url['pass'] ?? '';

            case '{host}':
                return $this->url['host'] ?? '';

            case '{port}':
                return $this->url['port'] ?? '';

            case '{path}':
                return $this->url['path'] ?? '/';

            case '{query}':
                return $this->url['query'] ?? '';

            case '{query_str}':
                return isset($this->url['query'])
                    ? (empty($this->url['query']) ? '' : '?'.$this->url['query'])
                    : '';

            case '{fragment}':
                return $this->url['fragment'] ?? '';

            case '{fragment_str}':
            return isset($this->url['fragment'])
                ? (empty($this->url['fragment']) ? '' : '#'.$this->url['fragment'])
                : '';

            case 'null':
                return null;

            case 'true':
                return true;

            case 'false':
                return false;
        }

        
        if ($value[0] === "{" && $value[-1] === "}") {
            $index = trim($value, '{}');
            foreach ($matches as $array) {
                if (isset($array[$index]) && !empty($array[$index])) {
                    return $array[$index];
                }
            }
            return $_SERVER[$index] ?? $_GET[$index] ?? '';
        }

        return $value;
    }

    private function invokeLogic (string $path = '/')
    {
        $this->httpCode = 200;
        $this->location = false;
        $this->render = false;
        $this->url = parse_url($path);

        $segmentIndex = 0;
        $segments = \SplFixedArray::fromArray(
            explode('/', substr($this->url['path'], 1))
        );
        $numSegments = $segments->getSize();


    }

    private function shouldQuit ($remaining)
    {
        if ($remaining === 0) {
            return true;
        }
        if ($this->location || $this->render) {
            return true;
        }
        return false;
    }

    public function generate ($id, array $data = [])
    {
        if (!isset($this->paths[$id])) {
            throw new \Exception("No such path ". $id);
        }

        $args = [];
        foreach ($this->paths[$id]['order'] as $name) {
            if (!isset($data[$name])) {
                throw new \Exception("Missing argument " . $name);
            }
            $args[] = $data[$name];
        }

        return sprintf(
            $this->paths[$id]['path'],
            ...$args
        );
    }

    private function fixRegex (string $regex)
    {
        if (empty($regex)) {
            return '//';
        }
        if (!preg_match('/\x'.dechex(ord($regex[0])).'[imsxeADSUXJu]*$/', $regex)) {
            return '/'.$regex.'/';
        }
        return $regex;
    }



    private function controlParse (\DOMElement $node, $returnValue = false)
    {
        $ret = [];
        $ifSwitch = [];
        $lastMatchesVar = '[]';
        $regexSwitch = [];

        $getSegment = '$segment = ($segmentIndex === $numSegments) ? null : $segments[$segmentIndex++];';
        $shouldQuit = 'if ($this->shouldQuit($numSegments-$segmentIndex)) {goto wheatRoutingFinished;}';

        $interpretString = function (string $string, $me) use (&$lastMatchesVar)
        {
            $vars = preg_split('/({[a-z0-9_]+})/i', $string,  -1, \PREG_SPLIT_DELIM_CAPTURE);
            for ($i=0; $i<count($vars); $i++) {
                if (!empty($vars[$i]) && $vars[$i][0] === '{') {
                    $vars[$i] = '$this->variableOrString(' . $me->phpWrite($vars[$i]) . ', ' . $lastMatchesVar . ')';
                } else {
                    $vars[$i] = $me->phpWrite($vars[$i]);
                }
            }
            return implode('.', $vars);
        };

        foreach ($node->childNodes as $child) {
            $lastMatchesVar = implode(", ", array_reverse($this->matchesStack));
            if (empty($lastMatchesVar)) {$lastMatchesVar = '[]';}
            /** @var \DOMElement $child */
            switch ($child->nodeName) {
                case 'router':
                    $this->readBlocks($child);
                    array_push($ret, ...$this->controlParse($child));
                    break;

                case 'ref':
                    $fnName = (string)$child->attributes->getNamedItem('name')->value;
                    if (!isset($this->namedBlocks[$fnName])) {
                        throw new \Exception("Found a <ref> with not matching <block>: " . $fnName);
                    }
                    if (count($this->namedBlocks[$fnName])) {
                        array_push($ret, ...$this->namedBlocks[$fnName]);
                    }
                    break;
                
                case 'http':
                    $code = (string)$child->attributes->getNamedItem('code')->value;
                    $route = $child->attributes->getNamedItem('route')
                        ? (string)$child->attributes->getNamedItem('route')->value
                        : '';
                    $location = $child->attributes->getNamedItem('location')
                        ? (string)$child->attributes->getNamedItem('location')->value
                        : '';
                    
                
                    $ret[] = '$this->httpCode = ' . $code . ';';
                    if (!empty($location)) {
                        $location = $interpretString($location, $this);

                        $ret[] = '$this->location = ' . $location . ';';
                    }
                    if (!empty($route)) {
                        $args = [];
                        foreach ($this->paths[$route]['order'] ?? [] as $arg) {
                            $args[$arg] = $child->attributes->getNamedItem($arg)
                            ? $this->phpWrite($arg).' => $this->variableOrString('
                                . $this->phpWrite((string)$child->attributes->getNamedItem($arg)->value)
                                . ', '.$lastMatchesVar.')'
                            : '';
                        }
                        $ret[] = '$this->location = $this->generate(' 
                            . $this->phpWrite($route) . ', [' . implode(',', $args) . ']'
                        .');';
                    }
                    break;
                
                case 'switch':
                    foreach ($child->childNodes as $node) {
                        if ($node->nodeName === "value") {
                            $value = $this->controlParse($node, true);
                            if ($value === []) {
                                $value = '$this->variableOrString('
                                    .$this->phpWrite((string)$node->textContent)
                                    .', '.$lastMatchesVar.')';
                            }
                            break;
                        }
                    }
                    $ret[] = 'switch (';
                    $ret[] = $value;
                    $ret[] = ') {';
                    $ret[] = $this->controlParse($child);
                    $ret[] = '}';
                    $ret[] = $shouldQuit;
                    break;
                
                case 'render':
                    $file = $child->attributes->getNamedItem('file')->value;
                    $ret[] = '$this->render = ' . $interpretString($file, $this) . ';';
                    break;

                case 'case':
                    
                    $ret[] = sprintf(
                            'case %s:',
                            '$this->variableOrString('.
                                $this->phpWrite(
                                    (string)$child->attributes->getNamedItem('value')->value
                                )
                            .', '.$lastMatchesVar.')'
                    );
                    $ret[] = $this->controlParse($child);
                    $ret[] = 'break;';
                    break;

                case 'default':
                    
                    $ret[] = 'default:';
                    $ret[] = $this->controlParse($child);
                    $ret[] = 'break;';
                    break;

                case 'call':
                    $args = $child->attributes->getNamedItem('with') 
                        ? (string)$child->attributes->getNamedItem('with')->value
                        : "";
                    $args = array_filter(str_getcsv($args));
                    foreach ($args as $k=>$arg) {
                        $args[$k] = $interpretString($arg, $this);
                    }

                    $fn = (string)$child->textContent;
                    $ret[] = sprintf(
                        'call_user_func_array("%s",[%s])%s',
                        $fn,
                        implode(", ", $args),
                        $returnValue ? '' : ';'
                    );
                    break;

                case 'test':
                    $pattern = $child->attributes->getNamedItem('pattern')
                        ? (string)$child->attributes->getNamedItem('pattern')->value
                        : '';
                    $subject = $child->attributes->getNamedItem('subject')
                        ? (string)$child->attributes->getNamedItem('subject')->value
                        : '';
                    $varName = '$'.self::makeId();
                    $this->matchesStack[] = $varName;
                    $ret[] = sprintf(
                        'if (preg_match(%s, $r = $this->variableOrString(%s, '.$lastMatchesVar.'), %s)) {',
                        $this->phpWrite($this->fixRegex($pattern)),
                        $this->phpWrite($subject),
                        $varName
                    );
                    $ret[] = $this->controlParse($child);
                    $ret[] = '}';
                    $ret[] = 'if ($this->shouldQuit($numSegments-$segmentIndex)) {goto wheatRoutingFinished;}';
                    array_pop($this->matchesStack);
                    break;

                case 'path':
                    $pattern = $child->attributes->getNamedItem('pattern')
                        ? (string)$child->attributes->getNamedItem('pattern')->value
                        : '';
                    $name = $child->attributes->getNamedItem('name')
                        ? (string)$child->attributes->getNamedItem('name')->value
                        : '';
                    $id = $child->attributes->getNamedItem('id')
                        ? (string)$child->attributes->getNamedItem('id')->value
                        : '';


                    if ($pattern) {
                        if ($this->isRegex($pattern)) {
                            $this->nameStack[] = $name;
                            $this->patternStack[] = '%s';

                            $varName = '$'.self::makeId();
                            $this->matchesStack[] = $varName;
                            $if = sprintf(
                                'if (preg_match(%s, $segment, %s)) {',
                                $this->phpWrite($this->fixRegex($pattern)),
                                $varName
                            );

                            $tmp = [];
                            $tmp[] = $if;
                            $children = $this->controlParse($child);
                            if (!$children) {
                                $tmp[] = [$shouldQuit];
                            } else {
                                $tmp[] = $children;
                            }
                            $tmp[] = '}';
                            $regexSwitch[] = $tmp;
                            array_pop($this->matchesStack);
                        } else {
                            $this->patternStack[] = $pattern;
                            $if = sprintf(
                                'if ($segment === %s) {',
                                $this->phpWrite($pattern)
                            );

                            $tmp = [];
                            $tmp[] = $if;
                            $children = $this->controlParse($child);
                            if (!$children) {
                                $tmp[] = [$shouldQuit];
                            } else {
                                $tmp[] = $children;
                            }
                            $tmp[] = '}';
                            $ifSwitch[] = $tmp;
                        }
                        
                        if (!empty($id)) {
                            $this->paths[$id] = [
                                'path' => '/'.implode('/', $this->patternStack),
                                'order' => $this->nameStack
                            ];
                        }
    

                        array_pop($this->nameStack);
                        array_pop($this->patternStack);
                    } else {
                        $ret[] = $this->controlParse($child);
                    }
                    break;
            }
        }

        if (count($ifSwitch) + count($regexSwitch)) {
            $ret[] = $getSegment;
            for ($i=0, $l=count($ifSwitch); $i<$l; $i++) {
                // $ret[] = $ifSwitch[$i];
                array_push($ret, ...$ifSwitch[$i]);
                if ($i < $l-1) {
                    $ret[] = 'else';
                }
            }
            for ($i=0, $l=count($regexSwitch); $i<$l; $i++) {
                array_push($ret, ...$regexSwitch[$i]);
                // $ret[] = $regexSwitch[$i];
            }

            $ret[] = '$segmentIndex--;';
            $ret[] = $shouldQuit;
        }

        return $ret;
    }

    private function isRegex (string $pattern)
    {
        return (bool)preg_match('/[\|\(\)\\\\\/\?]/', $pattern);
    }

    private function phpWrite ($var) {
        return var_export($var, true);
    }

    /**
     * @param \DOMElement $node
     * @return void
     */
    private function readBlocks (\DOMElement $node)
    {
        foreach ($node->childNodes as $child) {
            /** @var \DOMElement $child */
            if ($child->nodeName === 'block') {
                $fnName = (string)$child->attributes->getNamedItem('name')->value;
                $this->namedBlocks[$fnName] = $this->controlParse($child);
            }
        }
    }

    public function outputCode (string $file)
    {
        $this->readBlocks($this->root->documentElement);

        $this->syntaxTree = [
            self::TOKEN_SCOPE_START
        ];
        array_push($this->syntaxTree, ...$this->controlParse($this->root->documentElement, $this->regexTree));

        $fp = fopen($file, 'w');
        if ($fp === false) {
            throw new \Exception('Could not open Wheat\\Router cache file for writting');
        }

        fwrite($fp, "<?php /* Code automatically generated by Wheat\Router. Do not edit. */\n");
        fwrite($fp, "return new class implements \Wheat\Router\RouterInterface {\n");
        fwrite($fp, "    public \$httpCode = 200;\n");
        fwrite($fp, "    public \$location = false;\n");
        fwrite($fp, "    public \$render   = false;\n");
        fwrite($fp, "    public \$url      = [];\n");
        fwrite($fp, "    public \$paths    = " . var_export($this->paths, true).";\n");

        $lines = file(__FILE__);
        $reflectionClass = new \ReflectionClass($this);
        foreach (['variableOrString', 'shouldQuit', 'generate'] as $methodName) {
            $method = $reflectionClass->getMethod($methodName);
            $start = $method->getStartLine();
            $end = $method->getEndLine();
            for($i=$start-1; $i<$end; $i++) {
                fwrite($fp, $lines[$i]);     
            }
        }

        $output = function($data, $indent) use ($fp, &$output) {
            foreach ($data as $line) {
                if (is_array($line)) {
                    $output($line, $indent+1);
                } else if (is_string($line)) {
                    fwrite($fp, str_repeat(' ', 4*$indent).$line."\n");
                }
            }
        };


        
        fwrite($fp, "    public function route(string \$path): array\n");

        $method = $reflectionClass->getMethod('invokeLogic');
        $start = $method->getStartLine();
        $end = $method->getEndLine()-1;
        for($i=$start; $i<$end; $i++) {
            fwrite($fp, $lines[$i]);     
        }
            

        $output($this->syntaxTree, 2);


        fwrite($fp, "        \$this->httpCode = 404;\n");
        fwrite($fp, "        wheatRoutingFinished:\n");
        fwrite($fp, "        return [\n");
        fwrite($fp, "            'httpCode' => \$this->httpCode,\n");
        fwrite($fp, "            'location' => \$this->location,\n");
        fwrite($fp, "            'render' => \$this->render,\n");
        fwrite($fp, "        ];\n");
        fwrite($fp, "    }\n");
        fwrite($fp, "};");     
    }


}