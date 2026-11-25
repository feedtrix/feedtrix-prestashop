<?php
/**
 * Connecting the store to FeedTrix from the module configuration: Store Key + API key copied from the FeedTrix panel
 * (the account and store are created in the panel). POST {api}/plugin/connect-keys signed with the entered keys.
 *
 * @author    FeedTrix
 * @copyright FeedTrix
 * @license   AFL-3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class FeedTrixOnboarding
{
    /** @return array{ok: bool, message: string} */
    public static function keys($storeKey, $apiKey)
    {
        if (!preg_match('/^ftx_[a-f0-9]{24}$/', $storeKey) || '' === $apiKey) {
            return ['ok' => false, 'message' => FeedTrix::t('Enter the Store Key (ftx_…) and the API key from the FeedTrix panel.')];
        }
        $body = json_encode(['platform' => 'prestashop', 'shop_url' => FeedTrixSettings::shopUrl()]);

        return self::finish(FeedTrixApi::request('POST', '/plugin/connect-keys', $body, FeedTrixApi::signature($body, $storeKey, $apiKey), 30));
    }

    /** @return array{ok: bool, message: string} */
    private static function finish(array $response)
    {
        $data = $response['data'];
        if (!is_array($data) || empty($data['store_key'])) {
            $message = is_array($data) && !empty($data['error']) ? (string) $data['error']
                : ('' !== $response['error'] ? $response['error'] : FeedTrix::t('Unexpected response from FeedTrix.') . ' (HTTP ' . $response['status'] . ')');

            return ['ok' => false, 'message' => $message];
        }

        FeedTrixSettings::saveConnection(
            ['shop_id' => (string) $data['store_key'], 'api_key' => (string) $data['api_key'], 'indexnow_key' => (string) $data['indexnow_key']],
            [
                'email' => isset($data['email']) ? (string) $data['email'] : '',
                'verified' => !empty($data['verified']),
                'panel_url' => isset($data['panel_url']) ? (string) $data['panel_url'] : '',
                'account' => isset($data['account']) ? (string) $data['account'] : '',
            ]
        );

        return ['ok' => true, 'message' => FeedTrix::t('Your store is now connected to your FeedTrix account. We emailed you a link to the panel.')];
    }
}
