<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Services\GpgService;

class ConnectivityTestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_slug' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }
        $clientSlug = $request->string('client_slug');
        $clientConfig = config('clients.' . $clientSlug);
        if (!is_array($clientConfig) || empty($clientConfig[env('APP_ENV')])) {
            return response()->json([
                'message' => 'Invalid client configuration',
            ], 422);
        }
        $clientConfig = $clientConfig[env('APP_ENV')];
        $requestId = Str::uuid()->toString();
        $url = config('abs.' . env('APP_ENV') . '.connectivityTest.api_url');

        $aggregatorPublicKeyPath = storage_path('app/pgp/aggregator_public.asc');
        $aggregatorPublicKey = '';
        if (file_exists($aggregatorPublicKeyPath)) {
            $keyContent = file_get_contents($aggregatorPublicKeyPath);
            $aggregatorPublicKey = str_replace(["\r\n", "\r", "\n"], "\\n", trim($keyContent));
        }

        $headers = [
            'Content-Type' => 'text/plain',
            // 'Accept' => 'application/json',
            'clientID' => $clientConfig['client_id'],
            'requestID' => $requestId,
            'x-api-key' => $clientConfig['x-api-key'],
            // 'signKeyAlias' => "BOSOO201901887G_BU01_UAT_PGP_PUBLICKEY_PRIMARY_570832",
            'aggregatorKeyAlias' => "AGGREGATOR5_UAT_PGP_PRIMARY",
        ];

        $requestBody = [
            'message' => 'This is a test message',
        ];

        $gpgService = new GpgService();
        $plaintext = json_encode($requestBody, JSON_UNESCAPED_SLASHES);

        try {
            $aggregatorPublicKeyPath = storage_path('app/pgp/aggregator_public.asc');

            if (!file_exists($aggregatorPublicKeyPath)) {
                return response()->json([
                    'message' => 'Aggregator public key not found',
                ], 422);
            }

            $ciphertext = $gpgService->encrypt($plaintext, $aggregatorPublicKeyPath);

            $encryptedBody = [
                'payload' => $ciphertext,
            ];

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to encrypt request body',
                'error' => $e->getMessage(),
            ], 500);
        }

        $http = Http::withOptions([
            'cert' => storage_path('app/certs/uobuat_sivren_org.crt'),
            'ssl_key' => storage_path('app/certs/uobuat_sivren_org.pem'),
        ])->withHeaders($headers);

        $response = $http->post($url, $ciphertext);
        if ($response->failed()) {
            return response()->json([
                'request_data' => [
                    'url' => $url,
                    'headers' => $headers,
                    'original_request_body' => $requestBody,
                    'encrypted_request_body' => $ciphertext,
                    'timestamp' => now()->format('Y-m-d H:i:s'),
                ],
                'response_data' => [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ],
            ], $response->status() ?: 502);
        }
        return response()->json([
            'message' => 'Connectivity test',
            'data' => $response->json(),
        ]);
    }

    public function encodeURIComponent($query_param)
    {
        $revert = [
            '%21' => '!',
            '%2A' => '*',
            '%27' => "'",
            '%28' => '( ',
            '%29' => ' )',
        ];

        return strtr(rawurlencode($query_param), $revert);
    }
}