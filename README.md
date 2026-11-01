# Webisters Autoload

Class loading for Webisters applications: a PSR-4 autoloader you configure at
runtime, a locator that resolves class names and file paths through it, and a
preloader that feeds those files to the PHP OPcache.

## What It Provides

- **`Autoloader`** - maps namespace prefixes to directories (PSR-4) and, for
  classes that do not follow a namespace convention, maps a single class name
  straight to its file. Registers itself through `spl_autoload_register()`.
- **`Locator`** - answers the questions the autoloader needs: which fully
  qualified class name does this file declare, which file backs this namespaced
  path, and which files live under a namespaced directory.
- **`Preloader`** - collects every file reachable from the registered
  namespaces, classes and sibling `webisters` packages, and requires them so
  OPcache can preload them when the engine starts.

## Installation

```bash
composer require webisters/autoload
```

## Requirements

- PHP: `>=8.2`
- PHP extension: `ext-intl` (Intl - Internationalization extension)
- Composer: Compatible with Composer 2.x.

## Usage

Register a namespace and let the autoloader resolve a class from it:

```php
<?php
use Framework\Autoload\Autoloader;

require __DIR__ . '/vendor/autoload.php';

$autoloader = new Autoloader();
$autoloader->setNamespace('App', __DIR__ . '/app');

// Resolves to __DIR__ . '/app/Models/User.php'
$user = new App\Models\User();
```

Constructing the `Autoloader` registers it. Pass `new Autoloader(false)` to
configure it first and call `register()` yourself.

### Namespaces or single classes

The `Autoloader` offers two kinds of mapping. Which one you want depends on
whether the class name predicts the file path.

**Namespace mapping (PSR-4)** is for code where it does. The namespace prefix
stands for a directory, and the rest of the class name is the path below it:

```php
$autoloader->setNamespace('App\Models', __DIR__ . '/app/Models');

// App\Models\Blog\Post -> __DIR__ . '/app/Models/Blog/Post.php'
```

A prefix can resolve against more than one directory, searched in order. Use
`addNamespace()` to append to a prefix instead of replacing it, and
`setNamespaces()` or `addNamespaces()` to register several at once:

```php
$autoloader->setNamespace('App', [__DIR__ . '/app', __DIR__ . '/app-overrides']);
$autoloader->addNamespace('App', __DIR__ . '/app-extra');
```

Longer prefixes win: with both `App` and `App\Models` registered,
`App\Models\User` resolves through `App\Models`.

**Class mapping** is for the cases where the class name says nothing about the
file path: legacy code in the global namespace, a class whose file is named
differently, or a generated classmap. You name the file outright:

```php
$autoloader->setClass('OneClass', __DIR__ . '/legacy/one-class.php');
$autoloader->setClass(App\Models\User::class, __DIR__ . '/app/User.php');
```

A class mapping is checked before the namespaces, so it also serves as an
override for a single class inside an otherwise PSR-4 tree. Both kinds of
mapping are resolved to a real path when you register them, so the file or
directory has to exist already or a `RuntimeException` is thrown.

### Locating files

```php
use Framework\Autoload\Locator;

$locator = new Locator($autoloader);

// The first class, interface, trait or enum declared in a file
$locator->getClassName(__DIR__ . '/app/Models/User.php'); // 'App\Models\User'

// The file backing a namespaced path
$locator->getNamespacedFilepath('App/Models/User'); // '.../app/Models/User.php'

// Every file under a namespaced sub directory
$locator->getFiles('Views');
```

### Preloading

```php
// opcache.preload = /path/to/preload.php
use Framework\Autoload\Preloader;

$preloader = new Preloader();
$preloader->addExcludes('*/src/bootstrap.php');

$loaded = $preloader->load();

foreach ($preloader->getFailedFiles() as $file => $message) {
    \error_log("Preload skipped {$file}: {$message}");
}
```

`load()` skips any file that raises an `Error`, which is what happens when a
class references a type from an optional dependency that is not installed, and
records it in `getFailedFiles()`. Pass `load(false)` to have that abort instead.

By default the preloader also picks up the sibling `webisters` packages. It
finds them two directories above `src`, which is `vendor/webisters` for a
Composer install and `libraries` for a monorepo checkout. Pass an explicit path
for any other layout, or `null` to load only what you registered yourself.

## Documentation
- Guide: https://docs.webisters.com/guides/libraries/autoload/
- Package: https://webisters.com/packages/autoload

## Included in Webisters Framework
If you're building a full Webisters application, install the framework meta-package:

```bash
composer require webisters/framework
```

## Development
```bash
composer install
vendor/bin/phpunit
```
Follow consistent coding style and run available linters before opening pull requests.

## Changelog
See [CHANGELOG.md](CHANGELOG.md).

## Support
- Issues: https://github.com/webisters/autoload/issues
- Source: https://github.com/webisters/autoload
- Documentation: https://webisters.com
- Forum: https://github.com/webisters/forum
- Email: support@webisters.com

## License
MIT
