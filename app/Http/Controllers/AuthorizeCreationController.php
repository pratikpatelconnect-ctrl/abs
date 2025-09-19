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
        $signatureParams = [];

        // Required parameters
        $signatureParams[] = 'clientID=' . $request->input('clientID');
        $signatureParams[] = 'requestID=' . $requestId;
        $signatureParams[] = 'nonce=' . $nonce;
        $signatureParams[] = 'timestamp=' . $timestamp;
        $signatureParams[] = 'signKeyAlias=' . $signKeyAlias;

        // Optional parameters - only include if not empty
        if (!empty($request->input('applicantBankCode'))) {
            $signatureParams[] = 'applicantBankCode=' . $request->input('applicantBankCode');
        }
        if (!empty($request->input('boName'))) {
            $signatureParams[] = 'boName=' . $request->input('boName');
        }
        if (!empty($request->input('boTransactionRefNo'))) {
            $signatureParams[] = 'boTransactionRefNo=' . $request->input('boTransactionRefNo');
        }
        if (!empty($request->input('purpose'))) {
            $signatureParams[] = 'purpose=' . $request->input('purpose');
        }
        if (!empty($request->input('requestType'))) {
            $signatureParams[] = 'requestType=' . $request->input('requestType');
        }
        if (!empty($request->input('segment'))) {
            $signatureParams[] = 'segment=' . $request->input('segment');
        }

        $signatureParamsString = implode('&', $signatureParams);

        // Initialize GPG service and get client configuration
        try {
            $gpgService = new GpgService();
            $privateKeyPath = config('clients.' . $clientSlug . '.' . env('APP_ENV') . '.pgp.private_key');
            $passphrase = config('clients.' . $clientSlug . '.' . env('APP_ENV') . '.pgp.passphrase');
            $keyFingerprint = config('clients.' . $clientSlug . '.' . env('APP_ENV') . '.pgp.fingerprint');

            // Log the parameters being signed for debugging
            Log::info('Authorize Creation - Signing parameters', [
                'signatureParams' => $signatureParamsString,
                'privateKeyPath' => $privateKeyPath,
                'hasPassphrase' => !empty($passphrase),
                'keyFingerprint' => $keyFingerprint,
                'timestamp' => $timestamp,
                'timestampDate' => date('Y-m-d H:i:s', $timestamp / 1000)
            ]);

            // Generate SHA256 signature
            $signature = $gpgService->signWithSHA256($signatureParamsString, $privateKeyPath, $passphrase, $keyFingerprint);

            // Log the generated signature for debugging
            Log::info('Authorize Creation - Generated signature', [
                'signatureLength' => strlen($signature),
                'signatureStart' => substr($signature, 0, 50) . '...',
                'signatureEnd' => '...' . substr($signature, -50)
            ]);

        } catch (\Throwable $e) {
            Log::error('Authorize Creation - Signature generation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Signature generation failed',
                'error' => $e->getMessage()
            ], 500);
        }

        // Prepare request parameters for API call
        $requestParams = [
            'clientID' => $request->input('clientID'),
            'requestID' => $requestId,
            'nonce' => $nonce,
            'timestamp' => $timestamp,
            'signKeyAlias' => $signKeyAlias,
            'signature' => $signature,
        ];

        // Add optional parameters if provided
        if (!empty($request->input('applicantBankCode'))) {
            $requestParams['applicantBankCode'] = $request->input('applicantBankCode');
        }
        if (!empty($request->input('boName'))) {
            $requestParams['boName'] = $request->input('boName');
        }
        if (!empty($request->input('boTransactionRefNo'))) {
            $requestParams['boTransactionRefNo'] = $request->input('boTransactionRefNo');
        }
        if (!empty($request->input('purpose'))) {
            $requestParams['purpose'] = $request->input('purpose');
        }
        if (!empty($request->input('requestType'))) {
            $requestParams['requestType'] = $request->input('requestType');
        }
        if (!empty($request->input('segment'))) {
            $requestParams['segment'] = $request->input('segment');
        }

        // Get API configuration
        $apiConfig = config('abs.' . env('APP_ENV') . '.authorizeCreation');
        $apiUrl = $apiConfig['api_url'];
        $apiMethod = $apiConfig['method'];

        // Log the request parameters for debugging
        Log::info('Authorize Creation - API Request', [
            'url' => $apiUrl,
            'method' => $apiMethod,
            'params' => $requestParams
        ]);

        // Make HTTP request to authorize creation API
        try {
            $response = Http::get($apiUrl, $requestParams);

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
            } else {
                return response()->json([
                    'message' => 'Authorize creation failed',
                    'error' => $responseData,
                    'status_code' => $statusCode
                ], $statusCode);
            }

        } catch (\Throwable $e) {
            Log::error('Authorize Creation - API request failed', [
                'error' => $e->getMessage(),
                'url' => $apiUrl
            ]);

            return response()->json([
                'message' => 'API request failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
