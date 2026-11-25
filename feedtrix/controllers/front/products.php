<?php
/**
 * Public catalog for FeedTrix (only active products visible in the catalog – the same data a store customer sees).
 *   ?page=1&per_page=100 -> {"total", "page", "pages", "per_page", "currency", "products": [...]}
 *   ?id=12               -> {"currency", "product": {...}} or 404
 *
 * @author    FeedTrix
 * @copyright FeedTrix
 * @license   AFL-3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class FeedtrixProductsModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        if (!FeedTrixSettings::isConnected()) {
            FeedTrixResponse::json(403, ['error' => 'FeedTrix is not configured.']);
        }
        $currency = $this->context->currency ? $this->context->currency->iso_code : null;

        $id = (int) Tools::getValue('id');
        if ($id > 0) {
            $product = FeedTrixCatalog::isPublic($id) ? FeedTrixCatalog::export($id) : null;
            if (null === $product) {
                FeedTrixResponse::json(404, ['error' => 'not found']);
            }
            FeedTrixResponse::json(200, ['currency' => $currency, 'product' => $product]);
        }

        $perPage = max(1, min(FeedTrixCatalog::MAX_PER_PAGE, (int) Tools::getValue('per_page', FeedTrixCatalog::MAX_PER_PAGE)));
        $page = max(1, (int) Tools::getValue('page', 1));
        $total = FeedTrixCatalog::count();
        $products = [];
        foreach (FeedTrixCatalog::ids($page, $perPage) as $productId) {
            $product = FeedTrixCatalog::export($productId);
            if (null !== $product) {
                $products[] = $product;
            }
        }

        FeedTrixResponse::json(200, [
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
            'per_page' => $perPage,
            'currency' => $currency,
            'products' => $products,
        ]);
    }
}
