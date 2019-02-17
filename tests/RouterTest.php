<?php
namespace Wheat;

use Wheat\Router;
use Wheat\Router\Config;

class RouterTest extends \PHPUnit\Framework\TestCase
{


    // public static function setUpBeforeClass(){
    //     @unlink(__DIR__.'/basic.php');
    //     @unlink(__DIR__.'/regex.php');
    //     @unlink(__DIR__.'/comprehensive.php');
    //     @unlink(__DIR__.'/include.php');
    //     @unlink(__DIR__.'/tester.php');
    // }

    public static function tearDownAfterClass(){
        @unlink(__DIR__.'/basic.php');
        @unlink(__DIR__.'/regex.php');
        @unlink(__DIR__.'/comprehensive.php');
        @unlink(__DIR__.'/include.php');
        @unlink(__DIR__.'/tester.php');
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

    public function comprehensiveProvider ()
    {
        return [
            [
                'GET',
                2,
                'https://example.com/book/1',
                [
                    'file' => 'page/1.html',
                    'code' => '200',
                ]
            ],
            [
                'GET',
                2,
                'https://old-domain.com/book/1',
                [
                    'location' => 'https://example.com',
                    'code' => '302',
                ]
            ],
            [
                'GET',
                2,
                'https://api.example.com/book/1',
                [
                    'file' => 'book.php',
                    'code' => '200',
                ]
            ],
            [
                'POST',
                2,
                'https://api.example.com/book/1',
                [
                    'code' => '401',
                ]
            ],
            [
                'POST',
                1,
                'https://api.example.com/book/1',
                [
                    'file' => 'upgrade.php',
                    'code' => '301',
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
        $router = \Wheat\Router::make([
            'configFile' => __DIR__ . '/comprehensive.xml',
            'cacheFile' => [__DIR__.'/temp.php', __DIR__.'/tester.php'],
            'regenCache' => true,
        ]);
        $path = \parse_url($path);
        
        $route = $router->route([
            'HTTP_X_API_VERSION' => $apiVer,
            'HTTP_METHOD'        => $method,
            'REQUEST_URI'        => $path['path'],
            'PATH_INFO'          => $path['path'],
            'HTTP_HOST'          => $path['host'],
            'HTTP_SCHEME'        => $path['scheme']
        ]);
        $this->assertEquals(
            $expect,
            $route
        );
    }

    /**
     * @return void
     */
    public function testInterpretations ()
    {
        $router = \Wheat\Router::make([
            'configFile' => __DIR__ . '/comprehensive.xml',
            'cacheFile' => [__DIR__.'/comprehensive.php', __DIR__.'/tester.php'],
            'regenCache' => true,
        ]);

        $route = $router->route([
            'HTTP_METHOD' => 'GET',
            'REQUEST_SCHEME' => 'https',
            'HTTP_HOST' => 'redirect-please.com',
            'REQUEST_URI' => '/path1/2?query=string',
            'PATH_INFO' => '/path1/2',
            'QUERY_STRING' => 'query=string'
        ]);
        $this->assertEquals(
            [
                'scheme'=>'https',
                'path' => '/path1/2',
                'query' => 'query=string',
                'code' => '302',
                'location' => 'https://redirect-please.com/path1/2?query=string',
            ],
            $route
        );
    }

    public function testErrors1 ()
    {
        $this->expectException(\Exception::CLASS);
        $router = \Wheat\Router::make([
            'configFile' => __DIR__ . '/dne.xml',
            'cacheFile' => [__DIR__.'/dne.php', __DIR__.'/tester.php'],
            'regenCache' => true,
        ]);
    }

    public function testErrors2 ()
    {
        $this->expectException(\Exception::CLASS);
        $router = \Wheat\Router::make([
            'configFile' => __DIR__.'/invalid.xml',
            'cacheFile' => [__DIR__.'/dne.php', __DIR__.'/tester.php'],
            'regenCache' => true,
        ]);
    }

    public function testErrors3 ()
    {
        $this->expectException(\Exception::CLASS);
        $router = \Wheat\Router::make([
            'configFile' => __FILE__,
            'cacheFile' => [__DIR__.'/dne.php', __DIR__.'/tester.php'],
            'regenCache' => true,
        ]);
    }

    public function testErrors4 ()
    {
        $router = \Wheat\Router::make([
            'configFile' => __DIR__.'/basic.xml',
            'cacheFile' => [__DIR__.'/basic.php', __DIR__.'/tester.php'],
        ]);;

        touch(__DIR__.'/basic.xml', time()+5000);
        touch(__DIR__.'/basic.php', time()-5000);

        $router = \Wheat\Router::make([
            'configFile' => __DIR__.'/basic.xml',
            'cacheFile' => [__DIR__.'/basic.php', __DIR__.'/tester.php'],
            'regenCache' => true
        ]);
        $this->assertTrue(true);
    }

    public function testConflict ()
    {
        $this->expectException(\Exception::class);

        $router = \Wheat\Router::make([
            'configFile' => __DIR__.'/conflict.xml',
            'cacheFile' => [__DIR__.'/include.php', __DIR__.'/tester.php'],
            'regenCache' => true,
        ]);

    }

    public function testNoConflict ()
    {
        $this->expectException(\Exception::class);

        $router = \Wheat\Router::make([
            'configFile' => __DIR__.'/no_conflict.xml',
            'cacheFile' => [__DIR__.'/include.php', __DIR__.'/tester.php'],
            'regenCache' => true,
        ]);

    }

    public function testIncludes ()
    {
        $router = \Wheat\Router::make([
            'configFile' => __DIR__.'/include.xml',
            'cacheFile'  => [__DIR__.'/include.php', __DIR__.'/tester.php'],
            'regenCache' => true,
        ]);

        $result = $router->route([
            'HTTP_METHOD' => 'GET', 
            'REQUEST_URI' => '/post/1'
        ]);
        $this->assertEquals(
            [
                'code' => '200',
            ],
            $result
        );

        $result = $router->route([
            'HTTP_METHOD' => 'GET', 
            'REQUEST_URI' => '/nope'
        ]);
        $this->assertEquals(
            [
                'code' => '404',
            ],
            $result
        );
    }
}
