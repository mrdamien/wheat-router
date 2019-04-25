<?php
namespace Wheat;

use Wheat\Router;

class ErrorsTest extends \PHPUnit\Framework\TestCase
{
    public static function tearDownAfterClass(){
        @unlink(__DIR__.'/dne.php');
        @unlink(__DIR__.'/basic.php');
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
}
