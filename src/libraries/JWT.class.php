<?php

/**
 * jwt 令牌
 */
class JWT {

    public static $leeway = 0;
    public static $timestamp = null;
    public static $supported_algs = [
        'HS256' => ['hash_hmac', 'SHA256'],
        'HS512' => ['hash_hmac', 'SHA512'],
        'HS384' => ['hash_hmac', 'SHA384'],
        'RS256' => ['openssl', 'SHA256'],
        'RS384' => ['openssl', 'SHA384'],
        'RS512' => ['openssl', 'SHA512'],
    ];

    /**
     * 解码 jwt token
     * @param string $jwt       jwt token
     * @param type $key         密码
     * @param array $allowed_algs
     * @return boolean / object
     */
    public static function decode($jwt, $key = null, array $allowed_algs = ['HS256']) {
        $timestamp = is_null(static::$timestamp) ? time() : static::$timestamp;

        if (empty($key)) {
            $key = Config::get('auth_key');
        }
        $tks = explode('.', $jwt);
        if (count($tks) != 3) {
            return false;
        }
        list($headb64, $bodyb64, $cryptob64) = $tks;
        if (null === ($header = static::jsonDecode(static::urlsafeB64Decode($headb64)))) {
            // Invalid header encoding
            return false;
        }
        if (null === $payload = static::jsonDecode(static::urlsafeB64Decode($bodyb64))) {
            // Invalid claims encoding
            return false;
        }
        if (false === ($sig = static::urlsafeB64Decode($cryptob64))) {
            // Invalid signature encoding
            return false;
        }
        if (empty($header->alg)) {
            // Empty algorithm
            return false;
        }
        if (empty(static::$supported_algs[$header->alg])) {
            // Algorithm not supported
            return false;
        }
        if (!in_array($header->alg, $allowed_algs)) {
            // Algorithm not allowed
            return false;
        }
        if (is_array($key) || $key instanceof \ArrayAccess) {
            if (isset($header->kid)) {
                if (!isset($key[$header->kid])) {
                    // "kid" invalid, unable to lookup correct key
                    return false;
                }
                $key = $key[$header->kid];
            } else {
                // "kid" empty, unable to lookup correct key
                return false;
            }
        }

        if (!static::verify("$headb64.$bodyb64", $sig, $key, $header->alg)) {
            // Signature verification failed
            return false;
        }


        if (isset($payload->nbf) && $payload->nbf > ($timestamp + static::$leeway)) {
            //  'Cannot handle token prior to ' . date(DateTime::ISO8601, $payload->nbf)
            return false;
        }

        if (isset($payload->iat) && $payload->iat > ($timestamp + static::$leeway)) {
            // 'Cannot handle token prior to ' . date(DateTime::ISO8601, $payload->iat)
            return false;
        }

        // 检查 token 是否已经过期
        if (isset($payload->exp) && ($timestamp - static::$leeway) >= $payload->exp) {
            /* Expired token */
            return false;
        }

        return $payload;
    }

    /**
     * 编码数组为 jwt token
     * @param type $payload 数组/对象
     * @param type $key     密码
     * @param type $alg
     * @param type $keyId
     * @param type $head
     * @return type
     */
    public static function encode($payload, $key = null, $alg = 'HS256', $keyId = null, $head = null) {
        if (empty($key)) {
            $key = Config::get('auth_key');
        }
        $header = array('typ' => 'JWT', 'alg' => $alg);
        if ($keyId !== null) {
            $header['kid'] = $keyId;
        }
        if (isset($head) && is_array($head)) {
            $header = array_merge($head, $header);
        }
        $segments = array();
        $segments[] = static::urlsafeB64Encode(static::jsonEncode($header));
        $segments[] = static::urlsafeB64Encode(static::jsonEncode($payload));
        $signing_input = implode('.', $segments);

        $signature = static::sign($signing_input, $key, $alg);
        $segments[] = static::urlsafeB64Encode($signature);

        return implode('.', $segments);
    }

    /**
     * 签名
     * @param type $msg             信息
     * @param type $key             密钥
     * @param type $alg            支持 HS256、HS384、HS512、RS256
     * @return string
     * @throws DomainException
     */
    public static function sign($msg, $key, $alg = 'HS256') {
        if (empty(static::$supported_algs[$alg])) {
            // Algorithm not supported
            return false;
        }
        list($function, $algorithm) = static::$supported_algs[$alg];
        switch ($function) {
            case 'hash_hmac':
                return hash_hmac($algorithm, $msg, $key, true);
            case 'openssl':
                $signature = '';
                $success = openssl_sign($msg, $signature, $key, $algorithm);
                if (!$success) {
                    // OpenSSL unable to sign data
                    return false;
                } else {
                    return $signature;
                }
        }
    }

