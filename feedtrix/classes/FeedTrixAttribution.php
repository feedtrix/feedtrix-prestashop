<?php
/**
 * AI sales attribution (same as in the WooCommerce plugin).
 *
 * 1. A script in the store footer detects a visit from an AI assistant (referrer chatgpt.com, perplexity.ai… or
 *    utm_source=feedtrix from links in /llms.txt and JSON-LD) and stores it in the ft_ai_source cookie for 30 days.
 * 2. When an order is placed (actionValidateOrder), the cookie content goes to the feedtrix_order table.
 * 3. When the order is paid (status with the "paid" flag), the module sends an order.created event to FeedTrix:
 *    order number, amounts, currency and visit source. No buyer data.
 *    Orders without an AI visit are not sent.
 *
 * @author    FeedTrix
 * @copyright FeedTrix
 * @license   AFL-3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class FeedTrixAttribution
{
    public const COOKIE = 'ft_ai_source';
    public const WINDOW = 2592000; // 30 dni
    public const TABLE = 'feedtrix_order';

    /** @var string[] */
    public static $domains = ['chatgpt.com', 'openai.com', 'perplexity.ai', 'claude.ai', 'gemini.google.com', 'copilot.microsoft.com'];

    /** @var string[] */
    private static $media = ['llms_txt', 'llms_full', 'json_ld', 'mcp', 'organic_ai_referral'];

    /** @var int[] orders to report after the response is sent */
    private static $queue = [];

    public static function installDb()
    {
        return Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE . '` (
                `id_order` INT UNSIGNED NOT NULL PRIMARY KEY,
                `attribution` TEXT NOT NULL,
                `reported_at` DATETIME NULL DEFAULT NULL
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4'
        );
    }

    public static function uninstallDb()
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABLE . '`');
    }

    private static function active()
    {
        return FeedTrixSettings::isConnected() && FeedTrixSettings::attributionEnabled();
    }

    /** Skrypt przed </body> (displayBeforeBodyClosingTag). */
    public static function script()
    {
        if (!self::active()) {
            return '';
        }
        $domains = json_encode(self::$domains);

        return "<script id=\"feedtrix-ai-source\">(function(){try{var d={$domains},q=new URLSearchParams(location.search),"
            . "us=(q.get('utm_source')||'').toLowerCase(),um=(q.get('utm_medium')||'').toLowerCase(),rh='';"
            . "try{rh=document.referrer?new URL(document.referrer).hostname.toLowerCase().replace(/^www\\./,''):''}catch(e){}"
            . "function m(h){for(var i=0;i<d.length;i++){if(h===d[i]||h.slice(-d[i].length-1)==='.'+d[i])return d[i]}return ''}"
            . "var s=m(rh)||m(us),md='organic_ai_referral';"
            . "if(us==='feedtrix'){if(!s)s='feedtrix';if(um==='llms_txt'||um==='llms_full'||um==='json_ld')md=um}"
            . 'if(!s)return;'
            . 'var v=JSON.stringify({source:s,medium:md,landing_page:location.pathname.slice(0,200),timestamp:Math.floor(Date.now()/1000)});'
            . "document.cookie='" . self::COOKIE . "='+encodeURIComponent(v)+';path=/;max-age=" . self::WINDOW . ";SameSite=Lax'+(location.protocol==='https:'?';Secure':'');"
            . "try{localStorage.setItem('" . self::COOKIE . "',v)}catch(e){}"
            . "}catch(e){}})();</script>\n";
    }

    /**
     * Validated cookie content or null (missing, bad format, visit older than 30 days).
     *
     * @return array{source: string, medium: string, landing_page: string, timestamp: int}|null
     */
    public static function fromCookie()
    {
        if (empty($_COOKIE[self::COOKIE])) {
            return null;
        }
        $data = json_decode(rawurldecode((string) $_COOKIE[self::COOKIE]), true);
        if (!is_array($data)) {
            return null;
        }
        $source = strtolower(isset($data['source']) ? (string) $data['source'] : '');
        $timestamp = isset($data['timestamp']) ? (int) $data['timestamp'] : 0;
        if (!in_array($source, array_merge(self::$domains, ['feedtrix']), true) || $timestamp > time() + 3600 || $timestamp < time() - self::WINDOW) {
            return null;
        }
        $medium = isset($data['medium']) ? (string) $data['medium'] : '';
        $landing = isset($data['landing_page']) ? (string) $data['landing_page'] : '';

        return [
            'source' => $source,
            'medium' => in_array($medium, self::$media, true) ? $medium : 'organic_ai_referral',
            'landing_page' => substr(strip_tags(preg_replace('/[\x00-\x1F\x7F]/', '', $landing)), 0, 200),
            'timestamp' => $timestamp,
        ];
    }

    /** Order placed: stores the visit source; an order paid right away (e.g. card) is reported in the same request. */
    public static function capture($order, $orderState = null)
    {
        $attribution = self::active() ? self::fromCookie() : null;
        if (!$order instanceof Order || !Validate::isLoadedObject($order) || null === $attribution) {
            return;
        }
        Db::getInstance()->insert(self::TABLE, [
            'id_order' => (int) $order->id,
            'attribution' => pSQL(json_encode($attribution)),
        ], false, true, Db::INSERT_IGNORE);

        if ($orderState instanceof OrderState && $orderState->paid) {
            self::enqueue($order->id);
        }
    }

    /** Order status change (actionOrderStatusPostUpdate). */
    public static function statusChanged($idOrder, $orderState)
    {
        if (self::active() && $orderState instanceof OrderState && $orderState->paid) {
            self::enqueue($idOrder);
        }
    }

    private static function enqueue($idOrder)
    {
        if (!self::$queue) {
            register_shutdown_function([__CLASS__, 'flush']);
        }
        self::$queue[(int) $idOrder] = (int) $idOrder;
    }

    /** Sent after the response to the customer; a reported order gets a date, a failed one is retried on the next status change. */
    public static function flush()
    {
        if (!self::$queue) {
            return;
        }
        try {
            self::send();
        } catch (Throwable $e) {
            // a FeedTrix error must not break the store order
            PrestaShopLogger::addLog('FeedTrix attribution: ' . $e->getMessage(), 2, null, 'Order', 0, true);
        }
    }

    private static function send()
    {
        $ids = array_values(self::$queue);
        self::$queue = [];
        $rows = Db::getInstance()->executeS(
            'SELECT `id_order`, `attribution` FROM `' . _DB_PREFIX_ . self::TABLE . '`
            WHERE `reported_at` IS NULL AND `id_order` IN (' . implode(',', array_map('intval', $ids)) . ')'
        );
        $events = [];
        $reported = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $order = new Order((int) $row['id_order']);
            $attribution = json_decode((string) $row['attribution'], true);
            if (!Validate::isLoadedObject($order) || !is_array($attribution)) {
                continue;
            }
            $currency = new Currency((int) $order->id_currency);
            $created = date_create((string) $order->date_add);
            $events[] = [
                'type' => 'order.created',
                'order_id' => (string) $order->id,
                'total_net' => number_format((float) $order->total_paid_tax_excl, 2, '.', ''),
                'total_gross' => number_format((float) $order->total_paid_tax_incl, 2, '.', ''),
                'currency' => (string) $currency->iso_code,
                'created_at' => $created ? $created->format('c') : gmdate('c'),
                'attribution' => $attribution,
            ];
            $reported[] = (int) $order->id;
        }
        if (!$events) {
            return;
        }
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        $response = FeedTrixWebhooks::send($events, 10);
        if (202 === $response['status']) {
            Db::getInstance()->update(self::TABLE, ['reported_at' => gmdate('Y-m-d H:i:s')], '`id_order` IN (' . implode(',', $reported) . ')');
        }
    }
}
