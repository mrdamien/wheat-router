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

use Wheat\Router\Element\Router;
use Wheat\Router\Element\Value;
use Wheat\Router\Element\SwitchElement;
use Wheat\Router\Element\CaseElement;
use Wheat\Router\Element\ReturnElement;
use Wheat\Router\Element\TestElement;
use Wheat\Router\Element\Pattern;
use Wheat\Router\Element\Path;
use Wheat\Router\Element\Set;
use Wheat\Router\Element\NameTrait;
use Wheat\Router\Element\Call;
use Wheat\Router\Element\Arg;
use Wheat\Router\Element\Block;
use Wheat\Router\Element\DefaultElement;
use Wheat\Router\Element\RegexPath;
use Wheat\Router\Element\BlankPath;

class Parser
{
    const TOKEN_SCOPE_START = 0;
    const TOKEN_SCOPE_END = 1;


    const FLOAT_REGEX = '[-+Ee.0-9]*';
    const INT_REGEX = '\d+';

    private $root;
    private $addMethods = [];
    private $sprintfs = [];

    const FN_NAME_REGEX = '/^(\\\?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)+$/';

    public function __construct(\DOMNode $root)
    {
        $this->root = $root;
    }

    private static $id = 0;
    public static function makeId ()
    {
        self::$id++;
        return sprintf('z_%d', self::$id);
    }

    public static function parsePatternString (string $s): array
    {
        $pattern = [
            'names' => [],
            'types' => [],
            'functions' => [],
            'sprintf' => '',
            'regex' => '',
            'blank' => []
        ];

        \preg_match_all('/{\w+(:[^}]+)?}/', $s, $matches);
        \preg_match_all('/\[\w+(:[^\]]+)?\]/', $s, $optionals);

        $sprintf = $s;
        $regex = $s;

        foreach ($matches[0] as $argument) {
            $originalArg = $argument;
            $sprintf = \str_replace($originalArg, '%s', $sprintf);
            $argument = \trim($argument, '{}');
            $argumentParts = \explode(':', $argument);

            $functions = [];
            $pattern['names'][] = array_shift($argumentParts);
            
            $type = "string";
            $r = null;

            foreach ($argumentParts as $i=>$part) {
                switch ($part) {
                    case 'int':
                        $r = self::INT_REGEX;
                        $type = "int";
                        break;
                    case 'float':
                        $r = self::FLOAT_REGEX;
                        $type = "float";
                        break;
                }

                if (\preg_match(self::FN_NAME_REGEX, $part)) {
                    $functions[] = $part;
                } else {
                    $r = $part;
                }
            }

            if ($r === null) $r = '.+';

            // $regex = str_replace($matches[0][0], '(?<'.$name.'>'.$r.')', $regex);
            $regex = str_replace($originalArg, '('.$r.')', $regex);

            $pattern['types'][] = $type;
            $pattern['functions'][] = $functions;
        }

        foreach ($optionals[0] as $argument) {
            $originalArg = $argument;
            $sprintf = \str_replace($originalArg, '%s', $sprintf);
            $argument = \trim($argument, '[]');
            $argumentParts = \explode(':', $argument);

            $functions = [];
            $pattern['names'][] = $name = array_shift($argumentParts);
            $pattern['blank'][$name] = true;
            
            $type = "string";
            $r = null;

            foreach ($argumentParts as $i=>$part) {
                switch ($part) {
                    case 'int':
                        $r = self::INT_REGEX;
                        $type = "int";
                        break;
                    case 'float':
                        $r = self::FLOAT_REGEX;
                        $type = "float";
                        break;
                }

                if (\preg_match(self::FN_NAME_REGEX, $part)) {
                    $functions[] = $part;
                } else {
                    $r = $part;
                }
            }

            if ($r === null) $r = '.+';
            
            $regex = str_replace($originalArg, '('.$r.')?', $regex);

            $pattern['types'][] = $type;
            $pattern['functions'][] = $functions;
        }

        $pattern['sprintf'] = $sprintf;
        $pattern['regex'] = '^'.$regex.'$';

        return $pattern;
    }


