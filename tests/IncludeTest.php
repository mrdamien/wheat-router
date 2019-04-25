<?php
namespace Wheat;

use Wheat\Router;

class IncludeTest extends \PHPUnit\Framework\TestCase
{
    public static function tearDownAfterClass(){
        @unlink(__DIR__.'/include.php');
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
                'post_id' => '1'
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
