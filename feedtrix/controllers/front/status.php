<?php
/**
 * Module status for the connection test in the FeedTrix panel (HMAC signature). Also sends a test.ping webhook.
 *
 * @author    FeedTrix
 * @copyright FeedTrix
 * @license   AFL-3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class FeedtrixStatusModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        if (!FeedTrixSettings::isConnected()) {
            FeedTrixResponse::json(403, ['code' => 'feedtrix_not_configured', 'message' => 'FeedTrix is not configured.']);
        }
        if (!FeedTrixApi::verifyIncoming('')) {
            FeedTrixResponse::json(401, ['code' => 'feedtrix_forbidden', 'message' => 'Invalid signature.']);
        }

        $ping = FeedTrixWebhooks::send([['type' => 'test.ping']], 10);

        FeedTrixResponse::json(200, [
            'version' => FeedTrix::VERSION,
            'prestashop' => true,
            'ps_version' => _PS_VERSION_,
            'php_version' => PHP_VERSION,
            'llms_txt' => FeedTrixSettings::llmsEnabled(),
            'bot_tracking' => FeedTrixSettings::botsEnabled(),
            'attribution' => FeedTrixSettings::attributionEnabled(),
            'json_ld' => FeedTrixSettings::jsonLdEnabled(),
            'remote' => FeedTrixSettings::remoteState(), // switches and copy lifetimes, as in controller=settings
            'llms_full' => true,
            'hooks' => count(FeedTrix::$productHooks),
            'webhook_test' => '' !== $ping['error'] ? $ping['error'] : $ping['status'],
            'friendly_urls' => (bool) Configuration::get('PS_REWRITING_SETTINGS'),
            'indexnow' => '' !== FeedTrixSettings::indexNowKey(),
        ]);
    }
}
