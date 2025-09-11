<?php

return [
    'soonsengcredit' => [
        'uat' => [
            'client_id' => 'BOSOO1887G01SOO',
            'x-api-key' => '1135a09c-b0db-4c95-93e7-b8eb8f3e63e2',
            'pgp' => [
                'issuer_id' => 'uobuat@sivren.com',
                'passphrase' => 'pgpss2025',
                'fingerprint' => 'C17872836FDA628389B626533E949729690588A7',
                'issuer_key_id' => '3E949729690588A7',
                'public_key' => storage_path('app/pgp/public.asc'),
                'private_key' => storage_path('app/pgp/private.asc'),
            ]
        ],
        'production' => [
            'client_id' => '',
            'x-api-key' => '',
            'pgp' => [
                'issuer_id' => '',
                'passphrase' => '',
                'fingerprint' => '',
                'issuer_key_id' => '',
                'public_key' => storage_path('app/pgp/public.asc'),
                'private_key' => storage_path('app/pgp/private.asc'),
            ]
        ],
    ],
    'galaxycredit' => [
        'uat' => [
            'client_id' => 'BOSOO1887G01SOO',
            'x-api-key' => '1135a09c-b0db-4c95-93e7-b8eb8f3e63e2',
            'pgp' => [
                'issuer_id' => 'uobuat@sivren.com',
                'passphrase' => 'uatuob2025',
                'fingerprint' => 'C17872836FDA628389B626533E949729690588A7',
                'issuer_key_id' => '3E949729690588A7',
                'public_key' => storage_path('app/pgp/public.asc'),
                'private_key' => storage_path('app/pgp/private.asc'),
            ]
        ],
        'production' => [
            'client_id' => '',
            'x-api-key' => '',
            'pgp' => [
                'issuer_id' => '',
                'passphrase' => '',
                'fingerprint' => '',
                'issuer_key_id' => '',
                'public_key' => storage_path('app/pgp/public.asc'),
                'private_key' => storage_path('app/pgp/private.asc'),
            ]
        ],
    ],
];