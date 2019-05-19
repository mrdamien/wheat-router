<?php
namespace Wheat;

use Wheat\Router;
use Wheat\Router\Config;

class AdrTest extends \PHPUnit\Framework\TestCase
{


    // public static function setUpBeforeClass(){
    //     @unlink(__DIR__.'/basic.php');
    //     @unlink(__DIR__.'/regex.php');
    //     @unlink(__DIR__.'/comprehensive.php');
    //     @unlink(__DIR__.'/include.php');
    //     @unlink(__DIR__.'/tester.php');
    // }

    public static function tearDownAfterClass(){
        // @unlink(__DIR__.'/adr.php');
        @unlink(__DIR__.'/tester.php');
    }

    public function testAdr ()
    {
        $router = \Wheat\Router::make([
            'configFile' => __DIR__.'/adr.xml',
            'cacheFile'  => [__DIR__.'/adr.php', __DIR__.'/tester.php'],
            'regenCache' => true,
        ]);

        $result = $router->route([
            'HTTP_METHOD' => 'POST', 
            'REQUEST_URI' => '/photo/1'
        ]);
        $this->assertEquals(
            [
                'code' => '200',
                'action' => 'App\Http\PostPhoto',
                0 => '1'
            ],
            $result
        );

        $result = $router->route([
            'HTTP_METHOD' => 'GET', 
            'REQUEST_URI' => '/photo/'
        ]);
        $this->assertEquals(
            [
                'code' => '200',
                'action' => 'App\Http\Photo\GetPhoto',
            ],
            $result
        );

        $result = $router->route([
            'HTTP_METHOD' => 'GET', 
            'REQUEST_URI' => '/photos/edit/2'
        ]);
        $this->assertEquals(
            [
                'code' => '200',
                'action' => 'App\Http\Photos\Edit\GetPhotosEdit',
                2
            ],
            $result
        );

        $result = $router->route([
            'HTTP_METHOD' => 'GET', 
            'REQUEST_URI' => '/assets/foo/bar/cat.jpg'
        ]);
        $this->assertEquals(
            [
                'code' => '200',
                'remainder' => '/foo/bar/cat.jpg'
            ],
            $result
        );

        
    }
}
