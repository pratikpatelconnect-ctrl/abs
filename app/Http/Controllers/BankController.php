<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;           // <- correct import
use Carbon\CarbonImmutable;
use App\Services\GpgService;

class BankController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // 1) Validate input
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

        // 2) Resolve client config
        $clientConfig = config('clients.' . $clientSlug);
        if (!is_array($clientConfig) || empty($clientConfig[env('APP_ENV')])) {
            return response()->json([
                'message' => 'Invalid client configuration',
            ], 422);
        }
        $clientConfig = $clientConfig[env('APP_ENV')];

        // 3) Build request fields according to API documentation
        $signKeyAlias = 'KEY1'; // Optional parameter as per API docs
        $requestId = Str::uuid()->toString(); // UUID, length = 36
        $nonce = Str::random(20); // 20 digits random number
        // epoch millis - use a reasonable timestamp for testing
        // Note: Using a 2024 timestamp to avoid future timestamp issues with system clock
        $timestamp = (string)(strtotime('2024-01-01 00:00:00') * 1000); // 13 digits
        
        // Validate generated parameters according to API specs
        if (strlen($clientConfig['client_id']) !== 15) {
            return response()->json([
                'message' => 'Invalid client ID length. Must be exactly 15 characters.',
            ], 422);
        }
        
        if (strlen($requestId) !== 36) {
            return response()->json([
                'message' => 'Invalid request ID length. Must be exactly 36 characters (UUID).',
            ], 422);
        }
        
        if (strlen($nonce) !== 20) {
            return response()->json([
                'message' => 'Invalid nonce length. Must be exactly 20 characters.',
            ], 422);
        }
        
        if (strlen($timestamp) !== 13) {
            return response()->json([
                'message' => 'Invalid timestamp length. Must be exactly 13 characters.',
            ], 422);
        }

        // Build signature parameters in the correct order
        $signatureParams = 'clientID=' . $clientConfig['client_id']
            . '&requestID=' . $requestId
            . '&nonce=' . $nonce
            . '&timestamp=' . $timestamp
            . '&signKeyAlias=' . $signKeyAlias;

        // 4) Generate SHA256 signature as per API documentation
        try {
            $gpgService = new GpgService();
            $privateKeyPath = config('clients.' . $clientSlug . '.' . env('APP_ENV') . '.pgp.private_key');
            $passphrase = config('clients.' . $clientSlug . '.' . env('APP_ENV') . '.pgp.passphrase');
            $keyFingerprint = config('clients.' . $clientSlug . '.' . env('APP_ENV') . '.pgp.fingerprint');
            
            // Log the parameters being signed for debugging
            Log::info('Signing parameters', [
                'signatureParams' => $signatureParams,
                'privateKeyPath' => $privateKeyPath,
                'hasPassphrase' => !empty($passphrase),
                'keyFingerprint' => $keyFingerprint,
                'timestamp' => $timestamp,
                'timestampDate' => date('Y-m-d H:i:s', $timestamp / 1000)
            ]);
            
            // Use SHA256 signing as specified in API documentation
            $signature = $gpgService->signWithSHA256($signatureParams, $privateKeyPath, $passphrase, $keyFingerprint);
            
            // Log the generated signature for debugging
            Log::info('Generated signature', [
                'signatureLength' => strlen($signature),
                'signatureStart' => substr($signature, 0, 50) . '...',
                'signatureEnd' => '...' . substr($signature, -50)
            ]);
            
        } catch (\Throwable $e) {
            Log::error('Signature generation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Signature generation failed',
            ], 500);
        }

        // 5) Call API
        $requestParams = [
            'clientID' => $clientConfig['client_id'],
            'requestID' => $requestId,
            'nonce' => $nonce,
            'timestamp' => $timestamp,
            'signKeyAlias' => $signKeyAlias,
            'signature' => $signature, // -----BEGIN PGP SIGNATURE----- ...
        ];

        // Log the request parameters for debugging
        Log::info('Making API request', [
            'url' => config('abs.' . env('APP_ENV') . '.banks.api_url'),
            'params' => $requestParams
        ]);

        $response = Http::get(config('abs.' . env('APP_ENV') . '.banks.api_url'), $requestParams);

        if ($response->failed()) {
            Log::error('API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers()
            ]);
            
            return response()->json([
                'message' => 'Upstream request failed',
                'error' => $response->body(),
            ], $response->status() ?: 502);
        }

        return response()->json([
            'message' => 'Bank list',
            'data' => $response->json(),
        ]);
    }

}
