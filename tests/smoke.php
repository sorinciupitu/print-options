<?php
/**
 * Smoke tests for the quote flow and the GitHub updater.
 *
 *   php tests/smoke.php
 */

require __DIR__ . '/bootstrap.php';

$failures = 0;
$checks   = 0;

function check($condition, $label) {
    global $failures, $checks;
    $checks++;
    if ($condition) {
        echo "  ok   {$label}\n";
    } else {
        $failures++;
        echo "  FAIL {$label}\n";
    }
}

function reset_quote_state() {
    $reflection = new ReflectionClass('POV_Quote');
    foreach (array('errors' => array(), 'old' => array(), 'uploading' => false) as $name => $value) {
        $property = $reflection->getProperty($name);
        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }
        $property->setValue(null, $value);
    }
}

function reset_store() {
    $GLOBALS['pov_test']['posts']      = array();
    $GLOBALS['pov_test']['meta']       = array();
    $GLOBALS['pov_test']['mails']      = array();
    $GLOBALS['pov_test']['transients'] = array();
    $GLOBALS['pov_test']['redirect']   = null;
    $GLOBALS['pov_test']['next_id']    = 500;
    $_GET                              = array();
    $_POST                             = array();
    $_FILES                            = array();
}

function quote_posts() {
    $ids = array();
    foreach ($GLOBALS['pov_test']['posts'] as $id => $post) {
        if ('pov_quote' === $post['post_type']) {
            $ids[] = $id;
        }
    }
    return $ids;
}

function submit($overrides = array(), $files = array()) {
    $defaults = array(
        'pov_quote_action' => '1',
        'pov_quote_nonce'  => 'testnonce',
        'pov_quote_ts'     => time() - 20,
        'pov_quote'        => array(
            'name'  => 'Ana Popescu',
            'email' => 'ana@example.com',
            'phone' => '0722 123 456',
            'items' => array(
                '0' => array(
                    'name'       => 'Meniuri restaurant',
                    'desc'       => 'A4, 350g, 200 buc',
                    'qty'        => 200,
                    'product_id' => 123,
                ),
            ),
        ),
    );

    $post = array_replace_recursive($defaults, $overrides);

    $_POST  = $post;
    $_FILES = $files;

    $redirect = '';
    try {
        POV_Quote::maybe_handle_submission();
    } catch (RuntimeException $exception) {
        $redirect = $exception->getMessage();
    }
    return $redirect;
}

require dirname(__DIR__) . '/includes/class-pov-quote.php';
require dirname(__DIR__) . '/includes/class-pov-updater.php';

POV_Quote::init();
POV_Updater::init();

$GLOBALS['pov_test']['posts'][10] = array(
    'post_type'    => 'page',
    'post_status'  => 'publish',
    'post_title'   => 'Cere ofertă',
    'post_content' => '[pov_quote_form]',
);
update_option('pov_quote_page_id', 10);
update_option('pov_quote_email', 'oferte@example.test');

/* ------------------------------------------------------------------------- */

echo "Page creation\n";
reset_store();
$page_id = POV_Quote::create_page();
check($page_id > 0, 'create_page() returns a page id');
check(false !== strpos(get_post($page_id)->post_content, '[pov_quote_form]'), 'page contains the shortcode');

echo "\nPrefill from ?pov_product\n";
reset_store();
$_GET['pov_product'] = 123;
$html = POV_Quote::shortcode();
check(false !== strpos($html, 'value="Meniu restaurant A4"'), 'product name is prefilled');
check(false !== strpos($html, 'value="123"'), 'source product id is kept');
check(false !== strpos($html, 'name="pov_quote[items][0][name]"'), 'input names are correct');

echo "\nValid submission\n";
reset_store();
$redirect = submit();
check(false !== strpos($redirect, 'pov_sent='), 'redirects after a valid request');
$ids = quote_posts();
check(1 === count($ids), 'one quote is stored');
if ($ids) {
    $id = $ids[0];
    check('Ana Popescu' === get_post_meta($id, '_pov_quote_name', true), 'name stored');
    check('new' === get_post_meta($id, POV_Quote::STATUS_META, true), 'status starts as new');
    $items = get_post_meta($id, '_pov_quote_items', true);
    check(is_array($items) && 1 === count($items) && 'Meniuri restaurant' === $items[1]['name'], 'item stored with 1-based index');
    check(200 === (int) $items[1]['qty'], 'quantity stored');
}
check(1 === count($GLOBALS['pov_test']['mails']), 'notification email sent');
if ($GLOBALS['pov_test']['mails']) {
    $mail = $GLOBALS['pov_test']['mails'][0];
    check('oferte@example.test' === $mail['to'][0], 'email goes to the configured recipient');
    check(false !== strpos($mail['subject'], 'Cerere ofertă nouă'), 'subject is set');
    check(false !== strpos($mail['message'], 'Meniuri restaurant'), 'email body lists the product');
}

echo "\nInvalid submission\n";
reset_store();
reset_quote_state();
$redirect = submit(array('pov_quote' => array('email' => 'not-an-email', 'name' => '')));
check('' === $redirect, 'no redirect when validation fails');
check(0 === count(quote_posts()), 'nothing stored');
$html = POV_Quote::shortcode();
check(false !== strpos($html, 'Completează numele tău.'), 'name error is shown');
check(false !== strpos($html, 'Introdu o adresă de email validă.'), 'email error is shown');
check(false !== strpos($html, 'value="not-an-email"'), 'typed values are kept');

