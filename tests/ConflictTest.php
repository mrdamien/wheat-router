<?php
namespace Wheat;

use Wheat\Router;

class ConflictTest extends \PHPUnit\Framework\TestCase
{
    public static function tearDownAfterClass(){
        @unlink(__DIR__.'/include.php');
    }

    public function testConflict ()
    {
        $this->expectException(\Exception::class);

        $router = \Wheat\Router::make([
            'configFile' => __DIR__.'/conflict.xml',
            'cacheFile' => [__DIR__.'/include.php', __DIR__.'/tester.php'],
            'regenCache' => true,
        ]);
        exit;
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
}
