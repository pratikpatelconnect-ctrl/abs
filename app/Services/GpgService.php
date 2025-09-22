<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Crypt_GPG;

class GpgService
{
    /**
     * Encrypt a message with a recipient's public PGP key using Crypt_GPG.
     * Returns ASCII-armored ciphertext.
     *
     * @param string $message The plaintext message to encrypt
     * @param string $publicKeyPath Path to the recipient public key file
     * @param string|null $keyFingerprint Optional fingerprint/ID of the recipient key
     * @return string ASCII-armored ciphertext
     */
    public function encrypt(string $message, string $publicKeyPath, string $keyFingerprint = null): string
    {
        try {
            $tempHome = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gpg-' . bin2hex(random_bytes(6));
            if (!mkdir($tempHome, 0700, true) && !is_dir($tempHome)) {
                throw new \RuntimeException('Failed to create temp GNUPGHOME');
            }

            if (!is_writable($tempHome)) {
                throw new \RuntimeException('Temp GNUPGHOME directory is not writable');
            }

            try {
                $gpg = new Crypt_GPG([
                    'homedir' => $tempHome,
                    'armor' => true,
                ]);

                if (!is_readable($publicKeyPath)) {
                    throw new \RuntimeException('Public key not readable at ' . $publicKeyPath);
                }

                $armoredKey = file_get_contents($publicKeyPath);
                $importResult = $gpg->importKey($armoredKey);

                if (empty($importResult['fingerprint'])) {
                    throw new \RuntimeException('Failed to import public key');
                }

                $recipientFingerprint = $keyFingerprint ?: $importResult['fingerprint'];
                $gpg->addEncryptKey($recipientFingerprint);

                $ciphertext = $gpg->encrypt($message);
                if (!$ciphertext || stripos($ciphertext, 'BEGIN PGP MESSAGE') === false) {
                    throw new \RuntimeException('Encryption returned invalid ciphertext');
                }

                return $ciphertext;

            } finally {
                try {
                    array_map('unlink', glob($tempHome . DIRECTORY_SEPARATOR . '*') ?: []);
                    @rmdir($tempHome);
                } catch (\Throwable $e) {
                    // Ignore cleanup errors
                }
            }

        } catch (\Exception $e) {
            Log::error('Crypt_GPG encryption failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            throw new \RuntimeException('PGP encryption failed: ' . $e->getMessage());
        }
    }

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

    /**
     * Sign a message using SHA256 with PGP key (as per API documentation)
     * 
     * @param string $message The message to sign
     * @param string $privateKeyPath Path to the private key file
     * @param string $passphrase Passphrase for the private key
     * @param string $keyFingerprint Key fingerprint or ID
     * @return string ASCII-armored detached signature
     * @throws \RuntimeException on failure
     */
    public function signWithSHA256(string $message, string $privateKeyPath, string $passphrase, string $keyFingerprint = null): string
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
                
                // First, create SHA256 hash of the message
                $sha256Hash = hash('sha256', $message);
                
                // Then sign the SHA256 hash with PGP (detached signature)
                $signature = $gpg->sign($sha256Hash, Crypt_GPG::SIGN_MODE_DETACHED);
                
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
            Log::error('SHA256 signing failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            throw new \RuntimeException('SHA256 signing failed: ' . $e->getMessage());
        }
    }

}
