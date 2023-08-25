<?php

namespace Opencart\System\Library;
class Signature
{
    /**
     * @param string $data
     * @param string $signature
     * @param string $key
     * @return bool
     */
    public static function checkSignature(string $data, string $signature, string $key): bool
    {
        return base64_encode(hash_hmac('sha256', $data, $key, true)) == $signature;
    }
}
