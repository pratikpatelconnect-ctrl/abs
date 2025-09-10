<?php

return [
    'soonsengcredit' => [
        'uat' => [
            'client_id' => 'BOSOO1887G01SOO',
            'x-api-key' => '1135a09c-b0db-4c95-93e7-b8eb8f3e63e2',
            'pgp' => [
                'public_key' => storage_path('app/public/pgp/public.key'),
                'private_key' => storage_path('app/public/pgp/private.key'),
            ]
        ],
        'production' => [
            'client_id' => '',
            'x-api-key' => '',
            'pgp' => [
                'public_key' => storage_path('app/public/pgp/public.key'),
                'private_key' => storage_path('app/public/pgp/private.key'),
            ]
        ],
    ]
];