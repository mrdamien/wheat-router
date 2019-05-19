- [Example](#Simple\ example)
- [Patterns](#Patterns)
- [Optional Patterns](#Optional\ Patterns)
- [String Interpretation](#String\ Interpretation)
- [Conditionals](#Conditionals)
- [Subroutines](#Subroutines)
- [Includes](#Includes)


# Wheat/Route - An XML/PHP metaprogramming router

Goals
* Flexibility
* Composability
* Speed

## Simple example:

router.xml
```xml 
<?xml version="1.0" encoding="UTF-8"?>
<router xmlns:xi="http://www.w3.org/2001/XInclude">
    <!--
    Path with empty pattern match the default route 
    ex: GET /
    -->
    <path pattern="">
        <return controller="HelloWorld" />
    </path>
    <path pattern="foobar">
        <return code="302" location="http://google.com" />
    </path>
    <path pattern="hello">
        <!-- Path with no pattern has no effect. -->
        <path>
            <!-- {name} is a regex defaulting to (.+) -->
            <path pattern="{name}">
                <return controller="Hello" name="{name}" />
            </path>
        </path>
    </path>
</router>
```

```php
function HelloWorld() {
    echo "Hello World!";
}
function Hello ($name) {
    echo "Hello $name!";
}
$router = \Wheat\Router::make([
    'configFile' => 'router.xml'
]);
$route = $router->route($_SERVER);

switch ($route['code']) {
    case '200':
        $route['controller']($route['name'] ?? '');
    break;
    case '302':
        header("Location: {$route['location']}");
    break;
    case '404':
        header("HTTP/1.0 404 Not Found");
        echo "Page not found :(";
    break;
}
```
```
GET /           -> "Hello world"
GET /hello/john -> "Hello john!"
GET /hello/     -> 404
GET /other      -> 404
GET /foobar     -> redirect
```

When a &lt;return /&gt; element is reached, the attributes are returned as key=>value pairs.
'code' will default to 200 unless otherwise specified.

## Patterns

Patterns are surrounded by curly braces.

- A name, which must come first: {foobar}
- A regex pattern like: \d+ If no pattern is specified, ".+" is used.
- Types can be 'int' or 'float' or 'string', or not specified (string). Int and float change the regex to match integers/floats respectively.

## Optional patterns

Optional Patterns are surrounded by square braces.

For example:

cat[ext:\.\w+] matches things like "cat.jpg" "cat.html" and "cat".

Capturing groups () cannot be used in either required nor optional patterns.

## String Interpretation

Strings are interpreted. `foo_{bar}` is equivalent to `foo_{$bar}` in PHP.

A series of functions that can be used to sanitize/format those variables.

/the-quick-brown-fox -> {title:strtoupper} -> THE-QUICK-BROWN-FOX. More on this later.

    {foobar:\d+:int:intval}
    Name: foobar
    Regex: \d+
    Type: int
    Functions: intval()
    
    {title:([a-z]+_?)+:string:\my_ns\to_url_slug}
    Name: title
    Regex: ([a-z]+_?)+
    Type: string
    Functions: \my_ns\to_url_slug()


## Conditionals
Do something if subject matches pattern, like enforce HTTPS
```xml
<test subject="{REQUEST_SCHEME}" pattern="/http$/">
    <return code="302" location="https://reddit.com" />
</test>
```

## Variables

Named patterns are assigned to variables. 
ex:

    `/john`    -> <path pattern="{name}">
    "{name}"   -> "john"

Variables can also be created manually:

```xml
<test subject="{PATH_INFO}" pattern="/\.json$/">
    <set format="json" />
</test>
<test subject="{PATH_INFO}" pattern="/\.xml$/">
    <set format="xml" />
</test>
<return controller="DoAThing" format="{format}" />
```

## Subroutines
Declare a set of instructions that can be included later.
```xml
<block name="requireLogin">
    <switch>
        <value><call function="User::isLoggedIn"></call></value>
        <case value="false">
            <return code="302" location="https://loginUrl"  />
        </case>
    </switch>
</block>
<path pattern="view">
    <path pattern="{id:\d+}">
        <return controller="ViewThing" id="{id}" />
        <path pattern="edit">
            <ref name="requireLogin" />
            <return controller="EditThing" id="{id}" />
        </path>
    </path>
</path>
<path pattern="submit">
    <ref name="requireLogin" />
    <return controller="SubmitThing" />
</path>
```


## Includes
Include external routers: main.xml
```xml
<?xml version="1.0" encoding="UTF-8"?>
<router xmlns:xi="http://www.w3.org/2001/XInclude">
    <path pattern="blog">
        <xi:include href="blog.xml" />
    </path>
    <path pattern="forum">
        <xi:include href="forum.xml" />
    </path>
</router>
```
blog.xml
```xml
<?xml version="1.0" encoding="UTF-8"?>
<router xmlns:xi="http://www.w3.org/2001/XInclude">
    <path pattern="">
        <return controller="BlogHomepage" />
        <path pattern="{post_id}">
            <return controller="ViewPost" post_id="{post_id}" />
        </path>
    </path>
</router>
```
forum.xml
```xml
<?xml version="1.0" encoding="UTF-8"?>
<router xmlns:xi="http://www.w3.org/2001/XInclude">
    <path pattern="">
        <return controller="ForumHomepage" />
        <path pattern="forum">
            <path pattern="{forum_id:\d+}">
                <return controller="ForumIndex" forum="{forum_id}" />
                <path pattern="{thread_id:\d+}" id="ViewThread">
                    <return controller="ViewThread" forum_id="{forum_id}" thread_id="{thread_id}" />
                </path>
            </path>
        </path>
    </path>
</router>
```
```
/         -> 404 (No <path pattern=""> here)
/about    -> 404
/blog     -> BlogHomepage
/blog/1   -> ViewPost
/forum    -> ForumHomepage
/forum/10 -> ForumIndex
```

## Routing-styles
Multiple styles of routing, or combinations thereof are possible.

Controller->Method Example:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<router xmlns:xi="http://www.w3.org/2001/XInclude">
    <path pattern="">
        <return controller="DefaultController::index" />
        <path pattern="{controller}">
            <return controller="{controller:ucfirst}::index" />
            <path pattern="{action}">
                <return  controller="{controller:ucfirst}::{action}" />
            </path>
        </path>
    </path>
</router>
```
```
/        -> DefaultController::index
/foo     -> Foo::index
/foo/bar -> Foo::bar
```

ADR example:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<router xmlns:xi="http://www.w3.org/2001/XInclude">
    <path pattern="{domain}">
        <path pattern="{action}">
            <return action="App\Http\{domain:strtolower:ucfirst}\{HTTP_METHOD:strtolower:ucfirst}{action:ucfirst}">
            </return>
            <path pattern="{id:\d+}">
                <return action="App\Http\{domain:strtolower:ucfirst}\{HTTP_METHOD:strtolower:ucfirst}{action:ucfirst}">
                    <arg id="{id}" />
                </return>
            </path>
        </path>
        <return action="App\Http\{domain:strtolower:ucfirst}\{HTTP_METHOD:strtolower:ucfirst}Index" />
    </path>
</router>
```
```
GET  /foo     -> App\Http\Foo\GetIndex
POST /foo/bar -> App\Http\Foo\PostBar
PUT  /cat/dog -> App\Http\Cat\PutDog
```




## URL Generation
To ease url generation, attach an id="UpperCamelCase" to a path. Ex:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<router xmlns:xi="http://www.w3.org/2001/XInclude">
    <path pattern="{one:\d+}" id="one">
        <path pattern="{two:\d+:int}" id="Two">
            <path pattern="{three:\d+:int:intval}" id="Three">
                ...
            </path>
            ...
        </path>
        ...
    </path>
</router>
```
The generated functions will have a signature to aid with autocomplete. Parameters can be typehinted, or passed through a function first. Ex: $two will be type-hinted as an int, $three will be passed through intval().
```php
public function urlOne ($forum_id): string;
public function urlTwo ($forum_id, int $two): string;
public function urlThree ($forum_id, int $two, $three): string;
```
## Speed
Speed is good. Example benchmark: 200k hits per second. xdebug off before benchmarking.

```php
<?php
include 'vendor/autoload.php';
$nMatches = 200000;

$router = \Wheat\Router::make([
    'configFile' => __DIR__ . '/tests/benchmark.xml',
    'regenCache' => true
]);

echo 'first:   ', (function() use ($router, $nMatches) {
    $start = microtime(true);
    for ($i=0; $i<$nMatches; $i++) {
        $router->route([
            'PATH_INFO' => '/a/foo'
        ]);
    }
    $end = microtime(true);

    return sprintf(
        'Time: %12fs'."\n",
        ($end - $start)
    );
})();

echo 'cv:      ', (function() use ($router, $nMatches) {
    $start = microtime(true);
    for ($i=0; $i<$nMatches; $i++) {
        $router->route([
            'PATH_INFO' => '/cv/foo'
        ]);
    }
    $end = microtime(true);

    return sprintf(
        'Time: %12fs'."\n",
        ($end - $start)
    );
})();

echo 'node:    ', (function() use ($router, $nMatches) {
    $start = microtime(true);
    for ($i=0; $i<$nMatches; $i++) {
        $router->route([
            'PATH_INFO' => '/node/1'
        ]);
    }
    $end = microtime(true);

    return sprintf(
        'Time: %12fs'."\n",
        ($end - $start)
    );
})();

echo 'unknown: ', (function() use ($router, $nMatches) {
    $start = microtime(true);
    for ($i=0; $i<$nMatches; $i++) {
        $router->route([
            'PATH_INFO' => '/foobar/foo'
        ]);
    }
    $end = microtime(true);

    return sprintf(
        'Time: %12fs'."\n",
        ($end - $start)
    );
})();
```

```
first:   Time:     0.906898s
cv:      Time:     0.852843s
node:    Time:     0.834878s
unknown: Time:     0.872268s
```

## Special variables

### {path_remainder}
Identifies the remaining non-matched path.

```
/foo/bar/dog/cat
<path>
    <!-- {path_remainder}: /foo/bar/dog/cat -->
    <path pattern="foo">
        <!-- {path_remainder}: /bar/dog/cat -->

        <path pattern="bar">
            <!-- {path_remainder}: /dog/cat -->

            <path pattern="dog">
                <!-- {path_remainder}: /cat -->

                <path pattern="{animal}">
                    <!-- {path_remainder}:  -->

                </path>
                <path pattern="cat">
                    <!-- {path_remainder}:  -->

                </path>
            </path>
        </path>
    </path>

</path>
```