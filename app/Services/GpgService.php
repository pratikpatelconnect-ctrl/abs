<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class GpgService
{
    /**
     * Sign a message with a private PGP key using a clean interface.
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
        // Try PECL gnupg extension first
        if (class_exists('gnupg') && defined('GNUPG_SIG_MODE_DETACH')) {
            try {
                return $this->signWithGnupgExtension($message, $privateKeyPath, $passphrase);
            } catch (\RuntimeException $e) {
                // If gnupg extension fails, fall back to CLI
                Log::warning('gnupg extension failed, falling back to CLI', ['error' => $e->getMessage()]);
            }
        }

        // Check if CLI gpg is available before attempting to use it
        if (!$this->isGpgCommandAvailable()) {
            throw new \RuntimeException(
                'Neither the gnupg PHP extension nor the gpg command is available. ' .
                'Please install either the gnupg PHP extension or GnuPG command-line tools.'
            );
        }

        // Fallback to CLI gpg
        return $this->signWithCliGpg($message, $privateKeyPath, $passphrase, $keyFingerprint);
    }

    /**
     * Sign using the PECL gnupg extension
     */
    private function signWithGnupgExtension(string $message, string $privateKeyPath, string $passphrase): string
    {
        if (!is_readable($privateKeyPath)) {
            throw new \RuntimeException('Private key not readable at ' . $privateKeyPath);
        }

        $armoredKey = file_get_contents($privateKeyPath);
        
        // Check if gnupg extension is available
        if (!class_exists('gnupg') || !defined('GNUPG_SIG_MODE_DETACH')) {
            throw new \RuntimeException('gnupg extension not available');
        }
        
        $gpg = new \gnupg();
        $gpg->setarmor(true);
        $gpg->setsignmode(GNUPG_SIG_MODE_DETACH);

        // Import the key
        $import = $gpg->import($armoredKey);
        if (empty($import['fingerprint'])) {
            throw new \RuntimeException('Key import failed');
        }

        // Add signing key
        if (!$gpg->addsignkey($import['fingerprint'], $passphrase)) {
            throw new \RuntimeException('Unable to add sign key (check passphrase)');
        }

        $signature = $gpg->sign($message);
        if (!$signature || stripos($signature, 'BEGIN PGP SIGNATURE') === false) {
            throw new \RuntimeException('Signing returned invalid signature');
        }

        return $signature;
    }

    /**
     * Sign using CLI gpg command
     */
    private function signWithCliGpg(string $message, string $privateKeyPath, string $passphrase, string $keyFingerprint = null): string
    {
        if (!is_readable($privateKeyPath)) {
            throw new \RuntimeException('Private key not readable at ' . $privateKeyPath);
        }

        // Create temporary isolated keyring
        $tempHome = sys_get_temp_dir() . '/gpg-' . bin2hex(random_bytes(6));
        if (!mkdir($tempHome, 0700, true) && !is_dir($tempHome)) {
            throw new \RuntimeException('Failed to create temp GNUPGHOME');
        }

        $originalHome = getenv('GNUPGHOME');
        putenv('GNUPGHOME=' . $tempHome);

        try {
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

            // Get key fingerprint if not provided
            if (!$keyFingerprint) {
                $listCmd = 'gpg --batch --list-secret-keys --with-colons 2>/dev/null';
                exec($listCmd, $keyList, $listCode);
                if ($listCode === 0 && !empty($keyList)) {
                    foreach ($keyList as $line) {
                        if (strpos($line, 'sec:') === 0) {
                            $parts = explode(':', $line);
                            if (!empty($parts[4])) {
                                $keyFingerprint = $parts[4];
                                break;
                            }
                        }
                    }
                }
            }

            if (!$keyFingerprint) {
                throw new \RuntimeException('Could not determine key fingerprint');
            }

            // Create temporary files
            $msgPath = tempnam(sys_get_temp_dir(), 'pgpmsg_');
            $sigPath = $msgPath . '.asc';
            file_put_contents($msgPath, $message);

            // Sign the message
            $signCmd = sprintf(
                'gpg --batch --yes --pinentry-mode loopback --passphrase %s --local-user %s --armor --detach-sign -o %s %s 2>&1',
                escapeshellarg($passphrase),
                escapeshellarg($keyFingerprint),
                escapeshellarg($sigPath),
                escapeshellarg($msgPath)
            );

            exec($signCmd, $out, $code);
            $signature = is_file($sigPath) ? file_get_contents($sigPath) : null;

            // Cleanup temp files
            @unlink($msgPath);
            @unlink($sigPath);

            if ($code !== 0 || !$signature || stripos($signature, 'BEGIN PGP SIGNATURE') === false) {
                throw new \RuntimeException('gpg signing failed: ' . implode("\n", $out));
            }

            return $signature;

        } finally {
            // Restore original GNUPGHOME
            if ($originalHome !== false) {
                putenv('GNUPGHOME=' . $originalHome);
            } else {
                putenv('GNUPGHOME');
            }

            // Cleanup temp keyring
            try {
                array_map('unlink', glob($tempHome . '/*') ?: []);
                @rmdir($tempHome);
            } catch (\Throwable $e) {
                // Ignore cleanup errors
            }
        }
    }

    /**
     * Check if gpg command is available on the system
     */
    private function isGpgCommandAvailable(): bool
    {
        $output = [];
        $returnCode = 0;
        
        // Try to run gpg --version to check if it's available
        exec('gpg --version 2>&1', $output, $returnCode);
        
        return $returnCode === 0;
    }
}