    private function controlParse (\DOMElement $node, ?Element $router = null)
    {
        if ($router === null) {
            $router = new Router;
        }

        /** @var \DOMElement $child */
        foreach ($node->childNodes as $child) {

            switch ($child->nodeName) {
                case 'router':
                    $this->controlParse($child, $router);
                    break;
                
                case 'block':
                    $block = new Block;
                    $block->setName((string)$child->attributes->getNamedItem('name')->value);
                    $this->controlParse($child, $block);
                    $router->getRouter()->appendBlock($block);
                    break;
                
                case 'ref':
                    $block = $router->getRouter()->getBlock((string)$child->attributes->getNamedItem('name')->value);
                    $router->append($block);
                    break;

                case 'set':
                    foreach ($child->attributes as $attr) {
                        $router->setVar((string)$attr->localName, (string)$attr->nodeValue);
                    }
                    break;

                case 'arg':
                    $arg = new Arg;
                    $arg->setValue((string)$child->attributes->getNamedItem('value')->value);
                    $router->append($arg);
                    break;
                
                case 'return':
                    $ret  = new ReturnElement;
                    $ret->setValue('code', '200');
                    foreach ($child->attributes as $attr) {
                        $name = (string)$attr->name;
                        $value = $child->attributes->getNamedItem($name)->value;
                        $ret->setValue($name, $value);
                    }
                    $router->append($ret);
                    $this->controlParse($child, $ret);
                    break;
                
                case 'switch':
                    $switch = new SwitchElement;
                    $router->append($switch);
                    $this->controlParse($child, $switch);
                    break;

                case 'value':
                    $val = new Value;
                    $val->setValue((string)$child->textContent);
                    $router->append($val);
                    $this->controlParse($child, $val);
                    break;

                case 'case':
                    $case = new CaseElement;
                    $case->setValue((string)$child->attributes->getNamedItem('value')->value);
                    $router->append($case);
                    $this->controlParse($child, $case);
                    break;

                case 'default':
                    $default = new DefaultElement;
                    $router->append($default);
                    $this->controlParse($child, $default);
                    break;

                case 'call':
                    $call = new Call;
                    $call->setFunction((string)$child->attributes->getNamedItem('function')->value);
                    $router->append($call);
                    $this->controlParse($child, $call);
                    break;

                case 'test':
                    $test = new TestElement;
                    $test->setValue((string)$child->attributes->getNamedItem('subject')->value);
                    $test->setPattern((string)$child->attributes->getNamedItem('pattern')->value);
                    $router->append($test);
                    $this->controlParse($child, $test);
                    break;

                case 'path':
                    $id = $child->attributes->getNamedItem('id')
                        ? (string)$child->attributes->getNamedItem('id')->value
                        : '';
                    $pattern = $child->attributes->getNamedItem('pattern')
                        ? (string)$child->attributes->getNamedItem('pattern')->value
                        : '';

                    if ($pattern === '') {
                        $path = new BlankPath;
                    } else if (preg_match_all('/(\{.*\})|(\[.*\])/', $pattern)) {
                        $path = new RegexPath;
                    } else {
                        $path = new Path;
                    }

                    $path->setPattern($pattern);
                    $path->setName($id);

                    // check for conflicting routes
                    $tmp = $path->getPattern();
                    if ($path instanceof Path)
                        foreach ($router->children as $c) {
                            if ($c->getType() !== Element::TYPE_PATH) continue;
                            if ($c->getPattern() === $tmp)
                                throw new \InvalidArgumentException("There are two paths with string '$tmp'");
                        }

                    $router->append($path);
                    $this->controlParse($child, $path);


                    if ($id !== '') {
                        [$name, $format] = $path->getRoute();
                        $this->sprintfs[$name] = $format;
                        $this->addMethods[] = $path;
                    }
                    break;
            }
        }
        
        return $router;
    }


    // This pre1/2 post1/2 is necessary garbage for testing.
    // We cannot re-use a classname in one instance of PHP.
    // Thus after we generate a class once, subsequent classes
    // should be annonymous. 
    static private $preOne = 'class CompiledWheatRouter';
    static private $preTwo = 'return new class';

