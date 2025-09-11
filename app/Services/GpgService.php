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
            // Create Crypt_GPG instance
            $gpg = new Crypt_GPG();
            
            // Set armor mode for ASCII output
            $gpg->setArmor(true);
            
            // Import the private key
            if (!is_readable($privateKeyPath)) {
                throw new \RuntimeException('Private key not readable at ' . $privateKeyPath);
            }
            
            $armoredKey = file_get_contents($privateKeyPath);
            $importResult = $gpg->importKey($armoredKey);
            
            if (empty($importResult['fingerprint'])) {
                throw new \RuntimeException('Failed to import private key');
            }
            
            $keyFingerprint = $keyFingerprint ?: $importResult['fingerprint'];
            
            // Add the signing key
            $gpg->addSignKey($keyFingerprint, $passphrase);
            
            // Sign the message with detached signature
            $signature = $gpg->sign($message, Crypt_GPG::SIGN_MODE_DETACH);
            
            if (!$signature || stripos($signature, 'BEGIN PGP SIGNATURE') === false) {
                throw new \RuntimeException('Signing returned invalid signature');
            }
            
            return $signature;
            
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
