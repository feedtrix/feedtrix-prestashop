<?php
/**
 * Product changes -> POST {api}/webhooks/prestashop (batched, after the request ends).
 * {"site_url": "...", "sent_at": "...", "events": [{"type": "product.updated", "product_id": 12,
 *   "changes": ["price"], "snapshot": {"price": "199.99", "regular_price": "249.99", "in_stock": true}}]}
 *
 * @author    FeedTrix
 * @copyright FeedTrix
 * @license   AFL-3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class FeedTrixWebhooks
{
    /** @var array<int, array{type: string, changes: array<string, bool>}> */
    private static $queue = [];

    /** @var bool */
    private static $registered = false;

    public static function queue($idProduct, $type, $change = null)
    {
        $idProduct = (int) $idProduct;
        if ($idProduct <= 0 || !FeedTrixSettings::isConnected()) {
            return;
        }
        $current = isset(self::$queue[$idProduct]) ? self::$queue[$idProduct] : ['type' => $type, 'changes' => []];
        // create/delete takes precedence over an update in the same request
        if ('product.updated' !== $type) {
            $current['type'] = $type;
        }
        if ($change) {
            $current['changes'][$change] = true;
        }
        self::$queue[$idProduct] = $current;

        if (!self::$registered) {
            self::$registered = true;
            register_shutdown_function([__CLASS__, 'flush']);
        }
    }

    public static function flush()
    {
        if (!self::$queue) {
            return;
        }
        try {
            self::doFlush();
        } catch (Throwable $e) {
            // a FeedTrix error must not break saving the product; changes arrive with the sync
            PrestaShopLogger::addLog('FeedTrix webhook: ' . $e->getMessage(), 2, null, 'Product', 0, true);
        }
    }

    private static function doFlush()
    {
        $events = [];
        foreach (self::$queue as $idProduct => $item) {
            $event = ['type' => $item['type'], 'product_id' => $idProduct, 'changes' => array_keys($item['changes'])];
            if ('product.deleted' !== $item['type']) {
                $snapshot = FeedTrixCatalog::snapshot($idProduct);
                unset($snapshot['currency']);
                $event['snapshot'] = $snapshot;
            }
            $events[] = $event;
        }
        self::$queue = [];
        FeedTrixLlms::purgeLocal(); // the local copy is stale: the next request fetches a fresh one from FeedTrix

        self::send($events, 5);
    }

    /** @return array{status: int, data: array|null, error: string} */
    public static function send(array $events, $timeout = 5)
    {
        return FeedTrixApi::signedPost('/webhooks/prestashop', [
            'site_url' => FeedTrixSettings::shopUrl() . '/',
            'sent_at' => gmdate('c'),
            'events' => $events,
        ], $timeout);
    }
}
