<?php
include '../vendor/autoload.php';
/** @var \CompiledWheatRouter $router */
$router = \Wheat\Router::make([
    'configFile' => __DIR__.'/../tests/benchmark.xml',
]);

function prepare_title (string $title) {
    $title = preg_replace('/[^a-z_]/i', ' ', $title);
    $title = str_replace(" ", "_", $title);
    $title = str_replace("__", "_", $title);
    return strtolower(
        substr($title, 0, 44)
    );
}

function assertEquals ($expected, $test, $msg = '') {
    if ($expected !== $test) {
        echo "\n", $msg, "\n!!! Failed asserting that: ", var_export($expected, 1), " = ", var_export($test, true);

    }
}

$n = $argv[1] ?? 10000;

$start = microtime(true);
for ($i=0; $i<$n; $i++) {
    $result = $router->route([
        'PATH_INFO' => '',
        'HTTP_HOST' => 'reddit.com',
        'REQUEST_SCHEME' => 'https'
    ]);
}
$end = microtime(true);
assertEquals(['controller' => 'ViewRedditHomepage', 'format' => '', 'code'=>'200'], $result);
$homepage = $end - $start;


$start = microtime(true);
for ($i=0; $i<$n; $i++) {
    $result = $router->route([
        'PATH_INFO' => '/message/compose',
        'HTTP_HOST' => 'reddit.com',
        'REQUEST_SCHEME' => 'https'
    ]);
}
$end = microtime(true);
assertEquals(['controller'=>'MessageCompose', 'format' => '', 'code'=>'200'], $result);
$lastRedditRoute = $end - $start;


$start = microtime(true);
for ($i=0; $i<$n; $i++) {
    $result = $router->route([
        'PATH_INFO' => '/user/dude_guy/comments',
        'HTTP_HOST' => 'reddit.com',
        'REQUEST_SCHEME' => 'https'
    ]);
}
$end = microtime(true);
assertEquals(['username' => 'dude_guy', 'controller' => 'ViewUserComments', 'format' => '', 'code'=>'200'], $result);
$user = $end - $start;

// shows that trailing slash is no different
$result = $router->route([
    'PATH_INFO' => '/user/dude_guy/comments/',
    'HTTP_HOST' => 'reddit.com',
    'REQUEST_SCHEME' => 'https'
]);
assertEquals(['username' => 'dude_guy', 'controller' => 'ViewUserComments', 'format' => '', 'code'=>'200'], $result);

$start = microtime(true);
for ($i=0; $i<$n; $i++) {
    $result = $router->route([
        'PATH_INFO' => '/r/pics/asb987/this_is_the_title_part/',
        'HTTP_HOST' => 'reddit.com',
        'REQUEST_SCHEME' => 'https'
    ]);
}
$end = microtime(true);
assertEquals(['subreddit' => 'pics', 'controller' => 'ViewSubreddit', 'sort' => 'hot', 'format' => '', 'code'=>'200'], $result);
$post = $end - $start;


$start = microtime(true);
for ($i=0; $i<$n; $i++) {
    $result = $router->route([
        'PATH_INFO' => '/this_path_shouldnt_exist',
        'HTTP_HOST' => 'reddit.com',
        'REQUEST_SCHEME' => 'https'
    ]);
}
$end = microtime(true);
assertEquals(['code'=>'404'], $result);
$missing = $end - $start;


$start = microtime(true);
for ($i=0; $i<$n; $i++) {
    $result = $router->route([
        'PATH_INFO' => '/j/k',
        'HTTP_HOST' => 'reddit.com',
        'REQUEST_SCHEME' => 'https'
    ]);
}
$end = microtime(true);
assertEquals(['letters' => 'jk', 'code'=>'200'], $result);
$midChars = $end - $start;


$start = microtime(true);
for ($i=0; $i<$n; $i++) {
    $result = $router->route([
        'PATH_INFO' => '/j_0/k_9',
        'HTTP_HOST' => 'reddit.com',
        'REQUEST_SCHEME' => 'https'
    ]);
}
$end = microtime(true);
assertEquals(['letters' => 'jk', 'a' => '0', 'b' => '9', 'code'=>'200'], $result);
$midCharsNum = $end - $start;


$start = microtime(true);
for ($i=0; $i<$n; $i++) {
    $result = $router->route([
        'PATH_INFO' => '/r/subreddit/.rss',
        'HTTP_HOST' => 'reddit.com',
        'REQUEST_SCHEME' => 'https'
    ]);
}
$end = microtime(true);
assertEquals(['subreddit' => 'subreddit', 'controller' => 'ViewSubreddit', 'sort' => 'hot', 'format' => 'rss', 'code'=>'200'], $result);
$rss = $end - $start;


$times = [$homepage, $lastRedditRoute, $user, $post, $missing, $midChars, $midCharsNum, $rss];
$total = array_sum($times);
$count = $n * count($times);
$times[] = $count;
$times[] = $total;
echo sprintf(<<<TXT
n:          %d
Homepage:   %f s
lastReddit: %f s
User:       %f s
Post:       %f s
404:        %f s
/j/k        %f s
/j_0/k_9    %f s
rss:        %f s
Total:      %d reqs in %f s

TXT
, $n, ...$times);