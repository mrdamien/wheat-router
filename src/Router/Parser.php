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

/*
preg_match("@(?<route1>/about/banana)|(?<route0>/about/apple)|(?<route2>/about/(\d+))@","/about/3", $matches);
print_r($matches); $end=microtime(true); printf("Time: %fms", 1000*($end-$s));
Array
(
    [0] => /about/3
    [route1] => 
    [1] => 
    [route0] => 
    [2] => 
    [route2] => /about/3
    [3] => /about/3
    [4] => 3
)*/

class Parser
{
    const TOKEN_SCOPE_START = 0;
    const TOKEN_SCOPE_END = 1;


    private $root;
    private $regexTree = [];
    private $paths = [];
    private $namedBlocks = [];
    private $matchesStack = [];

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
            case '{route}':
                return $this->route;

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
        $this->route = false;
        $this->location = false;
        $this->render = false;
        $this->url = parse_url($path);

        $segmentIndex = 0;
        $segments = \SplFixedArray::fromArray(
            explode('/', substr($this->url['path'], 1))
        );
        $numSegments = $segments->getSize();

        $backSegment = function() use (&$segmentIndex) {
            $segmentIndex--;
        };
        $getSegment = function() use (&$segments, &$segmentIndex, $numSegments) {
            if ($segmentIndex === $numSegments) {
                return null;
            }
            return $segments[$segmentIndex++];
        };


    }

    private function shouldQuit ($remaining)
    {
        if ($remaining === 0) {
            return true;
        }
        if ($this->route || $this->location || $this->render) {
            return true;
        }
        return false;
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
        $pathStack = [];
        $ret = [];
        $ifSwitch = [];
        $lastMatchesVar = '[]';
        $regexSwitch = [];

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
                case 'ref':
                    $fnName = (string)$child->attributes->getNamedItem('name')->value;
                    if (!isset($this->namedBlocks[$fnName])) {
                        throw new \Exception("Found a <ref> with not matching <block>: " . $fnName);
                    }
                    array_push($ret, ...$this->namedBlocks[$fnName]);
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
                        $ret[] = '$this->route = false;';
                    }
                    if (!empty($route)) {
                        $ret[] = '$this->route = ' . $this->phpWrite($route) . ';';
                        $ret[] = '$this->location = false;';
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
                    $ret[] = 'if ($this->shouldQuit($numSegments-$segmentIndex)) {return $this->httpCode;}';
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
                    $ret[] = 'if ($this->shouldQuit($numSegments-$segmentIndex)) {return $this->httpCode;}';
                    array_pop($this->matchesStack);
                    break;

                case 'path':
                    $pattern = $child->attributes->getNamedItem('pattern')
                        ? (string)$child->attributes->getNamedItem('pattern')->value
                        : '';
                    $name = $child->attributes->getNamedItem('name')
                        ? (string)$child->attributes->getNamedItem('name')->value
                        : '';
                    if (!empty($path)) {
                        $pathStack[] = $name;
                        $this->paths[implode('.', $pathStack)] = [
                            'path' => str_repeat('/%s', count($pathStack)),
                            'default' => []
                        ];
                    }

                    if ($pattern) {
                        if ($this->isRegex($pattern)) {
                            $varName = '$'.self::makeId();
                            $this->matchesStack[] = $varName;
                            $if = sprintf(
                                'if (preg_match(%s, $segment, %s)) {',
                                $this->phpWrite($this->fixRegex($pattern)),
                                $varName
                            );

                            $tmp = [];
                            $tmp[] = $if;
                            $tmp[] = $this->controlParse($child);
                            $tmp[] = '}';
                            $regexSwitch[] = $tmp;
                            array_pop($this->matchesStack);
                        } else {
                            $if = sprintf(
                                'if ($segment === %s) {',
                                $this->phpWrite($pattern)
                            );

                            $tmp = [];
                            $tmp[] = $if;
                            $tmp[] = $this->controlParse($child);
                            $tmp[] = '}';
                            $ifSwitch[] = $tmp;
                        }
                    } else {
                        $ret[] = $this->controlParse($child);
                    }
                    break;
            }
        }

        if (count($ifSwitch) + count($regexSwitch)) {
            $ret[] = '$segment = $getSegment();';
            for ($i=0, $l=count($ifSwitch); $i<$l; $i++) {
                $ret[] = $ifSwitch[$i];
                if ($i < $l-1) {
                    $ret[] = 'else';
                }
            }
            for ($i=0, $l=count($regexSwitch); $i<$l; $i++) {
                $ret[] = $regexSwitch[$i];
            }

            $ret[] = '$segmentIndex--;';
            $ret[] = 'if ($this->shouldQuit($numSegments-$segmentIndex)) {return $this->httpCode;}';
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

        // $fp = fopen('php://stdout', 'w');
        $fp = fopen($file, 'w');
        fwrite($fp, "<?php /* Code automatically generated by Wheat\Router. Do not edit. */\n");
        fwrite($fp, "return new class {\n");
        fwrite($fp, "    public \$httpCode = 200;\n");
        fwrite($fp, "    public \$route    = false;\n");
        fwrite($fp, "    public \$location = false;\n");
        fwrite($fp, "    public \$render   = false;\n");
        fwrite($fp, "    public \$url      = [];\n");

        $lines = file(__FILE__);
        $reflectionClass = new \ReflectionClass($this);
        foreach (['variableOrString', 'shouldQuit'] as $methodName) {
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


        
        fwrite($fp, "    public function __invoke(\$path)\n");

        $method = $reflectionClass->getMethod('invokeLogic');
        $start = $method->getStartLine();
        $end = $method->getEndLine()-1;
        for($i=$start; $i<$end; $i++) {
            fwrite($fp, $lines[$i]);     
        }
            

        $output($this->syntaxTree, 2);
        fwrite($fp, "        return 404;\n");
        fwrite($fp, "    }\n");

        fwrite($fp, "};");     
    }


}