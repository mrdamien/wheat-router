<?php
namespace Wheat;

use Wheat\Router;
use Wheat\Router\Config;

class RouterTest extends \PHPUnit\Framework\TestCase
{

    public function basicProvider ()
    {
        return [
            [
                '/team/john',
                [
                    'httpCode' => 200,
                    'location' => false,
                    'render' => 'john.php'
                ]
            ],
            [
                '/team/jane',
                [
                    'httpCode' => 200,
                    'location' => false,
                    'render' => 'jane.php'
                ]
                ],
            [
                'http://example.com/team/jane',
                [
                    'httpCode' => 200,
                    'location' => false,
                    'render' => 'jane.php'
                ]
            ],
            [
                'http://example.com/team',
                [
                    'httpCode' => 200,
                    'location' => false,
                    'render' => 'roster.php'
                ]
            ],
            [
                'http://example.com/foobar',
                [
                    'httpCode' => 404,
                    'location' => false,
                    'render' => false
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
        $cfg = new \Wheat\Router\Config([
            'configFile' => __DIR__ . '/basic.xml',
            'cacheFile' => __DIR__.'/basic.php',
            'regenCache' => true
        ]);
        $router = new \Wheat\Router($cfg);

        $route = $router->route($path);
        $this->assertEquals(
            $expect,
            $route
        );
    }

    public function regexProvider ()
    {
        return [
            [
                '/team/john',
                [
                    'httpCode' => 200,
                    'location' => false,
                    'render' => 'john.php'
                ]
            ],
            [
                '/team/jane',
                [
                    'httpCode' => 200,
                    'location' => false,
                    'render' => 'jane.php'
                ]
                ],
            [
                'http://example.com/team/jane',
                [
                    'httpCode' => 200,
                    'location' => false,
                    'render' => 'jane.php'
                ]
            ],
            [
                'http://example.com/team',
                [
                    'httpCode' => 200,
                    'location' => false,
                    'render' => 'roster.php'
                ]
            ],
            [
                'http://example.com/foobar',
                [
                    'httpCode' => 404,
                    'location' => false,
                    'render' => false
                ]
            ]

        ];

    }
    
    /**
     * @dataProvider basicProvider
     *
     * @return void
     */
    public function testRegexRouter ($path, $expect)
    {
        $cfg = new \Wheat\Router\Config([
            'configFile' => __DIR__ . '/regex.xml',
            'cacheFile' => __DIR__.'/regex.php',
            'regenCache' => true
        ]);
        $router = new \Wheat\Router($cfg);

        $route = $router->route($path);
        $this->assertEquals(
            $expect,
            $route
        );
    }

    public function comprehensiveProvider ()
    {
        return [
            [
                'GET',
                2,
                'https://example.com/book/1',
                [
                    'httpCode' => 200,
                    'location' => false,
                    'render' => 'page/1.html'
                ]
            ],
            [
                'GET',
                2,
                'https://old-domain.com/book/1',
                [
                    'httpCode' => 302,
                    'location' => 'https://example.com',
                    'render' => false
                ]
            ],
            [
                'GET',
                2,
                'https://api.example.com/book/1',
                [
                    'httpCode' => 200,
                    'location' => false,
                    'render' => 'book.php'
                ]
            ],
            [
                'POST',
                2,
                'https://api.example.com/book/1',
                [
                    'httpCode' => 200,
                    'location' => false,
                    'render' => false
                ]
            ],
            [
                'POST',
                1,
                'https://api.example.com/book/1',
                [
                    'httpCode' => 301,
                    'location' => false,
                    'render' => 'upgrade.php'
                ]
            ]
        ];
    }

    /**
     * @dataProvider comprehensiveProvider
     *
     * @return void
     */
    public function testComprehensiveRouter ($method, $apiVer, $path, $expect)
    {
        $_SERVER['HTTP_X_API_VERSION'] = $apiVer;
        $_SERVER['REQUEST_METHOD'] = $method;
        $cfg = new \Wheat\Router\Config([
            'configFile' => __DIR__ . '/comprehensive.xml',
            'cacheFile' => __DIR__.'/comprehensive.php',
            'regenCache' => true
        ]);
        $router = new \Wheat\Router($cfg);

        $route = $router->route($path);
        $this->assertEquals(
            $expect,
            $route
        );
    }

    public static function tearDownAfterClass(){
        @unlink(__DIR__.'/basic.php');
        @unlink(__DIR__.'/regex.php');
        @unlink(__DIR__.'/comprehensive.php');
    }

    /**
     * @return void
     */
    public function testInterpretations ()
    {
        $cfg = new \Wheat\Router\Config([
            'configFile' => __DIR__ . '/comprehensive.xml',
            'cacheFile' => __DIR__.'/comprehensive.php',
            'regenCache' => true
        ]);
        $router = new \Wheat\Router($cfg);

        $route = $router->route('https://redirect-please.com/path1/2?query=string#id');
        $this->assertEquals(
            [
                'httpCode' => 302,
                'location' => 'https://redirect-please.com/path1/2?query=string#id',
                'render' => false
            ],
            $route
        );
    }
}