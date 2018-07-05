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

namespace Wheat;

use Wheat\Router\Config;
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

class Router
{
    private $router;

    /**
     * @param Config $c
     */
    public function __construct (Config $c)
    {
        $this->config = $c;

        if (
            $this->config->settings['regenCache'] || 
            !\file_exists($this->config->settings['cacheFile'])
        ) {
            $this->generateCode();
        }

        $this->router = require $this->config->settings['cacheFile'];
    }

    /**
     * @param string $uri
     * @return array
     */
    public function route (string $uri): array
    {
        $code = $this->router->__invoke($uri);

        return [
            'httpCode' => $code,
            'location' => $this->router->location,
            'render' => $this->router->render,
        ];
    }

    private function generateCode ()
    {
        $reader = new \DOMDocument();

        if (!$reader->load($this->config->settings['configFile'])) {
            throw new \Exception("Cannot find configFile");
        }
        
        \libxml_clear_errors();
        $prev = libxml_use_internal_errors(true);
        $error = set_error_handler(function(){});
        $restore = function() use ($prev) {
            $errors = libxml_get_errors();
            \libxml_clear_errors();
            libxml_use_internal_errors($prev);
            restore_error_handler();
            return (array)$errors;
        };

        if (!$reader->relaxNGValidate(__DIR__.'/Router/schema.xml')) {
            $errors = $restore();
            $errors = array_reduce($errors, function($carry, $item){
                return $carry . "\n" . sprintf(
                    "%s[%d,%d] %s - Level: %d, Code: ",
                    $item->file,
                    $item->line,
                    $item->column,
                    trim($item->message),
                    $item->level,
                    $item->code
                );
            }, '');
            throw new \Exception("configFile is not valid".$errors);
        }
        $restore();

        $configParser = new \Wheat\Router\Parser($reader);
        $configParser->outputCode($this->config->settings['cacheFile']);
    }
}