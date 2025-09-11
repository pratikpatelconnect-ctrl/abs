<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Crypt_GPG;

class GpgService
{
    /**
     * Sign a message with a private PGP key using Crypt_GPG package.
     * 
     * @param string $message The message to sign
     * @param string $privateKeyPath Path to the private key file
     * @param string $passphrase Passphrase for the private key
     * @param string $keyFingerprint Key fingerprint or ID
     * @return string ASCII-armored detached signature
     * @throws \RuntimeException on failure
     */
    public function sign(string $message, string $privateKeyPath, string $passphrase, string $keyFingerprint = null): string
    {
        try {
            // Create temporary isolated keyring directory
            $tempHome = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gpg-' . bin2hex(random_bytes(6));
            if (!mkdir($tempHome, 0700, true) && !is_dir($tempHome)) {
                throw new \RuntimeException('Failed to create temp GNUPGHOME');
            }
            
            // Ensure the directory is writable
            if (!is_writable($tempHome)) {
                throw new \RuntimeException('Temp GNUPGHOME directory is not writable');
            }

            try {
                // Create Crypt_GPG instance with temporary keyring
                $gpg = new Crypt_GPG([
                    'homedir' => $tempHome,
                    'armor' => true  // Enable ASCII armor output
                ]);
                
                // Import the private key
                if (!is_readable($privateKeyPath)) {
                    throw new \RuntimeException('Private key not readable at ' . $privateKeyPath);
                }
                
                $armoredKey = file_get_contents($privateKeyPath);
                
                // Import the key using the public method
                $importResult = $gpg->importKey($armoredKey);
                
                if (empty($importResult['fingerprint'])) {
                    throw new \RuntimeException('Failed to import private key');
                }
                
                $keyFingerprint = $keyFingerprint ?: $importResult['fingerprint'];
                
                // Add the signing key
                $gpg->addSignKey($keyFingerprint, $passphrase);
                
                // Sign the message with detached signature
                $signature = $gpg->sign($message, Crypt_GPG::SIGN_MODE_DETACHED);
                
                if (!$signature || stripos($signature, 'BEGIN PGP SIGNATURE') === false) {
                    throw new \RuntimeException('Signing returned invalid signature');
                }
                
                return $signature;
                
            } finally {
                // Cleanup temporary keyring
                try {
                    array_map('unlink', glob($tempHome . DIRECTORY_SEPARATOR . '*') ?: []);
                    @rmdir($tempHome);
                } catch (\Throwable $e) {
                    // Ignore cleanup errors
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Crypt_GPG signing failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            throw new \RuntimeException('PGP signing failed: ' . $e->getMessage());
        }
    }

}
