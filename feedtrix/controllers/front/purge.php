<?php
/**
 * POST controller=purge: FeedTrix tells the store that content changed, so local copies are removed
 * and the next request fetches fresh ones. Signed with the store API key (same headers as status).
 * Body: {"llms": true, "products": ["123", "456"]} or {"llms": true, "all_products": true}
 *
 * @author    FeedTrix
 * @copyright FeedTrix
 * @license   AFL-3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class FeedtrixPurgeModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $raw = (string) file_get_contents('php://input');
        if ('POST' !== $_SERVER['REQUEST_METHOD']) {
            FeedTrixResponse::json(405, ['code' => 'method_not_allowed', 'message' => 'POST only.']);
        }
        if (!FeedTrixSettings::isConnected()) {
            FeedTrixResponse::json(403, ['code' => 'feedtrix_not_configured', 'message' => 'FeedTrix is not configured.']);
        }
        if (!FeedTrixApi::verifyIncoming($raw)) {
            FeedTrixResponse::json(401, ['code' => 'feedtrix_forbidden', 'message' => 'Invalid signature.']);
        }

        $body = json_decode($raw, true);
        $body = is_array($body) ? $body : [];
        $products = 0;
        if (!empty($body['llms'])) {
            FeedTrixLlms::purgeLocal();
        }
        if (!empty($body['all_products'])) {
            FeedTrixJsonLd::purgeAll();
            $products = -1;
        } elseif (isset($body['products']) && is_array($body['products'])) {
            foreach (array_slice($body['products'], 0, 500) as $id) {
                if (is_scalar($id) && ctype_digit((string) $id)) {
                    FeedTrixJsonLd::purge((int) $id);
                    ++$products;
                }
            }
        }

        FeedTrixResponse::json(200, ['llms' => !empty($body['llms']), 'products' => $products]);
    }
}
