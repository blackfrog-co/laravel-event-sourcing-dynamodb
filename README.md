# An AWS DynamoDB driver for spatie/laravel-event-sourcing

! Work In Progress ! Not yet suitable for use.

A DynamoDB driver for `spatie/laravel-event-sourcing` allowing for a serverless approach to data storage.

This package provides a DynamoDB implementation for `StoredEventRepository` and `SnapshotRepository`.

If you're interested in this project please leave a note in the discussions section, and I'll let you know when the first
versioned release drops.

Requires 64bit PHP 8.2 due to the way it generates unique ids.

Pre-Release TODOs:

- Reduce the number of Global Secondary Indexes (currently 3) for stored events (if possible).
- Use more efficient batch get requests for `DynamoDbSnapshotRepository::retrieveById()`.
- Use more efficient batch requests for `DynamoDbStoredEventRepository::persistMany()`.
- Handle possibility of DynamoDb returning unprocessed items in batch put requests.
- `DynamoDbStoredEventRepository::RetrieveAllAfterVersion()` uses a filter expression which isn't cost efficient.
- Handling for manageable DynamoDb errors.
- A cleaner approach to handling meta data.
- Ensure package config is correct and install journey is easy and clear.
- Provide an interface to allow users to replace IdGenerator with their own.
- Allow changing the Timestamp provider implementation in config.
- Copy and modify any parts of the main package test suite that can give more end to end coverage.
- Write some basic docs

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
