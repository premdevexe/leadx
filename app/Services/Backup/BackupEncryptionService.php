<?php

namespace App\Services\Backup;

class BackupEncryptionService
{
    private const CIPHER = 'aes-256-cbc';

    /**
     * Get encryption key (binary 32 bytes).
     */
    protected function getKey(): string
    {
        $rawKey = config('leadx.backup.encryption_key') ?? config('app.key');
        if (str_starts_with($rawKey, 'base64:')) {
            $rawKey = base64_decode(substr($rawKey, 7));
        }

        return hash('sha256', $rawKey, true);
    }

    /**
     * Encrypt a source file to a destination file using AES-256-CBC.
     * Output format: [16 bytes IV][ciphertext][32 bytes HMAC-SHA256]
     */
    public function encryptFile(string $sourcePath, string $destinationPath): string
    {
        if (! file_exists($sourcePath)) {
            throw new \InvalidArgumentException("Source file does not exist: {$sourcePath}");
        }

        $key = $this->getKey();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER));

        $plaintext = file_get_contents($sourcePath);
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            throw new \RuntimeException("Encryption failed.");
        }

        $payload = $iv . $ciphertext;
        $hmac = hash_hmac('sha256', $payload, $key, true);

        file_put_contents($destinationPath, $payload . $hmac);

        return hash_file('sha256', $destinationPath);
    }

    /**
     * Decrypt an encrypted file to a target destination.
     */
    public function decryptFile(string $sourcePath, string $destinationPath): bool
    {
        if (! file_exists($sourcePath)) {
            throw new \InvalidArgumentException("Encrypted source file not found: {$sourcePath}");
        }

        $data = file_get_contents($sourcePath);
        $fileSize = strlen($data);

        if ($fileSize < 48) {
            throw new \RuntimeException("Invalid encrypted file format (too small).");
        }

        $key = $this->getKey();
        $iv = substr($data, 0, 16);
        $expectedHmac = substr($data, -32);
        $payload = substr($data, 0, -32);
        $ciphertext = substr($payload, 16);

        $actualHmac = hash_hmac('sha256', $payload, $key, true);
        if (! hash_equals($expectedHmac, $actualHmac)) {
            @unlink($destinationPath);
            throw new \RuntimeException("Backup integrity failure: HMAC authentication verification failed.");
        }

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($plaintext === false) {
            @unlink($destinationPath);
            throw new \RuntimeException("Decryption failed.");
        }

        file_put_contents($destinationPath, $plaintext);

        return true;
    }

    /**
     * Verify whether an encrypted archive is intact and untampered without writing plaintext to disk.
     */
    public function verifyEncryptedFile(string $filePath): bool
    {
        if (! file_exists($filePath)) {
            return false;
        }

        $data = file_get_contents($filePath);
        $fileSize = strlen($data);
        if ($fileSize < 48) {
            return false;
        }

        $key = $this->getKey();
        $expectedHmac = substr($data, -32);
        $payload = substr($data, 0, -32);

        $actualHmac = hash_hmac('sha256', $payload, $key, true);

        return hash_equals($expectedHmac, $actualHmac);
    }
}
