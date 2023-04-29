<?php

declare(strict_types=1);

// config for BlackFrog/LaravelEventSourcingDynamodb
use BlackFrog\LaravelEventSourcingDynamodb\IdGeneration\MicroTimestampProvider;
use BlackFrog\LaravelEventSourcingDynamodb\IdGeneration\TimeStampIdGenerator;

return [
    'dynamodb-client' => [
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'version' => 'latest',
        'endpoint' => env('DYNAMODB_ENDPOINT', null),
        'credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID', 'fakeMyKeyId'),
            'secret' => env('AWS_SECRET_ACCESS_KEY', 'fakeSecretAccessKey'),
        ],
    ],

    'read_consistency' => (bool) env('DYNAMO_EVENTS_READ_CONSISTENCY', false),

    'stored-event-table' => 'stored_events',

    'stored-event-table-definition' => [
        'AttributeDefinitions' => [
            ['AttributeName' => 'id', 'AttributeType' => 'N'],
            ['AttributeName' => 'sort_id', 'AttributeType' => 'N'],
            ['AttributeName' => 'aggregate_uuid', 'AttributeType' => 'S'],
            ['AttributeName' => 'aggregate_version', 'AttributeType' => 'N'],
            ['AttributeName' => 'version_id', 'AttributeType' => 'S'],
        ],
        'KeySchema' => [
            ['AttributeName' => 'aggregate_uuid', 'KeyType' => 'HASH'],
            ['AttributeName' => 'id', 'KeyType' => 'RANGE'],
        ],
        'LocalSecondaryIndexes' => [
            [
                'IndexName' => 'aggregate_uuid-version-id-index',
                'KeySchema' => [
                    ['AttributeName' => 'aggregate_uuid', 'KeyType' => 'HASH'],
                    ['AttributeName' => 'version_id', 'KeyType' => 'RANGE'],
                ],
                'Projection' => ['ProjectionType' => 'KEYS_ONLY'],
            ],
        ],
        'GlobalSecondaryIndexes' => [
            [
                'IndexName' => 'id-sort_id-index',
                'KeySchema' => [
                    ['AttributeName' => 'id', 'KeyType' => 'HASH'],
                    ['AttributeName' => 'sort_id', 'KeyType' => 'RANGE'],
                ],
                'Projection' => ['ProjectionType' => 'ALL'],
            ],
        ],
        'BillingMode' => 'PAY_PER_REQUEST',
    ],

    'snapshot-table' => 'snapshots',

    'snapshot-table-definition' => [
        'AttributeDefinitions' => [
            ['AttributeName' => 'aggregate_uuid', 'AttributeType' => 'S'],
            ['AttributeName' => 'id_part', 'AttributeType' => 'S'],
        ],
        'KeySchema' => [
            ['AttributeName' => 'aggregate_uuid', 'KeyType' => 'HASH'],
            ['AttributeName' => 'id_part', 'KeyType' => 'RANGE'],
        ],
        'BillingMode' => 'PAY_PER_REQUEST',
    ],

    'id_generator' => TimeStampIdGenerator::class,
    'id_timestamp_provider' => MicroTimestampProvider::class,
];
