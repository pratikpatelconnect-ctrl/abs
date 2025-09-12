<?php

namespace App\Http\Controllers;

use Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Str;

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
        $request_id = Str::uuid()->toString();
        $url = config('abs.' . env('APP_ENV') . '.connectivityTest.api_url');
        $header = [
            'clientID' => $clientConfig['client_id'],
            'requestID' => $request_id,
            'x-api-key' => $clientConfig['x-api-key'],
            'aggregatorKeyAlias' => 'ABS_eGIRO',
        ];
        $request_body = [
            'message' => 'Connectivity Test',
        ];
        $http = Http::withOptions([
            'cert' => storage_path('app/certs/uobuat_sivren_org.crt'),
            'ssl_key' => storage_path('app/certs/uobuat_sivren_org.pem'),
            // 'verify' => storage_path('app/certs/root-ca.crt'),
        ])->withHeaders($header);
        $response = $http->post($url, $request_body);
        if ($response->failed()) {
            return response()->json([
                'request' => [
                    'url' => $url,
                    'header' => $header,
                    'request_body' => $request_body,
                ],
                'message' => 'Upstream request failed',
                'error' => $response->body(),
            ], $response->status() ?: 502);
        }
        return response()->json([
            'message' => 'Connectivity test',
            'data' => $response->json(),
        ]);
    }
}
