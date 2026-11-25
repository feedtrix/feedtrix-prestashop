<?php
/**
 * FeedTrix for PrestaShop 8/9: store optimization for AI search engines (AEO), same as the WooCommerce plugin.
 * Price and stock webhooks, /llms.txt and /llms-full.txt, JSON-LD with AI content, AI sales attribution,
 * AI bot statistics, IndexNow, public catalog for FeedTrix.
 *
 * @author    FeedTrix
 * @copyright FeedTrix
 * @license   AFL-3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/FeedTrixSettings.php';
require_once __DIR__ . '/classes/FeedTrixApi.php';
require_once __DIR__ . '/classes/FeedTrixBots.php';
require_once __DIR__ . '/classes/FeedTrixCatalog.php';
require_once __DIR__ . '/classes/FeedTrixWebhooks.php';
require_once __DIR__ . '/classes/FeedTrixLlms.php';
require_once __DIR__ . '/classes/FeedTrixJsonLd.php';
require_once __DIR__ . '/classes/FeedTrixAttribution.php';
require_once __DIR__ . '/classes/FeedTrixBotTracker.php';
require_once __DIR__ . '/classes/FeedTrixOnboarding.php';
require_once __DIR__ . '/classes/FeedTrixResponse.php';

class FeedTrix extends Module
{
    public const VERSION = '1.0.0';

    /** FeedTrix API message languages (Accept-Language); the API picks the best available one, English by default. */
    public const API_LANGUAGES = ['pl', 'en', 'de', 'es', 'fr', 'it'];

    /** @var string[] product change hooks (webhooks to FeedTrix) */
    public static $productHooks = [
        'actionProductAdd',
        'actionProductUpdate',
        'actionProductDelete',
        'actionUpdateQuantity',
        'actionObjectSpecificPriceAddAfter',
        'actionObjectSpecificPriceUpdateAfter',
        'actionObjectSpecificPriceDeleteAfter',
    ];

    /** @var string[] other hooks: routes, bots, JSON-LD, FAQ tab, sales attribution */
    public static $hooks = [
        'moduleRoutes',
        'actionFrontControllerInitAfter',
        'actionOutputHTMLBefore',
        'displayProductExtraContent',
        'displayBeforeBodyClosingTag',
        'actionValidateOrder',
        'actionOrderStatusPostUpdate',
    ];

    public function __construct()
    {
        $this->name = 'feedtrix';
        $this->tab = 'seo';
        $this->version = '1.0.0';
        $this->author = 'FeedTrix';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->l('FeedTrix: AI search optimization (AEO) for your store');
        $this->description = $this->l('Optimizes your store for AI search engines (AEO): price and stock webhooks, a dynamic /llms.txt file, AI bot statistics and IndexNow.');
        $this->confirmUninstall = $this->l('Uninstall FeedTrix? The /llms.txt file will stop working.');
    }

    /** Translation outside the module class (classes and controllers); same source as $this->l() in feedtrix.php. Not escaped. */
    public static function t($string)
    {
        return Translate::getModuleTranslation('feedtrix', $string, 'feedtrix', null, false, null, true, false);
    }

    /** Current PrestaShop context for the module classes (the module instance keeps it). */
    public static function context()
    {
        /** @var FeedTrix $module */
        $module = Module::getInstanceByName('feedtrix');

        return $module->context;
    }

    /** Employee language for the FeedTrix API, e.g. "de" (error messages when connecting the store). */
    public static function apiLanguage()
    {
        $language = self::context()->language;
        $iso = $language ? strtolower(substr((string) $language->iso_code, 0, 2)) : 'en';

        return in_array($iso, self::API_LANGUAGES, true) ? $iso : 'en';
    }

    public function install()
    {
        return parent::install()
            && FeedTrixAttribution::installDb()
            && $this->registerHook(array_merge(self::$productHooks, self::$hooks));
    }

    public function uninstall()
    {
        foreach (FeedTrixSettings::$keys as $key) {
            Configuration::deleteByName($key);
        }
        FeedTrixLlms::purgeLocal(true);
        FeedTrixJsonLd::purgeAll();
        Configuration::deleteByName('FEEDTRIX_JSONLD_GEN');

        return FeedTrixAttribution::uninstallDb() && parent::uninstall();
    }

    // --- routes: /llms.txt, /llms-full.txt, /llms-full/{n}.txt and /{IndexNow key}.txt (require friendly URLs) ---

    public function hookModuleRoutes()
    {
        $params = ['fc' => 'module', 'module' => $this->name];

        return [
            'module-feedtrix-llms' => ['controller' => 'llms', 'rule' => 'llms.txt', 'keywords' => [], 'params' => $params + ['file' => 'llms']],
            'module-feedtrix-llms-full' => ['controller' => 'llms', 'rule' => 'llms-full.txt', 'keywords' => [], 'params' => $params + ['file' => 'full']],
            'module-feedtrix-llms-page' => [
                'controller' => 'llms',
                'rule' => 'llms-full/{page}.txt',
                'keywords' => ['page' => ['regexp' => '[1-9][0-9]{0,3}', 'param' => 'page']],
                'params' => $params + ['file' => 'full'],
            ],
            'module-feedtrix-indexnow' => [
                'controller' => 'indexnow',
                'rule' => '{key}.txt',
                'keywords' => ['key' => ['regexp' => '[a-fA-F0-9]{32}', 'param' => 'key']],
                'params' => $params,
            ],
        ];
    }

    // --- product changes ---

    public function hookActionProductAdd($params)
    {
        FeedTrixWebhooks::queue($this->productId($params), 'product.created');
    }

    public function hookActionProductUpdate($params)
    {
        $id = $this->productId($params);
        FeedTrixJsonLd::purge($id); // the local JSON-LD copy is stale (also when the feature is off)
        FeedTrixWebhooks::queue($id, 'product.updated', 'price');
    }

    public function hookActionProductDelete($params)
    {
        FeedTrixWebhooks::queue($this->productId($params), 'product.deleted');
    }

    public function hookActionUpdateQuantity($params)
    {
        FeedTrixWebhooks::queue($this->productId($params), 'product.updated', 'stock');
    }

    public function hookActionObjectSpecificPriceAddAfter($params)
    {
        $this->specificPriceChanged($params);
    }

    public function hookActionObjectSpecificPriceUpdateAfter($params)
    {
        $this->specificPriceChanged($params);
    }

    public function hookActionObjectSpecificPriceDeleteAfter($params)
    {
        $this->specificPriceChanged($params);
    }

    private function specificPriceChanged($params)
    {
        // id_product = 0: rule for the whole catalog – changes arrive with the full sync
        if (isset($params['object']) && (int) $params['object']->id_product > 0) {
            FeedTrixWebhooks::queue((int) $params['object']->id_product, 'product.updated', 'price');
        }
    }

    private function productId($params)
    {
        if (isset($params['id_product'])) {
            return (int) $params['id_product'];
        }

        return isset($params['product']) && is_object($params['product']) ? (int) $params['product']->id : 0;
    }

    // --- AI bot visits ---

    public function hookActionFrontControllerInitAfter($params)
    {
        if (null === FeedTrixBots::current()) {
            return; // regular traffic: zero cost
        }
        $controller = isset($params['controller']) ? $params['controller'] : $this->context->controller;
        $page = is_object($controller) && isset($controller->php_self) ? (string) $controller->php_self : '';
        if ($controller instanceof ModuleFrontController) {
            return; // llms.txt counts itself, the other module controllers are FeedTrix traffic
        }

        switch ($page) {
            case 'product':
                FeedTrixBotTracker::record('product', (int) Tools::getValue('id_product'));
                break;
            case 'category':
                FeedTrixBotTracker::record('category');
                break;
            case 'index':
                FeedTrixBotTracker::record('home');
                break;
            default:
                FeedTrixBotTracker::record('other');
        }
    }

    // --- JSON-LD and questions with answers on the product page ---

    private function isProductPage()
    {
        $controller = $this->context->controller;

        return is_object($controller) && isset($controller->php_self) && 'product' === $controller->php_self;
    }

    public function hookActionOutputHTMLBefore($params)
    {
        if (!isset($params['html']) || !is_string($params['html']) || !$this->isProductPage()
            || !FeedTrixSettings::isConnected() || !FeedTrixSettings::jsonLdEnabled()) {
            return;
        }
        $params['html'] = FeedTrixJsonLd::filterHtml($params['html'], (int) Tools::getValue('id_product'));
    }

    public function hookDisplayProductExtraContent($params)
    {
        if (!FeedTrixSettings::isConnected() || !FeedTrixSettings::jsonLdEnabled() || !FeedTrixSettings::faqTabEnabled()) {
            return [];
        }
        $product = isset($params['product']) ? $params['product'] : null;
        $id = is_object($product) ? (int) $product->id : (is_array($product) && isset($product['id_product']) ? (int) $product['id_product'] : 0);
        $faq = FeedTrixJsonLd::faq($id);
        if (!$faq) {
            return [];
        }
        $html = '<dl class="feedtrix-faq">';
        foreach ($faq as $item) {
            $html .= '<dt><strong>' . htmlspecialchars($item['q'], ENT_QUOTES, 'UTF-8') . '</strong></dt><dd>' . htmlspecialchars($item['a'], ENT_QUOTES, 'UTF-8') . '</dd>';
        }
        $html .= '</dl>';

        return [(new PrestaShop\PrestaShop\Core\Product\ProductExtraContent())->setTitle($this->l('Questions and answers'))->setContent($html)];
    }

    // --- AI sales attribution ---

    public function hookDisplayBeforeBodyClosingTag()
    {
        return FeedTrixAttribution::script();
    }

    public function hookActionValidateOrder($params)
    {
        FeedTrixAttribution::capture(isset($params['order']) ? $params['order'] : null, isset($params['orderStatus']) ? $params['orderStatus'] : null);
    }

    public function hookActionOrderStatusPostUpdate($params)
    {
        FeedTrixAttribution::statusChanged(isset($params['id_order']) ? (int) $params['id_order'] : 0, isset($params['newOrderStatus']) ? $params['newOrderStatus'] : null);
    }

    // --- back office configuration ---

    /** @return array<string, array{title: string, description: string, icon: string}> module features (tiles and switches) */
    private function features()
    {
        return [
            'llms_txt' => [
                'title' => $this->l('Serve /llms.txt'),
                'description' => $this->l('A file for AI bots with your catalog, served from your domain.'),
                'icon' => 'llms_txt',
            ],
            'bot_tracking' => [
                'title' => $this->l('AI bot statistics'),
                'description' => $this->l('Counts visits of AI bots (no personal data) and sends them to FeedTrix every hour.'),
                'icon' => 'bot_tracking',
            ],
            'attribution' => [
                'title' => $this->l('Sales from AI (order attribution)'),
                'description' => $this->l('Marks paid orders from customers who came from AI assistants in the last 30 days.'),
                'icon' => 'attribution',
            ],
            'json_ld' => [
                'title' => $this->l('FeedTrix structured data (JSON-LD) on product pages'),
                'description' => $this->l('Adds AI descriptions, attributes and FAQ to the product data read by AI and search engines.'),
                'icon' => 'json_ld',
            ],
            'faq_tab' => [
                'title' => $this->l('Show the questions and answers tab on product pages'),
                'description' => $this->l('A visible tab with questions and answers prepared by FeedTrix.'),
                'icon' => 'faq_tab',
            ],
        ];
    }

    /** Random in-stock product with a price for the AI chat preview; a placeholder when there is none. */
    private function previewProduct()
    {
        $ids = FeedTrixCatalog::ids(1, 20);
        shuffle($ids);
        $idLang = (int) $this->context->language->id;
        $fallback = null;
        foreach ($ids as $id) {
            $snapshot = FeedTrixCatalog::snapshot($id);
            if (null === $snapshot['price'] || !$snapshot['in_stock']) {
                continue;
            }
            $product = new Product($id, false, $idLang);
            $cover = Image::getCover($id);
            $item = [
                'name' => (string) $product->name,
                'price' => Tools::getContextLocale($this->context)->formatPrice((float) $snapshot['price'], (string) $snapshot['currency']),
                'image' => $cover ? $this->context->link->getImageLink($product->link_rewrite, $id . '-' . (int) $cover['id_image'], ImageType::getFormattedName('small')) : '',
            ];
            if ('' !== $item['image']) {
                return $item;
            }
            $fallback = $fallback ?: $item;
        }

        return $fallback ?: ['name' => $this->l('Your bestseller'), 'price' => '', 'image' => ''];
    }

    /** Masked key: only the start and end are visible (ftx_504b••••••ed12). */
    private static function mask($value, $head = 4, $tail = 4)
    {
        if (strlen((string) $value) <= (int) $head + (int) $tail) {
            return str_repeat('•', 8);
        }

        return substr($value, 0, $head) . str_repeat('•', 10) . substr($value, -$tail);
    }

    public function getContent()
    {
        $notice = null;
        $old = [];

        if (Tools::isSubmit('feedtrixKeys')) {
            $old = ['store_key' => trim((string) Tools::getValue('store_key'))];
            $notice = FeedTrixOnboarding::keys($old['store_key'], trim((string) Tools::getValue('api_key')));
        } elseif (Tools::isSubmit('feedtrixDisconnect')) {
            FeedTrixSettings::saveConnection([], []);
            $notice = ['ok' => true, 'message' => $this->l('Your store has been disconnected from your FeedTrix account.')];
        } elseif (Tools::isSubmit('feedtrixSettings')) {
            foreach (array_keys(FeedTrixSettings::$toggles) as $toggle) {
                FeedTrixSettings::setEnabled($toggle, (bool) Tools::getValue($toggle));
            }
            FeedTrixLlms::purgeLocal();
            $notice = ['ok' => true, 'message' => $this->l('Settings saved.')];
        }

        $output = '';
        if ($notice) {
            $message = htmlspecialchars((string) $notice['message'], ENT_QUOTES, 'UTF-8');
            $output .= $notice['ok'] ? $this->displayConfirmation($message) : $this->displayError($message);
        }

        $connected = FeedTrixSettings::isConnected();
        $state = FeedTrixSettings::remoteState();
        $features = [];
        foreach ($this->features() as $key => $feature) {
            // $this->l() returns HTML-escaped text; the template escapes it again, so decode first
            $features[$key] = [
                'title' => html_entity_decode($feature['title'], ENT_QUOTES, 'UTF-8'),
                'description' => html_entity_decode($feature['description'], ENT_QUOTES, 'UTF-8'),
                'icon' => $feature['icon'],
                'on' => $state['settings'][$key],
            ];
        }
        $this->context->smarty->assign([
            'ftx_connected' => $connected,
            'ftx_account' => FeedTrixSettings::account(),
            'ftx_panel_url' => FeedTrixSettings::panelUrl(),
            'ftx_signup_url' => FeedTrixSettings::signupUrl(),
            'ftx_bundled' => FeedTrixSettings::hasBundledConfig(),
            'ftx_store_key' => self::mask(FeedTrixSettings::storeKey(), 8),
            'ftx_api_key' => self::mask(FeedTrixSettings::apiKey(), 0),
            'ftx_action' => $this->configUrl(),
            'ftx_old' => $notice && !$notice['ok'] ? $old : [],
            'ftx_features' => $features,
            'ftx_llms_url' => FeedTrixSettings::shopUrl() . '/llms.txt',
            'ftx_domain' => (string) parse_url(FeedTrixSettings::shopUrl(), PHP_URL_HOST),
            'ftx_preview' => $this->previewProduct(),
            'ftx_friendly_urls' => (bool) Configuration::get('PS_REWRITING_SETTINGS'),
            'ftx_icon' => $this->_path . 'logo.png',
        ]);

        return $output . $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }

    /** Full URL of the module configuration (forms). */
    private function configUrl()
    {
        $url = $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name]);
        if (0 === strpos($url, '/')) {
            $url = Tools::getShopDomainSsl(true) . $url;
        } elseif (!preg_match('#^https?://#', $url)) {
            $url = Tools::getShopDomainSsl(true) . __PS_BASE_URI__ . basename(_PS_ADMIN_DIR_) . '/' . $url;
        }

        return $url;
    }
}
