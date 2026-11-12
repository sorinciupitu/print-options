<?php
/**
 * Renders the "Cere ofertă" page to a standalone HTML file for visual review.
 *
 *   php tests/render-quote.php dist/preview-quote.html
 */

require __DIR__ . '/bootstrap.php';

$GLOBALS['pov_test']['posts'][10] = array(
    'post_type'    => 'page',
    'post_status'  => 'publish',
    'post_title'   => 'Cere ofertă',
    'post_content' => '[pov_quote_form]',
);
update_option('pov_quote_page_id', 10);
update_option('pov_quote_email', 'oferte@example.test');

require dirname(__DIR__) . '/includes/class-pov-quote.php';
POV_Quote::init();

$_GET['pov_product'] = 123;

if (getenv('POV_PREVIEW_ERRORS')) {
    $_POST = array(
        'pov_quote_action' => '1',
        'pov_quote_nonce'  => 'testnonce',
        'pov_quote_ts'     => time() - 30,
        'pov_quote'        => array(
            'name'  => 'Sorin',
            'email' => 'adresa-gresita',
            'phone' => '0722 123 456',
            'items' => array(
                '0'  => array('name' => 'Meniu restaurant A4', 'desc' => '200 buc, hartie 350g, plasticare mat', 'qty' => 200, 'product_id' => 123),
                'n1' => array('name' => 'Carti de vizita', 'desc' => '', 'qty' => 500, 'product_id' => 0),
            ),
        ),
    );
    try {
        POV_Quote::maybe_handle_submission();
    } catch (RuntimeException $exception) {
        echo 'redirected: ' . $exception->getMessage() . "\n";
    }
}

$form = POV_Quote::shortcode();
$css  = file_get_contents(dirname(__DIR__) . '/assets/quote.css');

$target = isset($argv[1]) ? $argv[1] : dirname(__DIR__) . '/dist/preview-quote.html';
if (!is_dir(dirname($target))) {
    mkdir(dirname($target), 0777, true);
}

$html = '<!doctype html><html lang="ro"><head><meta charset="utf-8">'
    . '<title>Cere ofertă — preview</title>'
    . '<style>body{margin:0;background:#fff;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:#344054;-webkit-font-smoothing:antialiased}'
    . '.pov-preview-shell{padding:42px 40px 0}</style>'
    . '<style>' . $css . '</style></head><body><div class="pov-preview-shell">' . $form . '</div></body></html>';

file_put_contents($target, $html);
echo "Wrote {$target}\n";
