<?php
/**
 * Raw responses of the module controllers (text / JSON) without the store theme.
 *
 * @author    FeedTrix
 * @copyright FeedTrix
 * @license   AFL-3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class FeedTrixResponse
{
    /** @param array<string, string> $headers */
    public static function send($status, $contentType, $body, array $headers = [])
    {
        if (ob_get_level()) {
            @ob_end_clean();
        }
        http_response_code((int) $status);
        header('Content-Type: ' . $contentType);
        header('X-Robots-Tag: noindex');
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $body;
        exit;
    }

    public static function json($status, array $data, array $headers = [])
    {
        self::send($status, 'application/json; charset=utf-8', json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $headers + ['Cache-Control' => 'no-store']);
    }

    public static function text($status, $body, array $headers = [])
    {
        self::send($status, 'text/plain; charset=utf-8', $body, $headers);
    }
}
