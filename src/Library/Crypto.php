<?php
declare(strict_types=1);

namespace RidiPay\Library;

class Crypto
{
    private const BLOCK_SIZE = 16;

    /**
     * @param string $message
     * @param string $key
     * @return string
     * @throws \Exception
     */
    public static function encrypt(string $message, string $key): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $padded_message = sodium_pad($message, self::BLOCK_SIZE);
        $cipher = base64_encode($nonce . sodium_crypto_secretbox($padded_message, $nonce, $key));

        sodium_memzero($message);
        sodium_memzero($key);

        return $cipher;
    }

    /**
     * @param string $encrypted
     * @param string $key
     * @return string
     * @throws \Exception
     */
    public static function decrypt(string $encrypted, string $key): string
    {
        $decoded = base64_decode($encrypted);
        if ($decoded === false) {
            throw new \Exception('Decoding failed');
        }

        if (mb_strlen($decoded, '8bit') < (SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES)) {
            throw new \Exception('The message was truncated');
        }

        $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');

        $decrypted_padded_message = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        $message = sodium_unpad($decrypted_padded_message, self::BLOCK_SIZE);
        if ($message === false) {
            throw new \Exception('The message was tampered with in transit');
        }

        sodium_memzero($ciphertext);
        sodium_memzero($key);

        return $message;
    }
}
