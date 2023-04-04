# An AWS DynamoDB driver for spatie/laravel-event-sourcing

! Work In Progress ! Not yet suitable for use.

A DynamoDB driver for `spatie/laravel-event-sourcing` allowing for a serverless approach to data storage.

This package provides a DynamoDB implementation for `StoredEventRepository` and `SnapshotRepository`.

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

## Credits

- [BlackFrog Software Consultancy](https://blackfrog.co)
- [Shaun Keating](https://github.com/shkeats)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
