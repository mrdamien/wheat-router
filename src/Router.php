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

class Router
{

    const XINCLUDENS = 'http://www.w3.org/2001/XInclude';

    private $router;

    /** @var string[] $settings  */
    public $settings;

    /**
     * @param string[] $c
     */
    static public function make (array $settings)
    {
        $settings = [
            'configFile' => $settings['configFile'] ?? 'router.xml',
            'cacheFile' => $settings['cacheFile'] ?? 'wheat.router.cache.php',
            'regenCache' => $settings['regenCache'] ?? null,
            'renderCommand' => $settings['renderCommand'] ?? 'Wheat\Router::render',
        ];
        
        if (!\file_exists($settings['cacheFile'])) {
            self::generateCode($settings);
        }

        $router = require $settings['cacheFile'];
        if ($settings['regenCache'] || $router->needsRegeneration($settings['configFile'])) {
            self::generateCode($settings);
            $router = require $settings['cacheFile'];
        }
        return $router;
    }

    /**
     * Gets a list of files that are included (via xinclude)
     */
    static private function getIncludes (string $root, \DOMDocument $xml): array
    {
        $includes = [];
        $includeElements = $xml->getElementsByTagNameNS(self::XINCLUDENS, 'include');

        foreach ($includeElements as $element) {
            $include = $root.'/'.$element->attributes->getNamedItem('href')->nodeValue;
            $includes[] = $include;
            $doc = new \DOMDocument();
            $doc->load($include);
            $ret = self::getIncludes(dirname($include), $doc);
            if (count($ret)) {array_push($includes, ...$ret);}
        }

        return $includes;
    }

    static private function generateCode (array $settings)
    {
        $reader = new \DOMDocument();
        $reader->preserveWhiteSpace = false;
        $reader->formatOutput = true;

        \libxml_clear_errors();
        $prev = libxml_use_internal_errors(true);
        $error = set_error_handler(function($err){return $err;});
        $restore = function() use ($prev) {
            $errors = libxml_get_errors();
            \libxml_clear_errors();
            libxml_use_internal_errors($prev);
            restore_error_handler();
            return (array)$errors;
        };

        if (!file_exists($settings['configFile'])) {
            throw new \Exception("Cannot find configFile");
        }

        if (!@$reader->load($settings['configFile'])) {
            throw new \Exception("Config XML is not valid. ".$settings['configFile'] . var_export($restore, true));
        }

        $filePath = dirname($settings['configFile']);
        $includes = self::getIncludes($filePath, $reader);
        array_unshift($includes, $settings['configFile']);
        $reader->xinclude();


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

        $configParser = new \Wheat\Router\Parser($reader, $includes);
        $configParser->outputCode($settings['cacheFile']);
    }
}