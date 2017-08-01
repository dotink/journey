# Journey

Journey is a wrapper around [FastRoute]() which provides some additional functionality by extending the collector and providing a thin wrapping layer around the dispatcher.  Additional functionality includes:

- The ability to register shorthand patterns
- Ability to supply a route action resolver
- Ability to define parameter transformations (conversions of parameters to and from URLs)
- Easy link generation (not reverse routing)
- Direct usage of PSR-7 Requests/Response objects


## Defining Routes

First we need to create a collection:

```php
$parser    = new FastRoute\RouteParser\Std();
$generator = new FastRoute\DataGenerator\GroupCountBased();
$collector = new Dotink\Journey\Collector($parser, $generator);
```

Once the collection is created, routes can be defined in accordance with FastRoute's documentation:

```php
$collector->addRoute('GET', '/test', 'handler');
```

Multiple request methods for matching can be defined:

```php
$collector->addRoute(['GET', 'POST'], '/test', 'handler');
```

And shorthands can be used for a single request type:

```php
$collector->get(/test', 'handler');
```

Available only to Journey is also the `any()` method, which will match any one of GET, PUT, PATCH, POST, DELETE, HEAD:

```php
$collector->any(/test', 'handler');
```

### Pattern Matching

FastRoute provides RegEx based pattern matching out of the box, e.g.:

```php
$collector->addRoute('GET', '/user/{id:\d+}', 'handler');
```

It also provides optional parameters and URL components:

```php
$collector->addRoute('GET', '/user/{id:\d+}[/{name}]', 'handler');
```

While both of these remain, Journey also provides the ability to register pattern shorthands:

```php
$collector->addPattern('#', '\d+');
$collector->addRoute('GET', '/user/{id:#}', 'handler');
```

A pattern must match precisely to the string between the `:` and the closing `}` in the parameter token and will be swapped out one for one with the RegEx defined as the pattern when the route is added.  If no registered pattern matches precisely, then the pattern will be interpreted as a RegEx.
