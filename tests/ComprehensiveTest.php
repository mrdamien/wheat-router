<?php
namespace Wheat;

use Wheat\Router;
use Wheat\Router\Config;

class ComprehensiveTest extends \PHPUnit\Framework\TestCase
{

    public static function tearDownAfterClass(){
        // @unlink(__DIR__.'/comprehensive.php');
    }

    public function comprehensiveProvider ()
    {
        return [
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
            ],
            [
                'GET',
                1,
                'https://test.com/geo_12.345_67.89',
                [
                    'code' => '200',
                    'lat' => '12.345',
                    'long' => '67.89'
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

}
