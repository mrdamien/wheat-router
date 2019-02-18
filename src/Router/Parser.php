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

interface Pattern {
    public function getIdentity (): string;
    public function getType (): string;
    public function getPattern (): string;
    public function getTemplate (): string;
    public function getEncoder (string $param): ?string;
    public function setEncoder (string $param, string $fn);
    public function getTypehint (string $param): string;
    public function setTypehint (string $param, string $type);
}

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
    private $patternHashMap = [];
    private $nameStack = [];
    private $includes;
    private $syntaxTree;

    // just to make phpstan be quiet.
    private $url;
    private $globals;
    private $serverRequest;

    public function __construct(\DOMNode $root)
    {
        $this->root = $root;
    }

    private static $id = 0;
    public static function makeId ()
    {
        self::$id++;
        return sprintf('id%d', self::$id);
    }

    /**
     * @param array $patterns
     * @param string $template
     * @return Pattern
     */
    private function regexPattern(array $patterns, string $template): Pattern {
        return new class ($patterns, $template) implements Pattern {
            public $patterns;
            public $id = '', $template, $encoders, $types;
            public function __construct($patterns, $template)
            {
                $this->template = $template;
                $this->patterns = [
                    0 => [],
                    'name' => [],
                    'regex' => []
                ];
                foreach ($patterns['name'] as $i=>$pattern) {
                    if ($pattern !== '_') {
                        $this->patterns[0][] = $patterns[0][$i];
                        $this->patterns['name'][] = $pattern;
                        $this->patterns['regex'][] = $patterns['regex'][$i];
                    } else {
                        $this->template = str_replace($patterns[0][$i], '', $this->template);
                    }
                }
                $this->encoders = [];
                $this->types = [];
                $id = $template;
                foreach ($this->patterns[0] as $i=>$pattern) {
                    $id = str_replace($pattern, $this->patterns['regex'][$i], $id);
                    $this->encoders[$this->patterns['name'][$i]] = '\\rawurlencode';
                    $this->types[$this->patterns['name'][$i]] = '';
                }
                $this->id = $id;
            }
            public function getType (): string
            {
                return 'regex';
            }
            public function getIdentity (): string
            {
                return $this->id;
            }
            public function getPattern (): string
            {
                $regex = $this->template;
                foreach ($this->patterns[0] as $i=>$pattern) {
                    $name = $this->patterns['name'][$i];
                    $r = $this->patterns['regex'][$i];
                    $regex = str_replace($pattern, '(?<'.$name.'>'.$r.')', $regex);
                }
                return '^' . $regex . '$';
            }
            public function getTemplate (): string
            {
                $template = $this->template;
                foreach ($this->patterns[0] as $i=>$pattern) {
                    $template = str_replace($pattern, '%s', $template);
                }
                return $template;
            }
            public function getEncoder (string $param): ?string
            {
                return $this->encoders[$param] ?? null;
            }
            public function setEncoder (string $param, string $fn)
            {
                $this->encoders[$param] = $fn;
            }
            public function getTypehint (string $param): string
            {
                return $this->types[$param];
            }
            public function setTypehint (string $param, string $type)
            {
                $this->types[$param] = $type;
            }
        };
    }

    /**
     * @param string $template
     * @return Pattern
     */
    private function regularPattern(string $template): Pattern {
        return new class ($template) implements Pattern {
            public $id = '';
            public function __construct($id)
            {
                $this->id = $id;
            }
            public function getType (): string
            {
                return 'regular';
            }
            public function getIdentity (): string
            {
                return $this->id;
            }
            public function getPattern (): string
            {
                return $this->id;
            }
            public function getTemplate (): string
            {
                return $this->id;
            }
            public function getEncoder (string $param): ?string
            {
            }
            public function setEncoder (string $param, string $fn)
            {
            }
            public function getTypehint (string $param): string
            {
            }
            public function setTypehint (string $param, string $type)
            {
            }
        };
    }

    /**
     * @param string $pattern
     * @return Pattern
     */
    private function patternFromString (string $pattern): Pattern
    {
        $found = preg_match_all('/({(?<name>[^:}]+?)(:(?<regex>(.+?)))?})/', $pattern, $matches);
        if ($found) {
            foreach ($matches['regex'] as &$regex) {
                if ($regex === '') {
                    $regex = '.+';
                }
            }
            return $this->regexPattern($matches, $pattern);
        } else {
            return $this->regularPattern($pattern);
        }

    }

    private function fixRegex (string $regex)
    {
        if (empty($regex)) {
            return '//';
        }
        // if (!preg_match('/\x'.dechex(ord($regex[0])).'[imsxeADSUXJu]*$/', $regex)) {
        //     return '/'.$regex.'/';
        // }
        return $regex;
    }



    private function controlParse (\DOMElement $node, $returnValue = false)
    {
        $ret = [];
        $ifSwitch = [];
        $testStack = [];
        $lastMatchesVar = '[]';
        $regexSwitch = [];
        $assignments = [];
        $regexStringSegments = [];
        $regexCase = [];

        $getSegment = '$segment = ($si === $numSegments) ? \'\' : $segments[$si++];';


        $interpret = function($key) {
            switch ($key) {
                case 'true': return 'true';
                case 'null': return 'null';
                case 'false': return 'false';
            }
            
            if ($key[0] === "{" && $key[-1] === "}") {
                $code = [];
                $index = substr($key, 1, strlen($key)-2);
                for ($i=count($this->matchesStack)-1; $i>=0; $i--) {
                    $code[] = $this->matchesStack[$i]."['" . $index . "']";
                }
                $code[] = '$this->globals'."['" . $index . "']";
                $code[] = '$_GET'."['" . $index . "']";
                $code[] = '$this->serverRequest'."['" . $index . "']";
                $code[] = '""';
                return '('.implode(" ?? ", $code).')';
            }
            return var_export($key, true);
        };

        $interpretString = function (string $string, $me) use ($interpret)
        {
            $vars = preg_split('/({[a-z0-9_]+})/i', $string,  -1, \PREG_SPLIT_DELIM_CAPTURE);
            for ($i=0; $i<count($vars); $i++) {
                if (!empty($vars[$i]) && $vars[$i][0] === '{') {
                    $vars[$i] = $interpret($vars[$i]);
                } else {
                    $vars[$i] = $me->phpWrite($vars[$i]);
                }
            }
            return implode('.', $vars);
        };

        $valueAsStringOrEmpty = function($node): string {
            return $node ? (string)$node->value : '';
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

                case 'set':
                    foreach ($child->attributes as $attr) {
                        $assignments[] = sprintf(
                            '$this->globals[\'%s\'] = %s;',
                            (string)$attr->localName,
                            $interpret((string)$attr->nodeValue)
                        );
                    }
                    break;
                
                case 'return':
                    $data = [];
                    foreach ($child->attributes as $attr) {
                        $name = (string)$attr->name;
                        $value = $child->attributes->getNamedItem($name)->value;
                        $data[$name] = sprintf(
                            '%s => ' . $interpretString($value, $this),
                            $this->phpWrite($name)
                        );
                    }
                    if (!isset($data['code'])) {
                        $data['code'] = "'code' => '200'";
                    }
                    $ret[] = 'return [' . implode(',', $data) . '];';

                    break;
                
                case 'switch':
                    $empty = true;
                    $value = null;
                    foreach ($child->childNodes as $node) {
                        $empty = false;
                        if ($node->nodeName === "value") {
                            $value = $this->controlParse($node, true);
                            if ($value === []) {
                                $value = $interpret((string)$node->textContent);
                            }
                            break;
                        }
                    }
                    $tmp = [];
                    if (!$empty) {
                        if (is_string($value)) {
                            $tmp[] = 'switch ('.$value.')';
                        } else {
                            $tmp[] = 'switch (';
                            $tmp[] = $value;
                            $tmp[] = ')';
                        }
                        $tmp[] = '{';
                        $tmp[] = $this->controlParse($child);
                        $tmp[] = '}';
                        $testStack[] = $tmp;
                    }
                    break;

                case 'case':
                    $ret[] = sprintf(
                            'case %s:',
                            $interpret((string)$child->attributes->getNamedItem('value')->value)
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
                    $args = $valueAsStringOrEmpty($child->attributes->getNamedItem('with'));
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
                    $pattern = $valueAsStringOrEmpty($child->attributes->getNamedItem('pattern'));
                    $subject = $valueAsStringOrEmpty($child->attributes->getNamedItem('subject'));
                    $varName = '$'.self::makeId();
                    $tmp = [];
                    $tmp[] = sprintf(
                        'if (preg_match(%s, %s, %s)) {',
                        $this->phpWrite($this->fixRegex($pattern)),
                        $interpret($subject),
                        $varName
                    );
                    $this->matchesStack[] = $varName;
                    $tmp[] = $this->controlParse($child);
                    $tmp[] = '}';
                    $testStack[] = $tmp;
                    array_pop($this->matchesStack);
                    break;

                case 'path':
                    $id = $valueAsStringOrEmpty($child->attributes->getNamedItem('id'));

                    if ( $child->attributes->getNamedItem('pattern')) {
                        $pattern = (string)$child->attributes->getNamedItem('pattern')->value;
                        $pattern = $this->patternFromString($pattern);
                        $this->patternStack[] = $pattern;

                        if ($pattern->getType() === 'regex') {
                            foreach ($child->childNodes as $parameter) {
                                if ($parameter->nodeName === 'parameter') {
                                    $name = (string)$parameter->attributes->getNamedItem('name')->value;
                                    $func = $valueAsStringOrEmpty($parameter->attributes->getNamedItem('function'));
                                    $type = $valueAsStringOrEmpty($parameter->attributes->getNamedItem('type'));
                                    if ($pattern->getEncoder($name) === null) {
                                        throw new \Exception("No parameter $name in route");
                                    }
                                    $pattern->setEncoder($name, $func);
                                    $pattern->setTypehint($name, $type);
                                }
                            }
                            $regexCase[] = [$pattern, $child];
                        } else {
                            $case = sprintf(
                                'case %s:',
                                $this->phpWrite($pattern->getPattern())
                            );

                            $tmp = [];
                            $tmp[] = $case;
                            $tmp[] = $this->controlParse($child);
                            $tmp[] = 'break;';
                            $ifSwitch[] = $tmp;


                            $patternSegments = array_map(function(Pattern $e){ return $e->getIdentity(); }, $this->patternStack);
                            $pattern = implode('/', $patternSegments);
                            if (isset($this->patternHashMap[$pattern])) {
                                throw new \Exception("Path $pattern seems to have a conflict.");
                            }
                            $this->patternHashMap[$pattern] = true;
                        }
                        
                        if (!empty($id)) {
                            $this->paths[$id] = $this->patternStack;
                        }
                        
                        array_pop($this->nameStack);
                        array_pop($this->patternStack);
                    } else {
                        $ret[] = $this->controlParse($child);
                    }
                    break;
            }
        }

        $code = [];
        if (count($assignments)) {array_push($code, ...$assignments);}

        if (count($ifSwitch) + count($regexCase) + count($testStack)) {
            for ($i=0, $l=count($testStack); $i<$l; $i++) {
                array_push($code, ...$testStack[$i]);
            }
            if (count($ifSwitch) + count($regexCase)) {
                $code[] = $getSegment;
                $code[] = 'switch ($segment) {';
                for ($i=0, $l=count($ifSwitch); $i<$l; $i++) {
                    array_push($code, ...$ifSwitch[$i]);
                }
                $code[] = ['default:'];

                if (count ($regexCase)) {
                    $n = 0;
                    $regex = '/(?';
                    $regexCount = count($regexCase);
                    $childNodes = [];
                    foreach ($regexCase as $index=>$regexStruct) {
                        [$pattern, $child] = $regexStruct;
                        $markId = self::makeId();
                        $regex .= '|'.$pattern->getPattern().'(*:'.$markId.')';
                        $childNodes[$markId] = $child;

                        if ($index === $regexCount-1 || strlen($regex) >= 1024) {
                            $regex .= ')/';
                            $varName = '$'.self::makeId();
                            $this->matchesStack[] = $varName;
                            $code[] = [sprintf(
                                'if (preg_match(%s, $segment, %s)) {',
                                    $this->phpWrite($regex),
                                    $varName
                                )];
                            $code[] = [['switch ('.$varName.'[\'MARK\']) {']];
                            foreach ($childNodes as $markId=>$child) {
                                $code[] = [[[
                                    'case \''.$markId.'\':',
                                    $this->controlParse($child),
                                    'break;'
                                ]]];
                            }
                            array_pop($this->matchesStack);

                            $code[] = [['}']]; // endswitch


                            $regex = '/(?';
                            $code[] = ['}']; // end if
                        }
                    }
                }


                $code[] = ['break;'];
                $code[] = '}';
            }
                
            if (count($ifSwitch) + count($regexSwitch)) $code[] = '$si--;';
        }

        if (count($ret)) {array_push($code, ...$ret);}

        return $code;
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

    public function writePathFunctions($fp)
    {
        foreach ($this->paths as $id=>$pathArray) {
            /** @var Pattern $path */
            $arguments = [];
            $parameters = [];
            $pathTemplate = '';
            foreach ($pathArray as $part) {
                if ($part->getTemplate() === '') {
                    continue;
                }
                if($part->getType() === 'regex') {
                    foreach ($part->patterns['name'] as $arg) {
                        $arguments[] = (empty($part->getTypehint($arg)) ? '' : $part->getTypehint($arg).' ') . '$'.$arg;
                        if (empty($part->getEncoder($arg))) {
                            $parameters[] = '$'.$arg;
                        } else {
                            $parameters[] = $part->getEncoder($arg).'($'.$arg.')';
                        }
                    }
                }
                $pathTemplate .= '/'.$part->getTemplate();
            }

            fwrite($fp, sprintf("    public function url%s (%s): string\n", $id, implode(", ", $arguments)));
            fwrite($fp,         "    {\n");
            if (count($parameters)) {
                fwrite($fp, sprintf("        return sprintf(\"%s\", %s);\n", empty($pathTemplate)?'/':$pathTemplate , implode(", ", $parameters)));
            } else {
                fwrite($fp, sprintf("        return \"%s\";\n", empty($pathTemplate)?'/':$pathTemplate));
            }
            fwrite($fp, "    }\n");
            fwrite($fp, "\n");

        }
    }

    // This pre1/2 post1/2 is necessary garbage for testing.
    // We cannot re-use a classname in one instance of PHP.
    // Thus after we generate a class once, subsequent classes
    // should be annonymous. 
    static private $preOne = 'class CompiledWheatRouter';
    static private $preTwo = 'return new class';

    static private $postOne = 'return new CompiledWheatRouter;';
    static private $postTwo = '';

    /**
     * @param string $file
     * @return void
     */
    public function outputCode (string $file)
    {
        $this->readBlocks($this->root->documentElement);

        $this->syntaxTree = [];
        array_push($this->syntaxTree, ...$this->controlParse($this->root->documentElement, $this->regexTree));

        $fp = fopen($file, 'w');
        if ($fp === false) {
            throw new \Exception('Could not open Wheat\\Router cache file for writting');
        }

        $pre = self::$preOne;
        fwrite($fp, "<?php /* Code automatically generated by Wheat\Router. Do not edit. */\n");
        fwrite($fp, "$pre implements \Wheat\Router\RouterInterface {\n");
        fwrite($fp, "    private \$url, \$serverRequest, \$globals;\n");
        self::$preOne = self::$preTwo;
        $this->writePathFunctions($fp);

        $lines = file(__FILE__);
        $reflectionClass = new \ReflectionClass($this);
        foreach ([] as $methodName) {
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


        
        fwrite($fp, "    public function route(array \$request): array\n");
        fwrite($fp, "    {\n");
        fwrite($fp, "        \$this->serverRequest = \$request;\n");
        fwrite($fp, "        \$this->globals = [];\n");
        fwrite($fp, "        \$this->serverRequest['CURRENT_URL'] = sprintf('%s://%s%s', \$request['HTTP_SCHEME']??'', \$request['HTTP_HOST']??'', \$request['REQUEST_URI']??'');\n");
        fwrite($fp, "        \$this->serverRequest['CURRENT_URL_ENCODED'] = \\rawurlencode(\$this->serverRequest['CURRENT_URL']);\n");
        fwrite($fp, "        \$this->url = parse_url(\$request['REQUEST_URI'] ?? \$request['PATH_INFO'] ?? '');\n");
        fwrite($fp, "        // si = segment index\n");
        fwrite($fp, "        \$si = 0;\n");
        fwrite($fp, "        \$segments = [];\n");
        fwrite($fp, "        foreach (explode('/', \$this->url['path']) as \$p)\n");
        fwrite($fp, "            if (strlen(\$p)) \$segments[] = \$p;\n");
        fwrite($fp, "        \$segments = \SplFixedArray::fromArray(\n");
        fwrite($fp, "            \$segments,\n");
        fwrite($fp, "            false\n");
        // fwrite($fp, "            explode('/', substr(\$this->url['path'], 1))\n");
        fwrite($fp, "        );\n");
        fwrite($fp, "        \$numSegments = \$segments->getSize();\n");

        $output($this->syntaxTree, 2);


        fwrite($fp, "        return [\n");
        fwrite($fp, "            'code' => '404'\n");
        fwrite($fp, "        ];\n");
        fwrite($fp, "    }\n");
        fwrite($fp, "};");
        $post = self::$postOne;
        fwrite($fp, "$post");
        self::$postOne = self::$postTwo;
    }

    public function outputTesterFile(array $includes, array $settings)
    {
        $fp = fopen($settings['cacheFile'][1], 'w');
        if ($fp === false) {
            throw new \Exception('Could not open Wheat\\Router tester file for writting');
        }
        $now = \time();
        fwrite($fp, "<?php /* Code automatically generated by Wheat\Router. Do not edit. */\n");
        // fwrite($fp, "if (\\filemtime('{$settings['cacheFile'][0]}') > $now) {\n");
        // fwrite($fp, "    return true;\n");
        // fwrite($fp, "}\n");
        foreach ($includes as $include) {
            fwrite($fp, "if (\\filemtime('$include') > $now) {\n");
            fwrite($fp, "    return true;\n");
            fwrite($fp, "}\n");
        }
        fwrite($fp, "return false;\n");

    }
}