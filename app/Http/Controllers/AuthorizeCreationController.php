<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\GpgService;

class AuthorizeCreationController extends Controller
{
    public function createAuthorize(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_slug' => 'required|string',
            'applicantBankCode' => 'nullable|string|min:1|max:35',
            'boName' => 'required|string|max:140',
            'boTransactionRefNo' => 'required|string|min:35|max:35',
            'clientID' => 'required|string|min:15|max:15',
            'purpose' => 'nullable|string|in:LOAN',
            'requestID' => 'nullable|string|min:36|max:36',
            'requestType' => 'required|string|in:Creation',
            'segment' => 'required|string|in:Retail',
            'nonce' => 'nullable|string|min:20|max:20',
            'Timestamp' => 'nullable|string',
            'signKeyAlias' => 'nullable|string',
            'signature' => 'nullable|string',
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

        // Generate values if not provided in request
        $requestId = $request->input('requestID') ?: Str::uuid()->toString();
        $nonce = $request->input('nonce') ?: Str::random(20);
        $timestamp = $request->input('Timestamp') ?: (string) (time() * 1000);
        $signKeyAlias = $request->input('signKeyAlias') ?: 'KEY1';

        // Build signature parameters in correct order, excluding empty optional fields
        $signatureParams = $this->buildSignatureParams($request, $requestId, $nonce, $timestamp, $signKeyAlias);

        // Generate signature
        $signature = $this->generateSignature($signatureParams, $clientSlug, $timestamp);
        if ($signature === false) {
            return response()->json(['message' => 'Signature generation failed'], 500);
        }

        // Prepare request parameters for API call
        $requestParams = $this->buildRequestParams($request, $requestId, $nonce, $timestamp, $signKeyAlias, $signature);

        // Make API request
        return $this->makeApiRequest($requestParams, $signature);
    }

    /**
     * Build signature parameters in correct order, excluding empty optional fields
     */
    private function buildSignatureParams(Request $request, string $requestId, string $nonce, string $timestamp, string $signKeyAlias): string
    {
        $params = [
            'clientID' => $request->input('clientID'),
            'requestID' => $requestId,
            'nonce' => $nonce,
            'timestamp' => $timestamp,
            'signKeyAlias' => $signKeyAlias,
        ];

        // Add optional parameters if not empty
        $optionalParams = ['applicantBankCode', 'boName', 'boTransactionRefNo', 'purpose', 'requestType', 'segment'];
        foreach ($optionalParams as $param) {
            $value = $request->input($param);
            if (!empty($value)) {
                $params[$param] = $value;
            }
        }

        return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Generate SHA256 signature using GPG service
     */
    private function generateSignature(string $signatureParams, string $clientSlug, string $timestamp): string|false
    {
        try {
            $gpgService = new GpgService();
            $config = config('clients.' . $clientSlug . '.' . env('APP_ENV') . '.pgp');

            Log::info('Authorize Creation - Signing parameters', [
                'signatureParams' => $signatureParams,
                'timestamp' => $timestamp,
                'timestampDate' => date('Y-m-d H:i:s', $timestamp / 1000)
            ]);

            $signature = $gpgService->signWithSHA256(
                $signatureParams,
                $config['private_key'],
                $config['passphrase'],
                $config['fingerprint']
            );

            Log::info('Authorize Creation - Generated signature', [
                'signatureLength' => strlen($signature)
            ]);

            return $signature;

        } catch (\Throwable $e) {
            Log::error('Authorize Creation - Signature generation failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Build request parameters for API call
     */
    private function buildRequestParams(Request $request, string $requestId, string $nonce, string $timestamp, string $signKeyAlias, string $signature): array
    {
        $params = [
            'clientID' => $request->input('clientID'),
            'requestID' => $requestId,
            'nonce' => $nonce,
            'timestamp' => $timestamp,
            'signKeyAlias' => $signKeyAlias,
            'signature' => $signature,
        ];

        // Add optional parameters if provided
        $optionalParams = ['applicantBankCode', 'boName', 'boTransactionRefNo', 'purpose', 'requestType', 'segment'];
        foreach ($optionalParams as $param) {
            $value = $request->input($param);
            if (!empty($value)) {
                $params[$param] = $value;
            }
        }

        return $params;
    }

    /**
     * Make HTTP request to authorize creation API
     */
    private function makeApiRequest(array $requestParams, string $signature): JsonResponse
    {
        $apiConfig = config('abs.' . env('APP_ENV') . '.authorizeCreation');

        Log::info('Authorize Creation - API Request', [
            'url' => $apiConfig['api_url'],
            'params' => $requestParams
        ]);

        try {
            $response = Http::get($apiConfig['api_url'], $requestParams);
            $responseData = $response->json();
            $statusCode = $response->status();

            Log::info('Authorize Creation - API Response', [
                'status_code' => $statusCode,
                'response' => $responseData
            ]);

            if ($response->successful()) {
                return response()->json([
                    'message' => 'Authorize creation successful',
                    'data' => $responseData,
                    'signature' => $signature,
                ], $statusCode);
            }

            return response()->json([
                'message' => 'Authorize creation failed',
                'error' => $responseData,
                'status_code' => $statusCode
            ], $statusCode);

        } catch (\Throwable $e) {
            Log::error('Authorize Creation - API request failed', [
                'error' => $e->getMessage(),
                'url' => $apiConfig['api_url']
            ]);

            return response()->json([
                'message' => 'API request failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
