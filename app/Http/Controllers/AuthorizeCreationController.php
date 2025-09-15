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
        $requestId = Str::uuid()->toString();
        $request->requestID = $request->input('requestID', $requestId);

        $validator = Validator::make($request->all(), [
            'client_slug' => 'required|min:11|max:11',
            'applicantBankCode' => 'nullable|min:1|max:35',
            'boName' => 'required|max:140',
            'boTransactionRefNo' => 'required|min:35|max:35',
            'clientID' => 'required|min:15|max:15',
            'purpose' => 'nullable|in:LOAN',
            'requestID' => 'required|min:36|max:36',
            'requestType' => 'required|in:Creation',
            'segment' => 'required|in:Retail',
            'nonce' => 'required|min:20|max:20',
            'Timestamp' => 'required',
            'signKeyAlias' => 'nullable',
            'signature' => 'required',
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
        $nonce = Str::random(20);
        $timestamp = (string) (strtotime('2024-01-01 00:00:00') * 1000);

        $signatureParams = 'clientID=' . $clientConfig['client_id']
            . '&requestID=' . $requestId
            . '&nonce=' . $nonce
            . '&timestamp=' . $timestamp
            . '&signKeyAlias=' . $request->string('signKeyAlias');

        // $signature = $gpgService->signWithSHA256($signatureParams, $privateKeyPath, $passphrase, $keyFingerprint);
        $signature = 'test';

        return response()->json([
            'message' => 'Authorize creation successful',
            'signature' => $signature,
        ]);
    }
}
