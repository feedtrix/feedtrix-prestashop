<?php
/**
 * https://shop.example/{key}.txt – IndexNow verification file (Bing / ChatGPT Search, Copilot).
 *
 * @author    FeedTrix
 * @copyright FeedTrix
 * @license   AFL-3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class FeedtrixIndexnowModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $key = FeedTrixSettings::indexNowKey();
        $requested = strtolower((string) Tools::getValue('key'));
        if ('' === $key || !hash_equals($key, $requested)) {
            FeedTrixResponse::text(404, "Not found\n", ['Cache-Control' => 'no-store']);
        }
        FeedTrixResponse::text(200, $key, ['Cache-Control' => 'public, max-age=86400']);
    }
}
