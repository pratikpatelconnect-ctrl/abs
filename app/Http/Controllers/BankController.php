<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Str;

class BankController extends Controller
{
    function index(Request $request): JsonResponse
    {
        $client_slug = $request->client_slug;
        $validator = Validator::make($request->all(), [
            'client_slug' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $client_config = config('clients.' . $client_slug);
        $client_config = $client_config[env('APP_ENV')];
        $aggregator_key_alias = "ABS_eGIRO";
        $request_id = Str::uuid()->toString();
        $nonce = Str::random(20);
        $timestamp = now()->format('YmdHis');
        $signature = hash_hmac('sha256', "clientID=" . $client_config['client_id'] . "&requestID=" . $request_id . "&nonce=" . $nonce . "&timestamp=" . $timestamp . "&aggregatorKeyAlias=" . $aggregator_key_alias, $client_config['x-api-key']);
        $request_header = [
            'clientID' => $client_config['client_id'],
            'requestID' => $request_id,
            'nonce' => $nonce,
            'timestamp' => $timestamp,
            'aggregatorKeyAlias' => $aggregator_key_alias,
            'signature' => $signature,
        ];
        $response = Http::withHeaders($request_header)->get(config('abs.' . env('APP_ENV') . '.banks.api_url'));
        $data = $response->json();
        return response()->json([
            'message' => 'Bank list',
            'data' => $data
        ]);
    }
}
