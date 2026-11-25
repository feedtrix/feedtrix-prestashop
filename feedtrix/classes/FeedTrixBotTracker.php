<?php
/**
 * AI bot visits: hourly counters in Configuration, sent every hour with POST {api}/bot-hits.
 * {"site_url": "...", "hits": [{"hour": "2026-11-11T10:00:00Z", "bot": "GPTBot", "target": "product", "product_id": 12, "count": 3, "last_at": "..."}]}
 *
 * @author    FeedTrix
 * @copyright FeedTrix
 * @license   AFL-3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class FeedTrixBotTracker
{
    public const SEND_EVERY = 3600;
    public const MAX_KEYS = 2000;

    public static function record($target, $idProduct = 0)
    {
        $bot = FeedTrixBots::current();
        if (null === $bot || !FeedTrixSettings::isConnected() || !FeedTrixSettings::botsEnabled()) {
            return;
        }

        $hits = json_decode((string) FeedTrixSettings::get('BOT_HITS', '{}'), true);
        $hits = is_array($hits) ? $hits : [];
        $key = implode('|', [gmdate('Y-m-d\TH:00:00\Z'), $bot, $target, (int) $idProduct]);
        if (!isset($hits[$key]) && count($hits) >= self::MAX_KEYS) {
            return;
        }
        $hits[$key] = ['c' => (isset($hits[$key]['c']) ? (int) $hits[$key]['c'] : 0) + 1, 't' => time()];
        FeedTrixSettings::set('BOT_HITS', json_encode($hits));

        if (time() - (int) FeedTrixSettings::get('BOT_SENT_AT', 0) >= self::SEND_EVERY) {
            register_shutdown_function([__CLASS__, 'send']);
        }
    }

    public static function send()
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request(); // the bot already got its response
        }
        $hits = json_decode((string) FeedTrixSettings::get('BOT_HITS', '{}'), true);
        if (!is_array($hits) || !$hits) {
            return;
        }
        FeedTrixSettings::set('BOT_SENT_AT', time());

        $rows = [];
        foreach ($hits as $key => $value) {
            list($hour, $bot, $target, $idProduct) = array_pad(explode('|', (string) $key), 4, '0');
            $rows[] = [
                'hour' => $hour, 'bot' => $bot, 'target' => $target, 'product_id' => (int) $idProduct,
                'count' => (int) $value['c'], 'last_at' => gmdate('Y-m-d\TH:i:s\Z', (int) $value['t']),
            ];
        }

        $response = FeedTrixApi::signedPost('/bot-hits', ['site_url' => FeedTrixSettings::shopUrl() . '/', 'hits' => $rows], 10);
        if ($response['status'] >= 200 && $response['status'] < 300) {
            // subtract only the sent counters; hits recorded during sending stay
            $current = json_decode((string) FeedTrixSettings::get('BOT_HITS', '{}'), true);
            $current = is_array($current) ? $current : [];
            foreach ($hits as $key => $value) {
                if (isset($current[$key])) {
                    $current[$key]['c'] -= (int) $value['c'];
                    if ($current[$key]['c'] <= 0) {
                        unset($current[$key]);
                    }
                }
            }
            FeedTrixSettings::set('BOT_HITS', json_encode($current));
        }
    }
}
