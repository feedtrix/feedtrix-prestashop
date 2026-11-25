<?php
/**
 * GET|POST controller=settings: switches and copy lifetimes controlled from FeedTrix (signed like status).
 * POST: {"settings": {"json_ld": true, …}, "cache": {"product_ttl": 86400, "llms_ttl": 86400}} changes only the given values.
 * Response: {"settings": {…}, "cache": {…}} (state after saving).
 *
 * @author    FeedTrix
 * @copyright FeedTrix
 * @license   AFL-3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class FeedtrixSettingsModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $raw = (string) file_get_contents('php://input');
        if (!FeedTrixSettings::isConnected()) {
            FeedTrixResponse::json(403, ['code' => 'feedtrix_not_configured', 'message' => 'FeedTrix is not configured.']);
        }
        if (!FeedTrixApi::verifyIncoming($raw)) {
            FeedTrixResponse::json(401, ['code' => 'feedtrix_forbidden', 'message' => 'Invalid signature.']);
        }
        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $body = json_decode($raw, true);
            $body = is_array($body) ? $body : [];
            FeedTrixSettings::updateRemote(
                isset($body['settings']) && is_array($body['settings']) ? $body['settings'] : [],
                isset($body['cache']) && is_array($body['cache']) ? $body['cache'] : []
            );
        }

        FeedTrixResponse::json(200, FeedTrixSettings::remoteState());
    }
}
