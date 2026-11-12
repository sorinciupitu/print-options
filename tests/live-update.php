<?php
/**
 * Live check against the public GitHub API: does an installed 1.7.0 build see
 * the latest release as an available update?
 *
 *   POV_TEST_VERSION=1.7.0 php tests/live-update.php
 */

require __DIR__ . '/bootstrap.php';

$GLOBALS['pov_test']['remote'] = function ($url, $args = array()) {
    $headers = array('User-Agent: print-options-test');
    if (!empty($args['headers']['Accept'])) {
        $headers[] = 'Accept: ' . $args['headers']['Accept'];
    }
    $handle = curl_init($url);
    curl_setopt_array($handle, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
    ));
    $body = curl_exec($handle);
    $code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

    return array('response' => array('code' => $code), 'body' => $body);
};

require dirname(__DIR__) . '/includes/class-pov-updater.php';

delete_site_transient(POV_Updater::CACHE_KEY);
$release = POV_Updater::release();

echo 'Installed version: ' . POV_VERSION . "\n";
echo 'Latest release:    ' . ($release['version'] ? $release['version'] : '(none)') . "\n";
echo 'Package:           ' . ($release['package'] ? $release['package'] : '(none)') . "\n";

$transient = new stdClass();
$result    = POV_Updater::inject_update($transient);
$key       = 'print-options/print-options.php';

if (isset($result->response[$key])) {
    echo "RESULT: update offered -> {$result->response[$key]->new_version}\n";
    exit(0);
}

echo "RESULT: no update offered\n";
exit(1);
