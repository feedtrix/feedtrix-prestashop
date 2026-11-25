<?php
/**
 * Public product data (only active and visible in the catalog), in the FeedTrix contract format:
 * the worker fetches it from controller=products, the webhook adds a price and availability snapshot.
 *
 * @author    FeedTrix
 * @copyright FeedTrix
 * @license   AFL-3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class FeedTrixCatalog
{
    public const MAX_PER_PAGE = 100;

    private static function where()
    {
        $shopId = (int) FeedTrix::context()->shop->id;

        return 'FROM `' . _DB_PREFIX_ . 'product_shop` ps
            LEFT JOIN `' . _DB_PREFIX_ . 'product_sale` sale ON sale.`id_product` = ps.`id_product`
            WHERE ps.`id_shop` = ' . $shopId . ' AND ps.`active` = 1 AND ps.`visibility` IN (\'both\', \'catalog\')';
    }

    public static function count()
    {
        return (int) Db::getInstance()->getValue('SELECT COUNT(*) ' . self::where());
    }

    /** @return int[] IDs, best sellers first */
    public static function ids($page, $perPage)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT ps.`id_product` ' . self::where()
            . ' ORDER BY IFNULL(sale.`quantity`, 0) DESC, ps.`id_product` ASC LIMIT ' . (int) (($page - 1) * $perPage) . ', ' . (int) $perPage
        );

        return array_map('intval', array_column(is_array($rows) ? $rows : [], 'id_product'));
    }

    public static function isPublic($idProduct)
    {
        return (bool) Db::getInstance()->getValue('SELECT 1 ' . self::where() . ' AND ps.`id_product` = ' . (int) $idProduct);
    }

    /** @return array<string, mixed>|null */
    public static function export($idProduct)
    {
        $context = FeedTrix::context();
        $idLang = (int) $context->language->id;
        $product = new Product((int) $idProduct, true, $idLang, (int) $context->shop->id);
        if (!Validate::isLoadedObject($product)) {
            return null;
        }

        $images = [];
        $imageType = method_exists('ImageType', 'getFormattedName') ? ImageType::getFormattedName('large') : 'large_default';
        foreach (Image::getImages($idLang, $product->id) as $image) {
            $images[] = $context->link->getImageLink($product->link_rewrite, $product->id . '-' . $image['id_image'], $imageType);
        }

        $categories = [];
        foreach (Product::getProductCategoriesFull($product->id, $idLang) as $category) {
            $categories[] = (string) $category['name'];
        }

        $features = [];
        foreach (Product::getFrontFeaturesStatic($idLang, $product->id) as $feature) {
            $features[(string) $feature['name']][] = (string) $feature['value'];
        }

        return [
            'id' => (string) $product->id,
            'name' => (string) $product->name,
            'url' => $context->link->getProductLink($product),
            'reference' => (string) $product->reference,
            'ean13' => (string) $product->ean13,
            'description' => (string) $product->description,
            'description_short' => (string) $product->description_short,
            'categories' => $categories,
            'images' => $images,
            'features' => $features,
        ] + self::snapshot($product->id);
    }

    /** Price with tax and discounts, regular price, availability. */
    public static function snapshot($idProduct)
    {
        $context = self::priceContext();
        $specific = null;
        $price = Product::getPriceStatic((int) $idProduct, true, null, 2, null, false, true, 1, false, null, null, null, $specific, true, true, $context);
        $regular = Product::getPriceStatic((int) $idProduct, true, null, 2, null, false, false, 1, false, null, null, null, $specific, true, true, $context);
        $quantity = (int) StockAvailable::getQuantityAvailableByProduct((int) $idProduct);
        $inStock = !Configuration::get('PS_STOCK_MANAGEMENT') || $quantity > 0
            || Product::isAvailableWhenOutOfStock(StockAvailable::outOfStock((int) $idProduct));

        return [
            'price' => $price > 0 ? number_format((float) $price, 2, '.', '') : null,
            'regular_price' => $regular > 0 ? number_format((float) $regular, 2, '.', '') : null,
            'in_stock' => (bool) $inStock,
            'currency' => $context->currency ? $context->currency->iso_code : null,
        ];
    }

    /**
     * Context for computing prices also outside the storefront and back office (CLI, import, Webservice/ERP):
     * without a cart and employee, Product::getPriceStatic() fails.
     */
    private static function priceContext()
    {
        $context = FeedTrix::context()->cloneContext();
        if (!is_object($context->cart)) {
            $context->cart = new Cart();
        }
        if (!Validate::isLoadedObject($context->currency)) {
            $context->currency = Currency::getDefaultCurrency();
        }

        return $context;
    }
}
