<?php

// config for BlackFrog/LaravelEventSourcingDynamodb
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
            ['AttributeName' => 'aggregate_uuid', 'AttributeType' => 'S'],
            ['AttributeName' => 'aggregate_version', 'AttributeType' => 'N'],
        ],
        'KeySchema' => [
            ['AttributeName' => 'id', 'KeyType' => 'HASH'],
        ],
        'GlobalSecondaryIndexes' => [
            [
                'IndexName' => 'aggregate_uuid-index',
                'KeySchema' => [
                    ['AttributeName' => 'aggregate_uuid', 'KeyType' => 'HASH'],
                    ['AttributeName' => 'id', 'KeyType' => 'RANGE'],
                ],
                'Projection' => ['ProjectionType' => 'ALL'],
            ],
            [
                'IndexName' => 'aggregate_uuid-version-index',
                'KeySchema' => [
                    ['AttributeName' => 'aggregate_uuid', 'KeyType' => 'HASH'],
                    ['AttributeName' => 'aggregate_version', 'KeyType' => 'RANGE'],
                ],
                'Projection' => ['ProjectionType' => 'ALL'],
            ],
        ],
        'BillingMode' => 'PAY_PER_REQUEST',
    ],

    'snapshot-table' => 'snapshots',

    'snapshot-part-table' => 'snapshot_parts'
];
