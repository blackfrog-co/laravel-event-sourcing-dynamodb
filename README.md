# An AWS DynamoDB driver for Spatie Laravel Event Sourcing

A DynamoDB driver for [`spatie/laravel-event-sourcing`](https://github.com/spatie/laravel-event-sourcing) allowing for a
serverless approach to data storage.

Event sourcing can be complicated, and so can DynamoDB, make sure you have a decent grasp of DynamoDB and read the whole
README before choosing this approach.

**! Work In Progress !** Not yet suitable for use. The package is functionally complete but has only had light real
world testing on the example Larabank project. Please wait for the first SemVer versioned release.

TODOs for Release:

- `DynamoDbStoredEventRepository::RetrieveAllAfterVersion()` uses a filter expression which isn't efficient.
- Review approach to handling event metadata, ensure its compatible.
- Copy and modify any parts of the main package test suite that can give more end to end coverage.
- We're currently using LazyCollection->remember(), see if this can be avoided.

## Features

- Provides a DynamoDB implementation for `StoredEventRepository` and `SnapshotRepository`.
- Compatibility with the Spatie Eloquent implementations. See [minor differences](#minor-differences).
- Unlimited [snapshot](#snapshots) size.
- CreateTables command to get you started quickly.
- Optional support for [strongly consistent reads](#read-consistency) (with caveats).

### Minor Differences

- The default `EloquentStoredEventRepository:store()` implementation converts a `null` `$uuid` argument to an empty
  string for storage. DynamoDB does not allow empty strings, so we store this as the string `'null'`.
- There's currently no support for `persistInTransaction()` on AggregateRoots, the package doesn't use this method
  out of the box itself, but you might. [Read More](#transactions).

### Requirements

- 64bit PHP 8.2
- `"spatie/laravel-event-sourcing": "^7.3.3"`,

## How It Works

### Events

- Events are stored in a table with `aggregate_uuid` as `HASH` (partition) key and `id` as `RANGE` key.
- The events table has two indexes to cover the behaviours of the `StoredEventRepository` interface.
- A [Global Secondary Index](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/GSI.html) (projects all
  attributes) that has the `id` as both the `HASH` and `RANGE` keys supports fetching events without their aggregate
  uuid while preserving their order, both `find($id)` and `retrieveAll(null)` use this.
- A [Local Secondary Index](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/LSI.html) (projects keys
  only) has `aggregate_uuid` as `HASH` and `aggregate_version` as `RANGE` to support `getLatestAggregateVersion()`. This
  can be changed to a GSI if you don't need the read consistency feature. See [DynamoDB Limitations](#dynamodb) for more
  info.
- Event order is preserved using a generated incrementing integer id based on the current microsecond timestamp.
  This is necessary for compatibility with the Spatie package, see [Event Ids](#event-ids) for details.

### Snapshots

- Snapshots are stored in a single table, but as one or more items, allowing snapshots to exceed the DynamoDB 400KB
  limit in size. The table has the `aggregate_uuid` as the `HASH` key and `id_part` as the `RANGE` key.
- `id_part` is a composite of a randomly generated int id and the 'part number' of the snapshot. This does two things,
  it means that the most recent snapshots are returned first when queried (using a DESC sort) and that the snapshot
  parts are returned in the correct order if the snapshot required more than one DynamoDB item to be stored.
- PHP `serialize()` is used on the output of you aggregate root's `getState()` method and the results are then base64
  encoded and split into multiple parts if too large to fit inside a single DynamoDB item (400KB limit).
- When a snapshot is retrieved the parts are recombined behind the scenes to rehydrate your aggregate root.
- The total size of snapshots is not limited by DynamoDB and only constrained by the PHP memory limit of the process
  working with them.

### Read Consistency

- You can
  configure [consistent reads from DynamoDB](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/HowItWorks.ReadConsistency.html)
  using the `read_consistency` config key (defaults to `false`.) This only applies to methods where you pass an
  aggregate root UUID as an argument. Some method calls on the EventRepository such as `find($id)`
  and `retrieveAll(null)` remain eventually consistent.

## Limitations

### DynamoDB

- Individual Events cannot exceed 400KB in size, which is max size of a DynamoDB item.
- The maximum size of all events data per Aggregate UUID is 10GB due to the use of
  a [Local Secondary Index](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/LSI.html), If you do not
  intend to use the [read consistency](#read-consistency) feature, you can remove this limitation by moving the
  index `aggregate_uuid-version-index` to the `GlobalSecondaryIndexes` **before** creating tables.
- The package expects `PAY_PER_REQUEST` billing mode behaviour and doesn't currently support provisioned throughput.
  For example, there's no handling of throughput exceeded exceptions nor a wait/retry mechanism for this.

### Transactions

- The spatie package has a method on its `AggregateRoot` base class called `persistInTransaction()`, this creates a
  Laravel DB transaction around the storage of events. There's currently no way for the Repository to know about this
  transaction, so we aren't able to implement it for DynamoDB. This is not used by the package internally, so you only
  need to be aware if you use this method yourself.

### Event Ids

- This package generates its own 64bit `int` Ids for events. The Spatie package interfaces expect integer ids and the
  logic expects them to be incrementing. DynamoDB does not provide incrementing ids.
- The Ids consist of the current microsecond timestamp expressed as an integer plus 3 random digits appended to the end.
  approximating the incrementing behaviour that's expected for event order and the random digits
  increase collision resistance in the unlikely event that two are generated in the same microsecond.
- Collisions are possible but unlikely and currently unhandled in the code, the consequences would depend on the design
  of your application.
- Consider also that, at scale, clock skew between servers could cause issues for this.
- You can switch to your own implementation for generation of Ids by implementing the `IdGenerator` interface and
  updating the config key `id_generator`.

```
    'id_generator' => TimeStampIdGenerator::class,
```

- You can switch to your own implementation of a timestamp provider for the provided `TimeStampIdGenerator` by
  implementing the `TimestampProvider` interface and updating the config key `id_timestamp_provider`. If you return a
  shorter timestamp (e.g. seconds or milliseconds) the TimeStampIdGenerator will fill the remainder of the 64bit Int
  with random digits.

```
    'id_timestamp_provider' => TimeStampIdGenerator::class,
```

### LazyCollections

- The package currently calls the `remember()` method on LazyCollections before it returns them. This limits the
  complexity of the pagination required but means that if you traverse the entire collection it will remain in memory.

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
authentication options. If you already use AWS, for example with DynamoDB as a Cache driver for Laravel, you should
check and align your configuration for this with the one for this package to avoid confusion or duplication.

You can change the default table names using the `event-table` and `snapshot-table` config keys.

### Create DynamoDB Tables

You can create the relevant DynamoDb tables with `php artisan event-sourcing-dynamodb:create-tables`. This requires
appropriate AWS permissions to do so and is probably unwise to use in a production scenario. You can see (and modify at
your own risk) the table specifications in `event-sourcing-dynamodb.php`. For production, we recommend you take these
table specs and move them into your preferred mechanism for managing AWS resources, such as Terraform or CloudFormation.

### Update Spatie Laravel Event Sourcing Config

Update the config for the Spatie Laravel Event Sourcing package in `config/event-sourcing.php` setting the value
for `stored_event_repository` to `DynamoDbStoredEventRepository::class` and `snapshot_repository`
to `DynamoDbSnapshotRepository::class`.

## Testing

Running the test suite requires DynamoDBLocal, see [Local Development](#local-development) for setup.

The test suite expects this to be present and running at default ports.

**Run:**

```bash
composer test
```

## Local Development

For local development you can use:
[DynamoDB Local](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/DynamoDBLocal.html)
There are some minor differences in behaviour from the real service, and we recommend testing against real DynamoDB in
your AWS account before launching your project.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [BlackFrog Software Consultancy](https://blackfrog.co)
- [Shaun Keating](https://github.com/shkeats)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
