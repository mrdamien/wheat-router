# Wheat/Route - An XML/PHP metaprogramming router

* Goals:
    * Flexibility
    * Composability
    * Speed

It is programmed via XML that gets converted to PHP.


## Simplest example:

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

## Conditionals
Do something if subject matches pattern, like enforce HTTPS
```xml
<test subject="{REQUEST_SCHEME}" pattern="/http$/">
    <return code="302" location="https://reddit.com" />
</test>
```

## Global variables
Declare and assign a value to a variable for later use.
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
        <value><call>User::isLoggedIn</call></value>
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

## URL Generation
To ease url generation, attach an id="UpperCamelCase" to a path. Ex:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<router xmlns:xi="http://www.w3.org/2001/XInclude">
    <path pattern="{one:\d+}" id="one">
        <path pattern="{two:\d+}" id="Two">
            <parameter name="two" type="int" />
            <path pattern="{three:\d+}" id="Three">
                <parameter name="three" function="intval" />
                ...
            </path>
            ...
        </path>
        ...
    </path>
</router>
```
The generated functions will have a signature to aid with autocomplete. Parameters can be typehinted, or passed through a function first. Ex: $two will be type-cast as an int, $three will be passed through intval().
```php
public function urlOne ($forum_id): string;
public function urlTwo ($forum_id, int $two): string;
public function urlThree ($forum_id, int $two, $three): string;
```
## Speed
Speed is pretty good. There is an example benchmark in that folder. xdebug off before benchmarking.
```
.../router/benchmark$ php reddit.php 12500
n:          12500
Homepage:   0.046115 s
lastReddit: 0.052861 s
User:       0.073144 s
Post:       0.077587 s
404:        0.058778 s
/j/k        0.053539 s
/j_0/k_9    0.086541 s
rss:        0.078591 s
Total:      100000 reqs in 0.527156 s
```