<?php

return [
    'soonsengcredit' => [
        'uat' => [
            'client_id' => 'BOSOO1887G01SOO',
            'x-api-key' => '1135a09c-b0db-4c95-93e7-b8eb8f3e63e2',
            'pgp' => [
                'passphrase' => 'pgpss2025',
                'fingerprint' => 'C17872836FDA628389B626533E949729690588A7',
                'public_key' => storage_path('app/pgp/public.asc'),
                'private_key' => storage_path('app/pgp/private.asc'),
            ]
        ],
        'production' => [
            'client_id' => '',
            'x-api-key' => '',
            'pgp' => [
                'passphrase' => '',
                'fingerprint' => '',
                'public_key' => storage_path('app/pgp/public.asc'),
                'private_key' => storage_path('app/pgp/private.asc'),
            ]
        ],
    ]
];