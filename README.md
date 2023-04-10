# An AWS DynamoDB driver for spatie/laravel-event-sourcing

A DynamoDB driver for `spatie/laravel-event-sourcing` allowing for a serverless approach to data storage.

! Work In Progress ! Not yet suitable for use. The package is functionally complete but has only had light real world
testing on the example Larabank project.

This package provides a DynamoDB implementation for `StoredEventRepository` and `SnapshotRepository`.

The package endeavours to have no behaviour change when compared to the original Spatie Eloquent implementation.
This causes a few choices and compromises in dynamo table design that might seem strange otherwise.

If you're interested in this project please leave a note in the discussions section, and I'll let you know when the first
versioned release drops.

Requires 64bit PHP 8.2 due to the way it generates unique ids.

TODOs:

- Reduce the number of Global Secondary Indexes (currently 3, one KEYS_ONLY) for stored events, if possible. Currently,
    `StoredEventRepository` methods `retrieveAll()` without an aggregate uuid, and `find()` create a problem for this.
- Snapshot retrieval could make one less request in the case that there was no need to break the snapshot up into parts.
- `DynamoDbStoredEventRepository::RetrieveAllAfterVersion()` uses a filter expression which isn't cost-efficient.
- Handling for manageable DynamoDb errors.
- A cleaner approach to handling metadata.
- Ensure package config is correct and install journey is easy and clear.
- Provide an interface to allow users to replace IdGenerator with their own.
- Copy and modify any parts of the main package test suite that can give more end to end coverage.
- Write some basic docs.
- IdGenerator is a bit over-engineered, simplify it e.g. there's probably no need for it be a singleton with its own 
collision prevention, it's not going to be realistic to call the same instance twice in one microsecond.


## How It Works

### Events
- Events are stored in a table that has several indexes that cover the various possible behaviours of the spatie
 `StoredEventRepository` interface.
- 

### Snapshots
- Snapshots are stored in a single table, but as one or more items to allow snapshots to exceed 400Kb in size.
- PHP `serialize()` is used on the output of you aggregate root's `getState()` method and the results are then base64
  encoded and split into multiple parts if too large to fit a single dynamo item.
- When a snapshot is retrieved the parts are recombined behind the scenes to rehydrate your aggregate root.
- The total size of snapshots is not limited by DynamoDb and only constrained by the PHP memory limit of the process
  working with them.
- All of this behaviour is hidden behind the `SnapshotRepository` interface and should 'just work'.

## Limitations

### Dynamo Db
- Individual Events cannot exceed 400Kb in size (max size of a DynamoDb item). We engineer around this for snapshots.
- The package expects `PAY_PER_REQUEST` billing mode behaviour and doesn't currently support provisioned throughput. 
    For example, there's no handling of throughput exceeded exceptions nor a wait/retry mechanism for this.
- The package currently creates 3 Global Secondary Indexes, two with projection type `ALL` and one `KEYS ONLY`. This
  has a cost implication for writes and storage and should be factored in when estimating your Dynamo spend. If you 
  do not have a use case for calling `StoredEventRepository::retrieveAll($uuid = null)` without specifying an aggregate 
  UUID, you can remove the `id-sort_id-index` from the table specification in config and save money. The spatie package 
  internally does not (currently) use the method in that way either.

### Event Ids
- This package generates its own Int64 ids for events. This is due to the Spatie package interfaces expecting
    integer ids and the logic expecting them to be incrementing. Dynamo has no mechanism for returning incrementing ints.
- These ids consist of the current microsecond timestamp expressed as an integer plus 3 random digits appended to the end.
- The timestamp component approximates the incrementing id behaviour that's expected for events and the random digits
  increase collision resistance in the unlikely event that two are generated in the same microsecond.
- Collisions are possible but unlikely. The collision scenario would be two PHP processes generating ids and hitting the
same microsecond timestamp and then generating the same random 3 digit int to append to them. 
- This collision scenario is unhandled in the code and the consequences would depend on the design of your application.
- In the event that the random digits do not clash but the microsecond timestamp is the same, the event ordering 
is determined by the random digits and thus might be unexpected. This is unlikely to cause an issue depending
on how the events are used immediately after they are generated.

## Installation

Install the package via composer:

```bash
composer require blackfrog/laravel-event-sourcing-dynamodb
````

Publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-event-sourcing-dynamodb-config"
```

Review the config key `dynamodb-client` and make sure the appropriate ENV variables are set, or you may wish to use
your own ENV variable names if the package defaults clash for you. This array is just the configuration array passed to
`Aws\DynamoDb\DynamoDbClient` so you can modify it to use anything the AWS package supports, including alternative
authentication options. If you already use AWS, for example with DynamoDb as a Cache driver for Laravel, you should 
check and align your configuration for this with the one for this package to avoid confusion or duplication.

You can create the relevant DynamoDb tables with `php artisan event-sourcing-dynamodb:create-tables`. This requires
appropriate AWS permissions to do so and is probably unwise to use in a production scenario. You can see (and modify at
your own risk) the table specifications in `event-sourcing-dynamodb.php`. For production, we recommend you take these 
table specs and move them into your preferred mechanism for managing AWS resources, such as Terraform or CloudFormation.

## Testing

```bash
composer test
```

## Local Development

For local development you can use:
[DynamoDB Local](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/DynamoDBLocal.html)

The test suite expects this to be present and running at default ports if you wish to run the tests locally yourself.

There are some minor differences in behaviour from the real service, and we recommend testing against a real DynamoDb in
your AWS account before launching your project.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [BlackFrog Software Consultancy](https://blackfrog.co)
- [Shaun Keating](https://github.com/shkeats)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
