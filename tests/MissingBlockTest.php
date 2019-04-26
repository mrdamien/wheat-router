<?php
namespace Wheat;

use Wheat\Router;

class MissingBlockTest extends \PHPUnit\Framework\TestCase
{
    public static function tearDownAfterClass(){
        @unlink(__DIR__.'/missingblock.php');
    }

    public function testConflict ()
    {
        $this->expectException(\Exception::class);

        $router = \Wheat\Router::make([
            'configFile' => __DIR__.'/missing_block.xml',
            'cacheFile' => [__DIR__.'/missingblock.php', __DIR__.'/tester.php'],
            'regenCache' => true,
        ]);
    }

}