echo "\nHoneypot\n";
reset_store();
reset_quote_state();
$redirect = submit(array('pov_quote_hp' => 'spam'));
check(false !== strpos($redirect, 'pov_sent=1'), 'bot gets a fake success');
check(0 === count(quote_posts()), 'bot submission is not stored');

echo "\nRate limit\n";
reset_store();
reset_quote_state();
set_transient('pov_quote_rl_' . md5('0.0.0.0'), 6, HOUR_IN_SECONDS);
$redirect = submit();
check('' === $redirect, 'blocked after the limit');
check(0 === count(quote_posts()), 'rate limited request is not stored');

echo "\nFile upload\n";
reset_store();
reset_quote_state();
$tmp = sys_get_temp_dir() . '/pov-test-graphic.eps';
file_put_contents($tmp, "%!PS-Adobe-3.0\n%%BoundingBox: 0 0 10 10\n");
$GLOBALS['pov_test']['uploads']['grafica.eps'] = array(
    'file' => $tmp,
    'url'  => 'https://example.test/uploads/grafica.eps',
    'type' => 'application/postscript',
);
$files = array(
    'pov_files' => array(
        'name'     => array('0' => array('grafica.eps')),
        'type'     => array('0' => array('application/postscript')),
        'tmp_name' => array('0' => array($tmp)),
        'error'    => array('0' => array(0)),
        'size'     => array('0' => array(filesize($tmp))),
    ),
);
$redirect = submit(array(), $files);
check(false !== strpos($redirect, 'pov_sent='), 'upload accepted');
$ids = quote_posts();
if ($ids) {
    $items = get_post_meta($ids[0], '_pov_quote_items', true);
    check(1 === count($items[1]['files']), 'file saved on the item');
    check('grafica.eps' === $items[1]['files'][0]['name'], 'file name kept');
}
check(false !== strpos($GLOBALS['pov_test']['mails'][0]['message'], 'grafica.eps'), 'email links the file');

echo "\nUpdater\n";
$GLOBALS['pov_test']['remote'] = function ($url) {
    return array(
        'response' => array('code' => 200),
        'body'     => json_encode(array(
            'tag_name'     => 'v1.9.0',
            'html_url'     => 'https://github.com/sorinciupitu/print-options/releases/tag/v1.9.0',
            'body'         => "## Ce e nou\n* Test",
            'published_at' => '2026-11-12T10:00:00Z',
            'assets'       => array(array(
                'name'                 => 'print-options.zip',
                'browser_download_url' => 'https://github.com/sorinciupitu/print-options/releases/download/v1.9.0/print-options.zip',
            )),
            'zipball_url'  => 'https://api.github.com/repos/sorinciupitu/print-options/zipball/v1.9.0',
        )),
    );
};
delete_site_transient(POV_Updater::CACHE_KEY);
$transient = new stdClass();
$result    = POV_Updater::inject_update($transient);
check(isset($result->response['print-options/print-options.php']), 'newer release is injected');
if (isset($result->response['print-options/print-options.php'])) {
    $entry = $result->response['print-options/print-options.php'];
    check('1.9.0' === $entry->new_version, 'new version exposed');
    check('https://github.com/sorinciupitu/print-options/releases/download/v1.9.0/print-options.zip' === $entry->package, 'release asset used as package');
    check('print-options/print-options.php' === $entry->plugin, 'plugin slug set');
}

delete_site_transient(POV_Updater::CACHE_KEY);
$GLOBALS['pov_test']['remote'] = function () {
    return array(
        'response' => array('code' => 200),
        'body'     => json_encode(array(
            'tag_name'    => 'v1.7.0',
            'html_url'    => 'https://example.test',
            'body'        => '',
            'zipball_url' => 'https://api.github.com/zipball/v1.7.0',
            'assets'      => array(),
        )),
    );
};
$transient = new stdClass();
$result    = POV_Updater::inject_update($transient);
check(empty($result->response), 'same version produces no update');

delete_site_transient(POV_Updater::CACHE_KEY);
$GLOBALS['pov_test']['remote'] = function () {
    return array(
        'response' => array('code' => 200),
        'body'     => json_encode(array(
            'tag_name'    => 'v2.0.0',
            'html_url'    => 'https://example.test',
            'body'        => '',
            'zipball_url' => 'https://api.github.com/zipball/v2.0.0',
            'assets'      => array(),
        )),
    );
};
$transient = new stdClass();
$result    = POV_Updater::inject_update($transient);
check(
    isset($result->response['print-options/print-options.php'])
        && 'https://api.github.com/zipball/v2.0.0' === $result->response['print-options/print-options.php']->package,
    'falls back to the zipball when there is no asset'
);

delete_site_transient(POV_Updater::CACHE_KEY);
$GLOBALS['pov_test']['remote'] = function () {
    return new WP_Error('http', 'offline');
};
$transient = new stdClass();
$result    = POV_Updater::inject_update($transient);
check(empty($result->response), 'offline GitHub does not break the plugins screen');

echo "\n" . ($failures ? "FAILURES: {$failures}" : 'All checks passed') . " ({$checks} checks)\n";
exit($failures ? 1 : 0);
