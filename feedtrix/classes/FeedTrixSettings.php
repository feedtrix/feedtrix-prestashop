<?php
/**
 * Module settings (Configuration) + the feedtrix-config.php file from the package downloaded from the FeedTrix panel.
 * Order: defaults < feedtrix-config.php < values saved in PrestaShop.
 * Switches and copy lifetimes can also be changed from the FeedTrix panel (controller=settings).
 *
 * @author    FeedTrix
 * @copyright FeedTrix
 * @license   AFL-3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class FeedTrixSettings
{
    public const DEFAULT_ENDPOINT = 'https://api.feedtrix.com';
    public const DEFAULT_TTL = 86400;
    public const TTL_MIN = 300;
    public const TTL_MAX = 2592000;

    /** @var array<string, array{0: string, 1: int}> switch (name as in the WooCommerce plugin) => [Configuration key, default] */
    public static $toggles = [
        'llms_txt' => ['LLMS', 1],
        'bot_tracking' => ['BOTS', 1],
        'attribution' => ['ATTRIBUTION', 1],
        'json_ld' => ['JSONLD', 1],
        'faq_tab' => ['FAQ', 0],
    ];

    /** @var string[] klucze Configuration usuwane przy odinstalowaniu */
    public static $keys = [
        'FEEDTRIX_ENDPOINT', 'FEEDTRIX_SHOP_ID', 'FEEDTRIX_API_KEY', 'FEEDTRIX_INDEXNOW_KEY',
        'FEEDTRIX_LLMS', 'FEEDTRIX_BOTS', 'FEEDTRIX_ATTRIBUTION', 'FEEDTRIX_JSONLD', 'FEEDTRIX_FAQ',
        'FEEDTRIX_PRODUCT_TTL', 'FEEDTRIX_LLMS_TTL', 'FEEDTRIX_JSONLD_GEN', 'FEEDTRIX_LLMS_PAGES', 'FEEDTRIX_ACCOUNT',
        'FEEDTRIX_NONCE', 'FEEDTRIX_REMOTE_CONFIG', 'FEEDTRIX_LLMS_STALE', // starsze wersje
        'FEEDTRIX_BOT_HITS', 'FEEDTRIX_BOT_SENT_AT',
    ];

    /** @var array|null */
    private static $bundled;

    public static function get($key, $default = null)
    {
        $value = Configuration::get('FEEDTRIX_' . $key);

        return (false === $value || '' === $value) ? $default : $value;
    }

    public static function set($key, $value)
    {
        Configuration::updateValue('FEEDTRIX_' . $key, $value);
    }

    /** @return array<string, string> */
    public static function bundled()
    {
        if (null === self::$bundled) {
            $file = dirname(__DIR__) . '/feedtrix-config.php';
            $config = is_readable($file) ? include $file : [];
            self::$bundled = is_array($config) ? array_map('strval', $config) : [];
        }

        return self::$bundled;
    }

    public static function hasBundledConfig()
    {
        return [] !== self::bundled();
    }

    private static function connectionValue($key, $bundledKey)
    {
        $saved = self::get($key);
        if (null !== $saved) {
            return (string) $saved;
        }
        $bundled = self::bundled();

        return isset($bundled[$bundledKey]) ? (string) $bundled[$bundledKey] : '';
    }

    public static function endpoint()
    {
        $env = getenv('FEEDTRIX_ENDPOINT'); // test environment (Docker): http://feedtrix-api:81
        if ($env) {
            return rtrim($env, '/');
        }
        $value = self::connectionValue('ENDPOINT', 'endpoint');

        return rtrim('' !== $value ? $value : self::DEFAULT_ENDPOINT, '/');
    }

    public static function storeKey()
    {
        return self::connectionValue('SHOP_ID', 'shop_id');
    }

    public static function apiKey()
    {
        return self::connectionValue('API_KEY', 'api_key');
    }

    public static function indexNowKey()
    {
        $key = strtolower(self::connectionValue('INDEXNOW_KEY', 'indexnow_key'));

        return preg_match('/^[a-f0-9]{32}$/', $key) ? $key : '';
    }

    public static function isConnected()
    {
        return '' !== self::storeKey() && '' !== self::apiKey();
    }

    public static function enabled($toggle)
    {
        list($key, $default) = self::$toggles[$toggle];

        return (bool) self::get($key, $default);
    }

    public static function setEnabled($toggle, $on)
    {
        self::set(self::$toggles[$toggle][0], $on ? 1 : 0);
    }

    public static function llmsEnabled()
    {
        return self::enabled('llms_txt');
    }

    public static function botsEnabled()
    {
        return self::enabled('bot_tracking');
    }

    public static function attributionEnabled()
    {
        return self::enabled('attribution');
    }

    public static function jsonLdEnabled()
    {
        return self::enabled('json_ld');
    }

    public static function faqTabEnabled()
    {
        return self::enabled('faq_tab');
    }

    /** Lifetime of the local product JSON-LD copy (set from FeedTrix, 1 day by default). */
    public static function productTtl()
    {
        return self::clampTtl(self::get('PRODUCT_TTL', self::DEFAULT_TTL));
    }

    /** Lifetime of the local /llms.txt and /llms-full.txt copy. */
    public static function llmsTtl()
    {
        return self::clampTtl(self::get('LLMS_TTL', self::DEFAULT_TTL));
    }

    private static function clampTtl($seconds)
    {
        return max(self::TTL_MIN, min(self::TTL_MAX, (int) $seconds));
    }

    /**
     * Changes from FeedTrix: only the given switches and copy lifetimes, the rest unchanged.
     *
     * @param array<string, mixed> $toggles
     * @param array<string, mixed> $cache product_ttl, llms_ttl
     */
    public static function updateRemote(array $toggles, array $cache)
    {
        foreach (array_keys(self::$toggles) as $toggle) {
            if (array_key_exists($toggle, $toggles)) {
                self::setEnabled($toggle, !empty($toggles[$toggle]));
            }
        }
        $productTtl = self::productTtl();
        foreach (['product_ttl' => 'PRODUCT_TTL', 'llms_ttl' => 'LLMS_TTL'] as $from => $key) {
            if (isset($cache[$from]) && is_numeric($cache[$from])) {
                self::set($key, self::clampTtl($cache[$from]));
            }
        }
        FeedTrixLlms::purgeLocal();
        if ($productTtl !== self::productTtl()) {
            FeedTrixJsonLd::purgeAll(); // kopie zapisane ze starym czasem
        }
    }

    /** @return array{settings: array<string, bool>, cache: array{product_ttl: int, llms_ttl: int}} */
    public static function remoteState()
    {
        $settings = [];
        foreach (array_keys(self::$toggles) as $toggle) {
            $settings[$toggle] = self::enabled($toggle);
        }

        return [
            'settings' => $settings,
            'cache' => ['product_ttl' => self::productTtl(), 'llms_ttl' => self::llmsTtl()],
        ];
    }

    /** @return array<string, mixed> */
    public static function account()
    {
        $account = json_decode((string) self::get('ACCOUNT', ''), true);

        return is_array($account) ? $account : [];
    }

    /**
     * FeedTrix panel home page. Older API versions returned the store page URL (/shops/{id}),
     * which gives 404 when another account is logged in the browser, so only the panel URL is kept.
     */
    public static function panelUrl()
    {
        $account = self::account();
        $parts = parse_url(isset($account['panel_url']) ? (string) $account['panel_url'] : '');
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') . '/';
    }

    /**
     * Sign-up in the FeedTrix panel: API host with "api." replaced by "panel." (api.feedtrix.com -> panel.feedtrix.com).
     */
    public static function signupUrl()
    {
        $parts = parse_url(self::endpoint());
        $host = isset($parts['host']) ? (string) $parts['host'] : '';
        $panel = 0 === strpos($host, 'api.')
            ? (isset($parts['scheme']) ? $parts['scheme'] : 'https') . '://panel.' . substr($host, 4) . (isset($parts['port']) ? ':' . $parts['port'] : '')
            : 'https://panel.feedtrix.com';

        return $panel . '/register?utm_source=prestashop-module&utm_medium=plugin';
    }

    /**
     * @param array<string, string> $connection shop_id, api_key, indexnow_key
     * @param array<string, mixed> $account email, verified, panel_url
     */
    public static function saveConnection(array $connection, array $account)
    {
        self::set('SHOP_ID', isset($connection['shop_id']) ? (string) $connection['shop_id'] : '');
        self::set('API_KEY', isset($connection['api_key']) ? (string) $connection['api_key'] : '');
        self::set('INDEXNOW_KEY', isset($connection['indexnow_key']) ? (string) $connection['indexnow_key'] : '');
        self::set('ACCOUNT', $account ? json_encode($account) : '');
        FeedTrixLlms::purgeLocal(true);
        FeedTrixJsonLd::purgeAll();
    }

    /** Store URL that FeedTrix compares with the store URL in the panel (overridden by the FEEDTRIX_SHOP_URL variable in a test environment). */
    public static function shopUrl()
    {
        $env = getenv('FEEDTRIX_SHOP_URL');
        if ($env) {
            return rtrim($env, '/');
        }

        return rtrim(FeedTrix::context()->shop->getBaseURL(true), '/');
    }
}
