<?php
declare(strict_types=1);

namespace RidiPay\Tests;

use PHPUnit\Framework\TestCase;
use RidiPay\Library\Crypto;

class CryptoTest extends TestCase
{
    /**
     * @dataProvider keyAndMessageProvider
     *
     * @param string $key
     * @param string $message
     * @throws \Exception
     */
    public function testEncryptAndDecrypt(string $key, string $message)
    {
        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            $this->expectException(\SodiumException::class);
        }

        $encrypted_message = Crypto::encrypt($message, $key);
        $this->assertSame($message, Crypto::decrypt($encrypted_message, $key));
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function keyAndMessageProvider(): array
    {
        return [
            [random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES), uniqid()],
            [random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES - 1), uniqid()],
            [random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES + 1), uniqid()]
        ];
    }
}
