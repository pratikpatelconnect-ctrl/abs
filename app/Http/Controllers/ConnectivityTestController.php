<?php

namespace App\Http\Controllers;

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
        
        // Debug: Check certificate files exist
        $certPath = storage_path('app/certs/uobuat_sivren_org.crt');
        $keyPath = storage_path('app/certs/uobuat_sivren_org.pem');
        
        if (!file_exists($certPath)) {
            return response()->json([
                'message' => 'Certificate file not found',
                'cert_path' => $certPath,
            ], 500);
        }
        
        if (!file_exists($keyPath)) {
            return response()->json([
                'message' => 'Private key file not found',
                'key_path' => $keyPath,
            ], 500);
        }
        
        // Debug: Log the configuration being used
        \Log::info('Connectivity Test Configuration', [
            'client_slug' => $clientSlug,
            'client_config' => $clientConfig,
            'url' => $url,
            'headers' => $header,
            'cert_exists' => file_exists($certPath),
            'key_exists' => file_exists($keyPath),
        ]);
        
        // Initialize cURL
        $ch = curl_init();
        
        // Set cURL options
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($request_body),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'clientID: ' . $header['clientID'],
                'requestID: ' . $header['requestID'],
                'x-api-key: ' . $header['x-api-key'],
                'aggregatorKeyAlias: ' . $header['aggregatorKeyAlias'],
            ],
            CURLOPT_SSLCERT => $certPath,
            CURLOPT_SSLKEY => $keyPath,
            CURLOPT_SSLCERTTYPE => 'PEM',
            CURLOPT_SSLKEYTYPE => 'PEM',
            CURLOPT_SSL_VERIFYPEER => false, // Set to true and provide CA cert if needed
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);
        
        // Execute the request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $curlInfo = curl_getinfo($ch);
        
        // Close cURL handle
        curl_close($ch);
        
        // Debug logging
        \Log::info('Connectivity Test Request Debug', [
            'url' => $url,
            'headers' => $header,
            'request_body' => $request_body,
            'http_code' => $httpCode,
            'curl_error' => $error,
            'curl_info' => $curlInfo,
            'response' => $response,
        ]);
        
        // Check for cURL errors
        if ($response === false || !empty($error)) {
            return response()->json([
                'request' => [
                    'url' => $url,
                    'header' => $header,
                    'request_body' => $request_body,
                ],
                'message' => 'cURL request failed',
                'error' => $error ?: 'Unknown cURL error',
                'curl_info' => $curlInfo,
            ], 502);
        }
        
        // Check for HTTP errors
        if ($httpCode >= 400) {
            return response()->json([
                'request' => [
                    'url' => $url,
                    'header' => $header,
                    'request_body' => $request_body,
                ],
                'message' => 'Upstream request failed',
                'error' => $response,
                'http_code' => $httpCode,
                'curl_info' => $curlInfo,
            ], $httpCode);
        }
        
        // Parse JSON response
        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'message' => 'Invalid JSON response from upstream',
                'raw_response' => $response,
            ], 502);
        }
        
        return response()->json([
            'message' => 'Connectivity test',
            'data' => $responseData,
        ]);
    }
}
