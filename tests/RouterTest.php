<?php
namespace Wheat;

use Wheat\Router;
use Wheat\Router\Config;

class RouterTest extends \PHPUnit\Framework\TestCase
{
    public static function tearDownAfterClass(){
        @unlink(__DIR__.'/basic.php');
    }

    public function basicProvider ()
    {
        return [
            [
                '/team/john',
                [
                    'code' => '301',
                    'location' => 'team/1',
                ]
            ],
            [
                '/team/jane',
                [
                    'code' => '200',
                    'file' => 'jane.php'
                ]
                ],
            [
                '/team/jane',
                [
                    'code' => '200',
                    'file' => 'jane.php'
                ]
            ],
            [
                '/team',
                [
                    'code' => '200',
                    'location' => 'roster.php'
                ]
            ],
            [
                '/foobar',
                [
                    'code' => '404'
                ]
            ],
            [
                '/lost/1',
                [
                    'location' => '/team/1',
                    'code' => '302',
                ]
            ]

        ];

    }

    /**
     * @dataProvider basicProvider
     *
     * @return void
     */
    public function testRouter ($path, $expect)
    {
        $router = \Wheat\Router::make([
            'configFile' => __DIR__ . '/basic.xml',
            'cacheFile' => [__DIR__.'/basic.php', __DIR__.'/tester.php'],
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
        $this->assertEquals('/team/john', $router->urljohn('john'));
    }
    

}
