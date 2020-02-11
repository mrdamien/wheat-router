<?php
namespace Wheat;

function barbar () {
    return "barbar";
}

use Wheat\Router;
use Wheat\Router\Config;

class VariableTest extends \PHPUnit\Framework\TestCase
{
    public static function tearDownAfterClass(){
        @unlink(__DIR__.'/var.php');
    }

    /**
     */
    public function testRouter ()
    {
        $router = \Wheat\Router::make([
            'configFile' => __DIR__ . '/var.xml',
            'cacheFile' => [__DIR__.'/var.php', __DIR__.'/tester.php'],
            'regenCache' => true,
        ]);

        $route = $router->route([
            'HTTP_METHOD' => 'GET', 
            'REQUEST_URI' => '/dude'
        ]);

        $this->assertEquals(['code' => '200', 'foo' => 'barbar'], $route);
    }
    

}
