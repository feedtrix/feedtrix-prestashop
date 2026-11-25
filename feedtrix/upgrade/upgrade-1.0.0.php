<?php
/**
 * 1.0.0: JSON-LD and FAQ tab, /llms-full.txt, AI sales attribution, purge and settings controlled from FeedTrix.
 * New hooks and a table of orders with an AI visit; the old /llms.txt copy flag is no longer needed.
 *
 * @author    FeedTrix
 * @copyright FeedTrix
 * @license   AFL-3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_0($module)
{
    Configuration::deleteByName('FEEDTRIX_LLMS_STALE');
    @unlink(_PS_CACHE_DIR_ . 'feedtrix_llms_' . (int) FeedTrix::context()->shop->id . '.txt');

    return FeedTrixAttribution::installDb() && $module->registerHook(FeedTrix::$hooks);
}