    /**
     * 检验签名
     * @param type $msg             原信息
     * @param type $signature       原签名
     * @param type $key             密钥
     * @param type $alg
     * @return boolean
     * @throws DomainException
     */
    private static function verify($msg, $signature, $key, $alg) {
        if (empty(static::$supported_algs[$alg])) {
            // Algorithm not supported
            return false;
        }

        list($function, $algorithm) = static::$supported_algs[$alg];
        switch ($function) {
            case 'openssl':
                $success = openssl_verify($msg, $signature, $key, $algorithm);
                if ($success === 1) {
                    return true;
                } elseif ($success === 0) {
                    return false;
                }
                // 'OpenSSL error: ' . openssl_error_string()
                return false;
            case 'hash_hmac':
            default:
                $hash = hash_hmac($algorithm, $msg, $key, true);
                if (function_exists('hash_equals')) {
                    return hash_equals($signature, $hash);
                }
                $len = min(static::safeStrlen($signature), static::safeStrlen($hash));

                $status = 0;
                for ($i = 0; $i < $len; $i++) {
                    $status |= (ord($signature[$i]) ^ ord($hash[$i]));
                }
                $status |= (static::safeStrlen($signature) ^ static::safeStrlen($hash));

                return ($status === 0);
        }
    }

    /**
     * Decode a JSON string into a PHP object.
     * @param type $input
     * @return boolean
     */
    public static function jsonDecode($input) {
        if (version_compare(PHP_VERSION, '5.4.0', '>=') && !(defined('JSON_C_VERSION') && PHP_INT_SIZE > 4)) {
            /** In PHP >=5.4.0, json_decode() accepts an options parameter, that allows you
             * to specify that large ints (like Steam Transaction IDs) should be treated as
             * strings, rather than the PHP default behaviour of converting them to floats.
             */
            $obj = json_decode($input, false, 512, JSON_BIGINT_AS_STRING);
        } else {
            /** Not all servers will support that, however, so for older versions we must
             * manually detect large ints in the JSON string and quote them (thus converting
             * them to strings) before decoding, hence the preg_replace() call.
             */
            $max_int_length = strlen((string) PHP_INT_MAX) - 1;
            $json_without_bigints = preg_replace('/:\s*(-?\d{' . $max_int_length . ',})/', ': "$1"', $input);
            $obj = json_decode($json_without_bigints);
        }

        if (function_exists('json_last_error') && $errno = json_last_error()) {
            static::handleJsonError($errno);
        } elseif ($obj === null && $input !== 'null') {
            // Null result with non-null input
            return false;
        }
        return $obj;
    }

    /**
     * Encode a PHP object into a JSON string.
     * @param type $input
     * @return boolean
     */
    public static function jsonEncode($input) {
        $json = json_encode($input);
        if (function_exists('json_last_error') && $errno = json_last_error()) {
            static::handleJsonError($errno);
        } elseif ($json === 'null' && $input !== null) {
            // Null result with non-null input
            return false;
        }
        return $json;
    }

    /**
     * Decode a string with URL-safe Base64.
     *
     * @param string $input A Base64 encoded string
     *
     * @return string A decoded string
     */
    public static function urlsafeB64Decode($input) {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }

    /**
     * Encode a string with URL-safe Base64.
     *
     * @param string $input The string you want encoded
     *
     * @return string The base64 encode of what you passed in
     */
    public static function urlsafeB64Encode($input) {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }

    /**
     * Helper method to create a JSON error.
     *
     * @param int $errno An error number from json_last_error()
     *
     * @return void
     */
    private static function handleJsonError($errno) {
        $messages = array(
            JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
            JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
            JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON',
            JSON_ERROR_UTF8 => 'Malformed UTF-8 characters' //PHP >= 5.3.3
        );

        $msg = isset($messages[$errno]) ? $messages[$errno] : 'Unknown JSON error: ' . $errno;
        Log::write($msg, LOG::ERR);
        return false;
    }

    /**
     * Get the number of bytes in cryptographic strings.
     *
     * @param string
     *
     * @return int
     */
    private static function safeStrlen($str) {
        if (function_exists('mb_strlen')) {
            return mb_strlen($str, '8bit');
        }
        return strlen($str);
    }

}
