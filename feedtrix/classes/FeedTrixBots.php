<?php
/**
 * AI bot detection by User-Agent (list matches the WooCommerce plugin and FeedTrix nginx).
 *
 * @author    FeedTrix
 * @copyright FeedTrix
 * @license   AFL-3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class FeedTrixBots
{
    /** @var array<string, string> */
    private static $patterns = [
        'GPTBot' => 'GPTBot',
        'OAI-SearchBot' => 'OAI-SearchBot',
        'ChatGPT-User' => 'ChatGPT-User',
        'PerplexityBot' => 'PerplexityBot',
        'Perplexity-User' => 'Perplexity-User',
        'ClaudeBot' => 'ClaudeBot',
        'Claude-User' => 'Claude-User',
        'Claude-SearchBot' => 'Claude-SearchBot',
        'Google-Extended' => 'Google-Extended',
        'Applebot-Extended' => 'Applebot-Extended',
        'CCBot' => 'CCBot',
        'Bytespider' => 'Bytespider',
        'meta-externalagent' => 'Meta-ExternalAgent',
    ];

    public static function current()
    {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
        if ('' === $ua) {
            return null;
        }
        foreach (self::$patterns as $needle => $name) {
            if (false !== stripos($ua, $needle)) {
                return $name;
            }
        }

        return null;
    }
}