    static private $postOne = 'return new CompiledWheatRouter;';
    static private $postTwo = '';

    private function indent ($str, $indent)
    {
        $indent = str_repeat(' ', 4*$indent);
        return $indent.preg_replace('/\n\s*/', "\n$indent    ", $str). "\n";
    }

    /**
     * @param string $file
     * @return void
     */
    public function outputCode (string $file)
    {
        $syntax = $this->controlParse($this->root->documentElement);

        $fp = fopen($file, 'w');
        if ($fp === false) {
            throw new \Exception('Could not open Wheat\\Router cache file for writting');
        }

        $pre = self::$preOne;
        fwrite($fp, "<?php /* Code automatically generated by Wheat\Router. Do not edit. */\n");
        fwrite($fp, "$pre implements \Wheat\Router\RouterInterface {\n");
        fwrite($fp, "    private \$url, \$serverRequest;\n");
        self::$preOne = self::$preTwo;
        
        fwrite($fp, "    public \$routes = ".var_export($this->sprintfs, true).";\n");

        foreach ($syntax->sprintfs as $id=>$route) {
            fwrite($fp, "    const $id = \"$route\";\n");
        }

        foreach ($this->addMethods as $path) {
            fwrite($fp, $path->addMethod());
        }

        fwrite($fp, "\n");
        fwrite($fp, "    public function path_remaining (array \$segments, int \$offset): string\n");
        fwrite($fp, "    {\n");
        fwrite($fp, "        \$parts = \array_slice(\$segments, \$offset-1);\n");
        fwrite($fp, "        return count(\$parts) > 0\n");
        fwrite($fp, "            ? \implode('/', ['']+\$parts)\n");
        fwrite($fp, "            : '';\n");
        fwrite($fp, "    }\n");
        fwrite($fp, "\n");
        fwrite($fp, "    public function route (array \$request, ?array \$get = null): array\n");
        fwrite($fp, "    {\n");
        fwrite($fp, "        if (\$get === null) \$get = \$_GET;\n");
        fwrite($fp, "        \$this->serverRequest = \$request;\n");
        fwrite($fp, "        \$path = \$request['REQUEST_URI'] ?? \$request['PATH_INFO'] ?? '';\n");
        fwrite($fp, "        \$url = parse_url(\$path);\n");
        fwrite($fp, "        foreach (\$url as \$k=>\$v) \$this->serverRequest[\$k] = '\$v';\n");
        fwrite($fp, "        \$pathinfo = \pathinfo(\$url['path']);");
        fwrite($fp, "        \$this->serverRequest['extension'] = \$pathinfo['extension'] ?? '';\n");
        fwrite($fp, "        \$segments = [];\n");
        fwrite($fp, "        foreach (explode('/', \$url['path']) as \$p) if (strlen(\$p)) \$segments[] = \$p;\n");


        $code = $syntax->toCode();
        $indent = 2;
        $segment = 0;
        fwrite($fp, $this->indent('$segment = $segments['. $segment. "] ?? null;", $indent));
        fwrite($fp, $this->indent('$segment_offset = '. $segment. ";", $indent));
        $segment++;

        foreach ($code as $c) {
            switch ($c) {
                case Element::INDENT:
                    $indent++;
                    break;
                case Element::UNINDENT:
                    $indent--;
                    break;
            
                case Element::NEXT_SEGMENT:
                    fwrite($fp, $this->indent('$segment = $segments['. $segment. "] ?? null;", $indent));
                    fwrite($fp, $this->indent('$segment_offset = '. $segment. ";", $indent));
                    $segment++;
                    break;
        
                case Element::PREV_SEGMENT:
                    $segment--;
                    fwrite($fp, $this->indent('$segment = $segments['. $segment. "] ?? null;", $indent));
                    fwrite($fp, $this->indent('$segment_offset = '. $segment. ";", $indent));
                    break;
                    
                default:
                fwrite($fp, $this->indent($c, $indent));
            }
        }



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