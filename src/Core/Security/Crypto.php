<?php
declare(strict_types=1);
namespace Hostider\Core\Security;
final readonly class Crypto
{
    public function __construct(#[\SensitiveParameter] private string $key, private string $keyId = 'v1')
    {
        if (strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES || !preg_match('/^[a-zA-Z0-9_-]{1,24}$/D', $keyId)) {
            throw new \InvalidArgumentException('Invalid encryption key');
        }
    }
    public function encrypt(#[\SensitiveParameter] string $plaintext, string $context): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        return $this->keyId . ':' . base64_encode($nonce . sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $this->keyId . ':' . $context, $nonce, $this->key));
    }
    public function decrypt(#[\SensitiveParameter] string $envelope, string $context): string
    {
        [$id, $encoded] = array_pad(explode(':', $envelope, 2), 2, '');
        $raw = base64_decode($encoded, true);
        if ($id !== $this->keyId || $raw === false || strlen($raw) < 40) {
            throw new \RuntimeException('Invalid encrypted envelope');
        }
        $value = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($raw, 24), $id . ':' . $context, substr($raw, 0, 24), $this->key);
        if ($value === false) {
            throw new \RuntimeException('Ciphertext authentication failed');
        }
        return $value;
    }
}
