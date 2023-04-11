<?php

declare(strict_types=1);

// config for BlackFrog/LaravelEventSourcingDynamodb
use BlackFrog\LaravelEventSourcingDynamodb\IdGeneration\MicroTimeTimestampProvider;

return [
    'dynamodb-client' => [
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'version' => 'latest',
        'endpoint' => env('DYNAMODB_ENDPOINT', null),
        'credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
    ],

    'stored-event-table' => 'stored_events',

    'stored-event-table-definition' => [
        'AttributeDefinitions' => [
            ['AttributeName' => 'id', 'AttributeType' => 'N'],
            ['AttributeName' => 'sort_id', 'AttributeType' => 'N'],
            ['AttributeName' => 'aggregate_uuid', 'AttributeType' => 'S'],
            ['AttributeName' => 'aggregate_version', 'AttributeType' => 'N'],
        ],
        'KeySchema' => [
            ['AttributeName' => 'aggregate_uuid', 'KeyType' => 'HASH'],
            ['AttributeName' => 'id', 'KeyType' => 'RANGE'],
        ],
        'LocalSecondaryIndexes' => [
            [
                'IndexName' => 'aggregate_uuid-version-index',
                'KeySchema' => [
                    ['AttributeName' => 'aggregate_uuid', 'KeyType' => 'HASH'],
                    ['AttributeName' => 'aggregate_version', 'KeyType' => 'RANGE'],
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

    'id_timestamp_provider' => MicroTimeTimestampProvider::class,
];
