<?php
namespace Wheat;

use Wheat\Router;
use Wheat\Router\Config;

class RegexTest extends \PHPUnit\Framework\TestCase
{
    public static function tearDownAfterClass(){
        @unlink(__DIR__.'/regex.php');
    }

    public function regexProvider ()
    {
        return [
            [
                '/team/john',
                [
                    'code' => '200',
                    'file' => 'john.php',
                ]
            ],
            [
                '/team/jane',
                [
                    'code' => '200',
                    'file' => 'jane.php',
                ]
            ],
            [
                '/team/',
                [
                    'code' => '200',
                    'file' => 'roster.php',
                ]
            ],
            [
                '/team',
                [
                    'code' => '200',
                    'file' => 'roster.php',
                ]
            ],

        ];

    }
    /**
     * @dataProvider regexProvider
     *
     * @return void
     */
    public function testRegexRouter ($path, $expect)
    {
        $router = \Wheat\Router::make([
            'configFile' => __DIR__ . '/regex.xml',
            'cacheFile' => [__DIR__.'/regex.php', __DIR__.'/tester.php'],
            'regenCache' => true,
        ]);

        $route = $router->route([
            'HTTP_METHOD' => 'GET', 
            'REQUEST_URI' => $path
        ]);
        $this->assertEquals(
            $expect,
            $route
        );
    }



}
