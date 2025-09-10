<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;           // <- correct import
use Carbon\CarbonImmutable;

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

        // 3) Build request fields
        $aggregatorKeyAlias = 'ABS_eGIRO';
        $requestId = Str::uuid()->toString();
        $nonce = Str::random(20);
        // epoch millis (e.g., "1725950062123"); Carbon ensures portability
        $timestamp = CarbonImmutable::now()->format('Uv');

        $signatureParams = 'clientID=' . $clientConfig['client_id']
            . '&requestID=' . $requestId
            . '&nonce=' . $nonce
            . '&timestamp=' . $timestamp
            . '&aggregatorKeyAlias=' . $aggregatorKeyAlias;

        // 4) Generate PGP signature (ASCII-armored, detached)
        try {
            $signature = $this->signWithPGP($signatureParams, $clientSlug);
        } catch (\Throwable $e) {
            Log::error('PGP signing failed', ['error' => $e->getMessage()]);
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
            'aggregatorKeyAlias' => $aggregatorKeyAlias,
            'signature' => $signature, // -----BEGIN PGP SIGNATURE----- ...
        ];

        $response = Http::get(config('abs.' . env('APP_ENV') . '.banks.api_url'), $requestParams);

        if ($response->failed()) {
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

    /**
     * Sign a message with a private PGP key.
     * Prefers the gnupg PHP extension; falls back to gpg CLI.
     *
     * @throws \RuntimeException on failure
     */
    private function signWithPGP(string $message, string $client_slug): string
    {
        // Try PECL gnupg first
        if (class_exists(\gnupg::class)) {
            $privateKeyPath = config('clients.' . $client_slug . '.' . env('APP_ENV') . '.pgp.private_key');
            $passphrase = config('clients.' . $client_slug . '.' . env('APP_ENV') . '.pgp.passphrase');

            if (!is_readable($privateKeyPath)) {
                throw new \RuntimeException('Private key not readable at ' . $privateKeyPath);
            }
            $armoredKey = file_get_contents($privateKeyPath);

            $gpg = new \gnupg();
            $gpg->setarmor(true);                          // ASCII-armored output
            $gpg->setsignmode(GNUPG_SIG_MODE_DETACH);      // detached signature

            // Import once per process (idempotent)
            $import = $gpg->import($armoredKey);
            if (empty($import['fingerprint'])) {
                throw new \RuntimeException('Key import failed');
            }

            // Unlock & add signing key
            if (!$gpg->addsignkey($import['fingerprint'], $passphrase)) {
                throw new \RuntimeException('Unable to add sign key (passphrase?)');
            }

            $sig = $gpg->sign($message);
            if (!$sig || stripos($sig, 'BEGIN PGP SIGNATURE') === false) {
                throw new \RuntimeException('Signing returned invalid signature');
            }
            return $sig; // ASCII-armored detached signature
        }

        // Fallback: CLI gpg
        $privateKeyPath = config('clients.' . $client_slug . '.' . env('APP_ENV') . '.pgp.private_key');
        $passphrase = config('clients.' . $client_slug . '.' . env('APP_ENV') . '.pgp.passphrase');
        $signFpr = config('clients.' . $client_slug . '.' . env('APP_ENV') . '.pgp.fingerprint'); // key fingerprint or keyid

        if (!is_readable($privateKeyPath)) {
            throw new \RuntimeException('Private key not readable at ' . $privateKeyPath);
        }

        // Import key into a temporary isolated keyring to avoid global keyring mutation
        $tempHome = sys_get_temp_dir() . '/gpg-' . bin2hex(random_bytes(6));
        if (!mkdir($tempHome, 0700, true) && !is_dir($tempHome)) {
            throw new \RuntimeException('Failed to create temp GNUPGHOME');
        }
        putenv('GNUPGHOME=' . $tempHome);

        // Import private key
        $importCmd = sprintf(
            'gpg --batch --yes --pinentry-mode loopback --passphrase %s --import %s 2>&1',
            escapeshellarg($passphrase),
            escapeshellarg($privateKeyPath)
        );
        exec($importCmd, $outImport, $codeImport);
        if ($codeImport !== 0) {
            throw new \RuntimeException('gpg key import failed: ' . implode("\n", $outImport));
        }

        // Write message to a temp file
        $msgPath = tempnam(sys_get_temp_dir(), 'pgpmsg_');
        $sigPath = $msgPath . '.asc';
        file_put_contents($msgPath, $message);

        // Sign (detached, ASCII-armored)
        $cmd = 'gpg --batch --yes --pinentry-mode loopback'
            . ' --passphrase ' . escapeshellarg($passphrase)
            . ' --local-user ' . escapeshellarg($signFpr)
            . ' --armor --detach-sign'
            . ' -o ' . escapeshellarg($sigPath)
            . ' ' . escapeshellarg($msgPath) . ' 2>&1';

        exec($cmd, $out, $code);
        $sig = is_file($sigPath) ? file_get_contents($sigPath) : null;

        // Cleanup
        @unlink($msgPath);
        @unlink($sigPath);
        // wipe the temp GNUPG home
        try {
            array_map('unlink', glob($tempHome . '/*') ?: []);
            @rmdir($tempHome);
        } catch (\Throwable $e) {
            // ignore
        }

        if ($code !== 0 || !$sig || stripos($sig, 'BEGIN PGP SIGNATURE') === false) {
            throw new \RuntimeException('gpg signing failed: ' . implode("\n", $out));
        }

        return $sig;
    }
}
