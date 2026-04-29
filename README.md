# Webisters Autoload

Webisters Autoload Library This library is designed for reuse in Composer-based PHP applications.

## What It Provides
- Reusable PHP components intended for integration into larger applications.
- Clean interfaces and extension points to support maintainable implementations.
- Compatibility-minded packaging so the library can be adopted with Composer.

## Installation
```bash
composer require webisters/autoload
```

## Requirements
- PHP: `>=8.2`
- Composer: Compatible with Composer 2.x.

## Quick Start
```php
<?php
require __DIR__ . '/vendor/autoload.php';

// Initialize the package and integrate it into your application flow.
```
Next step: review the package source and adapt initialization to your use case.

## Development
```bash
composer install
vendor/bin/phpunit
```
Follow consistent coding style and run available linters before opening pull requests.

## Support
- Issues: https://github.com/webisters/autoload/issues
- Source: https://github.com/webisters/autoload
- Documentation: https://webisters.com
- Forum: https://github.com/webisters/forum
- Email: support@webisters.com

## License
MIT
