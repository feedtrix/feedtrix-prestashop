<?php
/**
 * FeedTrix API HTTP client. Store requests are signed:
 *   X-FeedTrix-Shop: <store key>, X-FeedTrix-Timestamp: <unix>,
 *   X-FeedTrix-Signature: sha256=HMAC_SHA256("<timestamp>.<body>", API key)
 *
 * @author    FeedTrix
 * @copyright FeedTrix
 * @license   AFL-3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class FeedTrixApi
{
    /**
     * @param array<string, mixed> $payload
     *
     * @return array{status: int, data: array|null, error: string}
     */
    public static function signedPost($path, array $payload, $timeout = 10, $storeKey = null, $apiKey = null)
    {
        $body = json_encode($payload);

        return self::request('POST', $path, $body, self::signature($body, $storeKey, $apiKey), $timeout);
    }

    /** @return array<string, string> */
    public static function signature($body, $storeKey = null, $apiKey = null)
    {
        $timestamp = (string) time();

        return [
            'X-FeedTrix-Shop' => null !== $storeKey ? $storeKey : FeedTrixSettings::storeKey(),
            'X-FeedTrix-Timestamp' => $timestamp,
            'X-FeedTrix-Signature' => 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, null !== $apiKey ? $apiKey : FeedTrixSettings::apiKey()),
        ];
    }

    /** Signature check of a FeedTrix -> store request (e.g. status). */
    public static function verifyIncoming($body)
    {
        $storeKey = isset($_SERVER['HTTP_X_FEEDTRIX_SHOP']) ? (string) $_SERVER['HTTP_X_FEEDTRIX_SHOP'] : '';
        $timestamp = isset($_SERVER['HTTP_X_FEEDTRIX_TIMESTAMP']) ? (string) $_SERVER['HTTP_X_FEEDTRIX_TIMESTAMP'] : '';
        $signature = isset($_SERVER['HTTP_X_FEEDTRIX_SIGNATURE']) ? (string) $_SERVER['HTTP_X_FEEDTRIX_SIGNATURE'] : '';

        if (!FeedTrixSettings::isConnected() || !hash_equals(FeedTrixSettings::storeKey(), $storeKey)
            || !ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        return hash_equals('sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, FeedTrixSettings::apiKey()), $signature);
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array{status: int, data: array|null, error: string, body: string}
     */
    public static function request($method, $path, $body = '', array $headers = [], $timeout = 10)
    {
        $url = preg_match('#^https?://#', $path) ? $path : FeedTrixSettings::endpoint() . $path;
        $lines = ['Content-Type: application/json', 'Accept: application/json', 'Accept-Language: ' . FeedTrix::apiLanguage(), 'User-Agent: FeedTrix-PrestaShop/' . FeedTrix::VERSION . '; ' . FeedTrixSettings::shopUrl()];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_TIMEOUT => (int) $timeout,
            CURLOPT_CONNECTTIMEOUT => min(5, (int) $timeout),
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ('GET' !== $method) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string) $body);
        }
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = false === $response ? curl_error($ch) : '';
        curl_close($ch);

        $data = is_string($response) ? json_decode($response, true) : null;

        return ['status' => $status, 'data' => is_array($data) ? $data : null, 'error' => $error, 'body' => is_string($response) ? $response : ''];
    }
}
