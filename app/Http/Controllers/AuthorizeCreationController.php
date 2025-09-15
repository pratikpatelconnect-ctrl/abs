<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Str;

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

        // Use actual request values for signature parameters
        $signatureParams = 'clientID=' . $request->input('clientID')
            . '&requestID=' . $requestId
            . '&nonce=' . $nonce
            . '&timestamp=' . $timestamp
            . '&signKeyAlias=' . $signKeyAlias;

        // $signature = $gpgService->signWithSHA256($signatureParams, $privateKeyPath, $passphrase, $keyFingerprint);
        $signature = 'test';

        return response()->json([
            'message' => 'Authorize creation successful',
            'signature' => $signature,
        ]);
    }
}
