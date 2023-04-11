# An AWS DynamoDB driver for spatie/laravel-event-sourcing

A DynamoDB driver for `spatie/laravel-event-sourcing` allowing for a serverless approach to data storage.

! Work In Progress ! Not yet suitable for use. The package is functionally complete but has only had light real world
testing on the example Larabank project.

The package endeavours to have no behaviour change when compared to the original Spatie Eloquent implementation.
This causes a few choices and compromises in dynamo table design that might seem strange otherwise.

If you're interested in this project please leave a note in the discussions section, and I'll let you know when the
first
versioned release drops.

Requires 64bit PHP 8.2 due to the way it generates unique ids.

TODOs for Release:

- `DynamoDbStoredEventRepository::RetrieveAllAfterVersion()` uses a filter expression which isn't efficient.
- Handling for manageable DynamoDb errors.
- A cleaner approach to handling metadata.
- Ensure package config is correct and install journey is easy and clear.
- Provide an interface to allow users to replace IdGenerator with their own.
- Copy and modify any parts of the main package test suite that can give more end to end coverage.
- Write some basic docs. (WIP)
- IdGenerator is a bit over-engineered, simplify it e.g. there's probably no need for it be a singleton with its own
  collision prevention, it's not going to be realistic to call the same instance twice in one microsecond.
- Persist() with null uuid?

## Features

- Provides a DynamoDB implementation for `StoredEventRepository` and `SnapshotRepository`.
- Complete compatibility with the Spatie Eloquent implementations.
- Support for [strongly consistent reads](#read-consistency) (with caveats).
- Unlimited [snapshot](#snapshots) size.
- CreateTables command to get you started quickly.

## How It Works

The gorey DynamoDB details.

### Events

- Events are stored in their own table with `aggregate_uuid` as `HASH` (partition) key and `id` as `SORT` key.
- The events table has two extra indexes to cover the possible behaviours of the `StoredEventRepository` interface.
- A [Global Secondary Index](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/GSI.html) (projects all
  attributes) that has the `id` as both the `HASH` and `SORT` keys supports finding events by their id without their
  aggregate UUID, and preserves event order when fetching all events.
- A [Local Secondary Index](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/LSI.html) (projects keys
  only) has `aggregate_uuid` as `HASH` and `aggregate_version` as `SORT` to support `getLatestAggregateVersion()`.
- Event order is preserved using a generated incrementing integer id based on the current microsecond timestamp.
  See [Event Ids](#event-ids) for details.

### Snapshots

- Snapshots are stored in a single table, but as one or more items, allowing snapshots to exceed 400KB in size.
- PHP `serialize()` is used on the output of you aggregate root's `getState()` method and the results are then base64
  encoded and split into multiple parts if too large to fit inside a single DynamoDB item (400KB limit).
- When a snapshot is retrieved the parts are recombined behind the scenes to rehydrate your aggregate root.
- The total size of snapshots is not limited by DynamoDb and only constrained by the PHP memory limit of the process
  working with them.
- Each snapshot has an integer Id generated in the same as

### Read Consistency

- You can
  configure [consistent reads from DynamoDB](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/HowItWorks.ReadConsistency.html)
  using the `read_consistency` config key (defaults to `false`.) This only applies to methods where you pass an
  aggregate root UUID as an argument. Some method calls on the EventRepository such as `find($id)`
  and `retrieveAll(null)`
  remain eventually consistent.

## Limitations

### Dynamo Db

- Individual Events cannot exceed 400KB in size, which is max size of a DynamoDb item.
- The maximum size of all events data per Aggregate UUID is 10GB due to the use of
  a [Local Secondary Index](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/LSI.html), If you do not
  intend to use the read consistency feature, you can remove this limitation by moving the
  index `aggregate_uuid-version-index` to the `GlobalSecondaryIndexes` **before** creating tables.
- The package expects `PAY_PER_REQUEST` billing mode behaviour and doesn't currently support provisioned throughput.
  For example, there's no handling of throughput exceeded exceptions nor a wait/retry mechanism for this.

### Transactions

- The spatie package has a method on its `AggregateRoot` base class called `persistInTransaction()`, this creates a
  Laravel DB transaction around the storage of events. There's currently no way for the Repository to know about this
  transaction, so we aren't able to implement it for DynamoDb. This is not used by the package internally, so you only
  need to be aware if you use this method yourself.

### Event Ids

- This package generates its own Int64 ids for events. This is due to the Spatie package interfaces expecting
  integer ids and the logic expecting them to be incrementing. Dynamo has no mechanism for returning incrementing ints.
- The ids consist of the current microsecond timestamp expressed as an integer plus 3 random digits appended to the end.
- The timestamp component approximates the incrementing id behaviour that's expected for events and the random digits
  increase collision resistance in the unlikely event that two are generated in the same microsecond.
- Collisions are possible but unlikely. The collision scenario would be two PHP processes generating ids and hitting the
  same microsecond timestamp and then generating the same random 3 digit int to append to them.
- This collision scenario is unhandled in the code and the consequences would depend on the design of your application.
- In the event that the random digits do not clash but the microsecond timestamp is the same, the event ordering
  is determined by the random digits.

## Getting Started

### Install

Install the package via composer:

```bash
composer require blackfrog/laravel-event-sourcing-dynamodb
````

### Configure

Publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-event-sourcing-dynamodb-config"
```

Review the config key `dynamodb-client` and make sure the appropriate ENV variables are set, or you may wish to use
your own ENV variable names if the package defaults clash for you. This array is the configuration array passed to
`Aws\DynamoDb\DynamoDbClient` so you can modify it to use anything the AWS package supports, including alternative
authentication options. If you already use AWS, for example with DynamoDb as a Cache driver for Laravel, you should
check and align your configuration for this with the one for this package to avoid confusion or duplication.

You can change the default table names using the `event-table` and `snapshot-table` config keys.

### Create DynamoDb Tables

You can create the relevant DynamoDb tables with `php artisan event-sourcing-dynamodb:create-tables`. This requires
appropriate AWS permissions to do so and is probably unwise to use in a production scenario. You can see (and modify at
your own risk) the table specifications in `event-sourcing-dynamodb.php`. For production, we recommend you take these
table specs and move them into your preferred mechanism for managing AWS resources, such as Terraform or CloudFormation.

### Update Spatie Laravel Event Sourcing Config

- Update the config for the Spatie Event sourcing package in `config/event-sourcing.php` setting the value
  for `stored_event_repository` to `DynamoDbStoredEventRepository::class` and `snapshot_repository`
  to `DynamoDbSnapshotRepository::class`.

## Testing

Running the test suite requires DynamoDBLocal, see [Local Development](#local-development) for setup.

The test suite expects this to be present and running at default ports.

```bash
composer test
```

## Local Development

For local development you can use:
[DynamoDB Local](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/DynamoDBLocal.html)
There are some minor differences in behaviour from the real service, and we recommend testing against real DynamoDb in
your AWS account before launching your project.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [BlackFrog Software Consultancy](https://blackfrog.co)
- [Shaun Keating](https://github.com/shkeats)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
