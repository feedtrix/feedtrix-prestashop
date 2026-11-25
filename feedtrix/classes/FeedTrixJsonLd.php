<?php
/**
 * FeedTrix JSON-LD on the product page (same as in the WooCommerce plugin).
 *
 * Content prepared by FeedTrix (description for AI assistants, attributes from the description, questions and answers) goes into the
 * product structured data:
 *  - when the theme (or another module) prints its own Product node, the module extends it (actionOutputHTMLBefore),
 *    so the page has one product with the price, availability and reviews from the store,
 *  - when the page has no structured data, the module adds its own Product node with the current price and availability,
 *  - questions and answers go into a separate FAQPage node and, when enabled, also into a product tab.
 *
 * GET {api}/shops/{store_key}/products/{id}/json-ld, local copy in var/cache. FeedTrix removes the copy through
 * controller=purge when the product content changes, so it can live long.
 *
 * @author    FeedTrix
 * @copyright FeedTrix
 * @license   AFL-3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class FeedTrixJsonLd
{
    public const TTL_ERROR = 3600; // API not responding

    /** @var array<int, array|null> */
    private static $loaded = [];

    private static function file($idProduct)
    {
        $generation = (int) FeedTrixSettings::get('JSONLD_GEN', 0);

        return _PS_CACHE_DIR_ . 'feedtrix/jsonld_' . (int) FeedTrix::context()->shop->id . '_' . $generation . '_' . (int) $idProduct . '.json';
    }

    public static function purge($idProduct)
    {
        @unlink(self::file($idProduct));
        unset(self::$loaded[(int) $idProduct]);
    }

    /** Invalidates all copies (new generation) and removes the previous files. */
    public static function purgeAll()
    {
        $old = glob(_PS_CACHE_DIR_ . 'feedtrix/jsonld_' . (int) FeedTrix::context()->shop->id . '_*.json') ?: [];
        FeedTrixSettings::set('JSONLD_GEN', (int) FeedTrixSettings::get('JSONLD_GEN', 0) + 1);
        foreach (array_slice($old, 0, 5000) as $path) {
            @unlink($path);
        }
        self::$loaded = [];
    }

    /**
     * FeedTrix response for a product: product, faq, ai. Null: unknown product or the API is not responding.
     *
     * @return array<string, mixed>|null
     */
    public static function data($idProduct)
    {
        $idProduct = (int) $idProduct;
        if ($idProduct <= 0) {
            return null;
        }
        if (array_key_exists($idProduct, self::$loaded)) {
            return self::$loaded[$idProduct];
        }
        $file = self::file($idProduct);
        if (is_file($file)) {
            $cached = json_decode((string) file_get_contents($file), true);
            if (is_array($cached) && isset($cached['exp']) && $cached['exp'] > time()) {
                return self::$loaded[$idProduct] = is_array($cached['data']) && isset($cached['data']['product']) ? $cached['data'] : null;
            }
        }

        $response = FeedTrixApi::request('GET', '/shops/' . rawurlencode(FeedTrixSettings::storeKey()) . '/products/' . $idProduct . '/json-ld', '', [], 2);
        $data = 200 === $response['status'] && is_array($response['data']) && isset($response['data']['product']) && is_array($response['data']['product']) ? $response['data'] : null;

        // Missing data is cached too, so the API is not asked on every page view.
        // A valid response (also "no AI content yet" or 404) lives as long as set in FeedTrix;
        // only an API failure (and HTTP 402: inactive subscription) gets a short lifetime.
        $answered = 200 === $response['status'] || 404 === $response['status'];
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0775, true);
        }
        @file_put_contents($file, json_encode(['exp' => time() + ($answered ? FeedTrixSettings::productTtl() : self::TTL_ERROR), 'data' => $data]));

        return self::$loaded[$idProduct] = $data;
    }

    /**
     * Extends an existing Product node with FeedTrix content. Price, availability and reviews stay from the store.
     *
     * @param array<string, mixed> $markup
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function merge(array $markup, array $data)
    {
        $ours = $data['product'];
        if (!empty($data['ai']) && !empty($ours['description'])) {
            $markup['description'] = $ours['description'];
        }

        $properties = [];
        $existing = isset($markup['additionalProperty']) ? (array) $markup['additionalProperty'] : [];
        $added = isset($ours['additionalProperty']) ? (array) $ours['additionalProperty'] : [];
        foreach (array_merge($existing, $added) as $property) {
            $key = is_array($property) && isset($property['name']) ? strtolower((string) $property['name']) : '';
            if ('' !== $key && !isset($properties[$key])) {
                $properties[$key] = $property; // store attributes take precedence
            }
        }
        if ($properties) {
            $markup['additionalProperty'] = array_values($properties);
        }

        // offer link with the FeedTrix tag (AI sales attribution)
        if (isset($markup['offers']) && is_array($markup['offers'])) {
            $single = isset($markup['offers']['@type']);
            $offers = $single ? [$markup['offers']] : $markup['offers'];
            foreach ($offers as $i => $offer) {
                if (is_array($offer) && !empty($offer['url']) && false === strpos((string) $offer['url'], 'utm_source=')) {
                    $offers[$i]['url'] = self::tagged((string) $offer['url']);
                }
            }
            $markup['offers'] = $single ? $offers[0] : $offers;
        }

        return $markup;
    }

    /** Tag before the fragment (#/1-size-s… in PrestaShop combination URLs). */
    private static function tagged($url)
    {
        $hash = strpos($url, '#');
        $fragment = false === $hash ? '' : substr($url, $hash);
        $url = false === $hash ? $url : substr($url, 0, $hash);

        return $url . (false === strpos($url, '?') ? '?' : '&') . 'utm_source=feedtrix&utm_medium=json_ld' . $fragment;
    }

    private static function encode(array $node)
    {
        // JSON_HEX_TAG: the content must not close the <script> tag
        return json_encode($node, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    }

    /**
     * Product page (actionOutputHTMLBefore): extends Product nodes in existing JSON-LD scripts,
     * and before </body> adds FAQPage and, when nothing else printed a Product node, also Product.
     */
    public static function filterHtml($html, $idProduct)
    {
        $data = self::data($idProduct);
        if (null === $data || false === stripos($html, '</body>')) {
            return $html;
        }

        $merged = false;
        $html = preg_replace_callback(
            '#(<script[^>]+type=["\']application/ld\+json["\'][^>]*>)(.*?)(</script>)#is',
            function ($m) use ($data, &$merged) {
                if (false !== strpos($m[1], 'feedtrix-json-ld')) {
                    return $m[0];
                }
                $node = json_decode(trim($m[2]), true);
                if (!is_array($node)) {
                    return $m[0];
                }
                $changed = false;
                $node = self::mergeProducts($node, $data, $changed);
                if (!$changed) {
                    return $m[0];
                }
                $merged = true;

                return $m[1] . self::encode($node) . $m[3];
            },
            $html
        );

        $nodes = [];
        if (!$merged) {
            $nodes[] = self::withLiveOffer($data['product'], $idProduct);
        }
        if (isset($data['faq']) && is_array($data['faq']) && !empty($data['faq']['mainEntity'])) {
            $nodes[] = ['@context' => 'https://schema.org'] + $data['faq'];
        }
        $scripts = '';
        foreach ($nodes as $node) {
            $scripts .= '<script type="application/ld+json" class="feedtrix-json-ld">' . self::encode($node) . "</script>\n";
        }
        if ('' === $scripts) {
            return $html;
        }
        $at = strripos($html, '</body>');

        return substr($html, 0, $at) . $scripts . substr($html, $at);
    }

    /** Product node in a script: a single object, a list of nodes or @graph. */
    private static function mergeProducts(array $node, array $data, &$changed)
    {
        if (self::isProduct($node)) {
            $changed = true;

            return self::merge($node, $data);
        }
        if (isset($node['@graph']) && is_array($node['@graph'])) {
            $node['@graph'] = self::mergeProducts($node['@graph'], $data, $changed);

            return $node;
        }
        if (array_keys($node) === range(0, count($node) - 1)) {
            foreach ($node as $i => $item) {
                if (is_array($item) && !$changed && self::isProduct($item)) {
                    $node[$i] = self::merge($item, $data);
                    $changed = true;
                }
            }
        }

        return $node;
    }

    private static function isProduct(array $node)
    {
        $type = isset($node['@type']) ? $node['@type'] : null;

        return 'Product' === $type || (is_array($type) && in_array('Product', $type, true));
    }

    /** Price and availability always from the store (FeedTrix data can be a few minutes old). */
    private static function withLiveOffer(array $node, $idProduct)
    {
        $node = ['@context' => 'https://schema.org'] + $node;
        $snapshot = FeedTrixCatalog::snapshot($idProduct);
        if (null === $snapshot['price']) {
            unset($node['offers']);

            return $node;
        }
        $node['offers'] = [
            '@type' => 'Offer',
            'url' => self::tagged(FeedTrix::context()->link->getProductLink((int) $idProduct)),
            'price' => $snapshot['price'],
            'priceCurrency' => $snapshot['currency'],
            'availability' => 'https://schema.org/' . ($snapshot['in_stock'] ? 'InStock' : 'OutOfStock'),
            'itemCondition' => 'https://schema.org/NewCondition',
        ];

        return $node;
    }

    /** @return array<int, array{q: string, a: string}> questions and answers for the product tab */
    public static function faq($idProduct)
    {
        $data = self::data($idProduct);
        $items = isset($data['faq']['mainEntity']) && is_array($data['faq']['mainEntity']) ? $data['faq']['mainEntity'] : [];
        $faq = [];
        foreach ($items as $item) {
            $question = isset($item['name']) ? (string) $item['name'] : '';
            $answer = isset($item['acceptedAnswer']['text']) ? (string) $item['acceptedAnswer']['text'] : '';
            if ('' !== $question && '' !== $answer) {
                $faq[] = ['q' => $question, 'a' => $answer];
            }
        }

        return $faq;
    }
}
