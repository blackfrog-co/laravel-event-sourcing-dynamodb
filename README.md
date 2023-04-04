# An AWS DynamoDB driver for spatie/laravel-event-sourcing

[![Latest Version on Packagist](https://img.shields.io/packagist/v/blackfrog/laravel-event-sourcing-dynamodb.svg?style=flat-square)](https://packagist.org/packages/blackfrog/laravel-event-sourcing-dynamodb)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/blackfrog/laravel-event-sourcing-dynamodb/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/blackfrog/laravel-event-sourcing-dynamodb/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/blackfrog/laravel-event-sourcing-dynamodb/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/blackfrog/laravel-event-sourcing-dynamodb/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/blackfrog/laravel-event-sourcing-dynamodb.svg?style=flat-square)](https://packagist.org/packages/blackfrog/laravel-event-sourcing-dynamodb)

! Work In Progress ! Not yet suitable for use.

A DynamoDB driver for spatie/laravel-event-sourcing.

If you're interested in this project please leave a note in the discussions section, and I'll let you know when the first
versioned release drops.

Requires 64bit PHP 8.2 due to the way it generates unique ids.

## Installation

You can install the package via composer:

```bash
composer require blackfrog/laravel-event-sourcing-dynamodb
````

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-event-sourcing-dynamodb-config"
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [BlackFrog Software Consultancy](https://blackfrog.co)
- [Shaun Keating](https://github.com/shkeats)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
