<?php

return [
    'soonsengcredit' => [
        'uat' => [
            'client_id' => 'BOSOO1887G01SOO',
            'x-api-key' => '1135a09c-b0db-4c95-93e7-b8eb8f3e63e2',
            'pgp' => [
                'issuer_id' => 'uobuat@sivren.com',
                'passphrase' => 'pgpss2025',
                'fingerprint' => '4CA8AD8EAA3A29E33765A659326E636F574ACFDA',
                'issuer_key_id' => '326E636F574ACFDA',
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
            'client_id' => 'BOGAL0194M01GAL',
            'x-api-key' => '9523ef31-107d-4242-9139-6360337bd8d2',
            'pgp' => [
                'issuer_id' => 'uobuat@sivren.com',
                'passphrase' => 'uatuob2025',
                'fingerprint' => '40044FCBD111BD3ADEBA4447BF7AC39F6DC6A2A2',
                'issuer_key_id' => 'BF7AC39F6DC6A2A2',
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
    '96bmcredit' => [
        'uat' => [
            'client_id' => 'BO96B2532M0196B',
            'x-api-key' => 'c11287ac-1ca3-445b-a9ae-1bae619fc987',
            'pgp' => [
                'issuer_id' => 'uobuat@sivren.com',
                'passphrase' => 'uatuob2025',
                'fingerprint' => '40044FCBD111BD3ADEBA4447BF7AC39F6DC6A2A2',
                'issuer_key_id' => 'BF7AC39F6DC6A2A2',
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