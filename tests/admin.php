<?php
/**
 * Admin side smoke tests: product checkbox, saving, product page button.
 *
 *   php tests/admin.php
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

require dirname(__DIR__) . '/print-options.php';

$plugin = new Print_Options_WC();

$GLOBALS['pov_test']['posts'][55] = array(
    'post_type'    => 'product',
    'post_status'  => 'publish',
    'post_title'   => 'Meniu restaurant A4',
    'post_content' => '',
);
$GLOBALS['pov_test']['posts'][10] = array(
    'post_type'    => 'page',
    'post_status'  => 'publish',
    'post_title'   => 'Cere ofertă',
    'post_content' => '[pov_quote_form]',
);
update_option('pov_quote_page_id', 10);

echo "Product meta box\n";
update_post_meta(55, '_pov_settings', array('quote' => 1, 'min' => 1, 'step' => 1, 'mode' => 'fixed'));
ob_start();
$plugin->box_html((object) array('ID' => 55));
$html = ob_get_clean();

check(false !== strpos($html, 'name="pov_quote"'), 'checkbox field is rendered');
check(false !== strpos($html, 'Afișare buton „Cere ofertă”'), 'checkbox label matches the request');
check(false !== strpos($html, "checked='checked'"), 'checkbox reflects the saved value');
check(false !== strpos($html, 'https://example.test/?p=10'), 'link to the quote page is rendered');

echo "\nSaving the product\n";
$_POST = array(
    'pov_nonce'      => 'testnonce',
    'pov_min'        => 1,
    'pov_step'       => 1,
    'pov_mode'       => 'fixed',
    'pov_tiers'      => '',
    'pov_quote'      => '1',
    'pov_upload_required' => '1',
);
$plugin->save(55);
$settings = get_post_meta(55, '_pov_settings', true);
check(is_array($settings) && 1 === (int) $settings['quote'], 'quote flag is saved when checked');

unset($_POST['pov_quote']);
$plugin->save(55);
$settings = get_post_meta(55, '_pov_settings', true);
check(0 === (int) $settings['quote'], 'quote flag is cleared when unchecked');

check(0 === (int) $plugin->settings(55)['min'] - 1, 'existing settings still round trip');

echo "\nProduct page button\n";
update_post_meta(125, '_pov_settings', array('quote' => 1, 'min' => 1, 'step' => 1, 'mode' => 'fixed'));
$GLOBALS['product'] = new WC_Product(125, 'Meniu restaurant A4');
ob_start();
$plugin->quote_button();
$button = ob_get_clean();

check(false !== strpos($button, 'pov-quote-btn'), 'button markup rendered');
check(false !== strpos($button, 'Cere ofertă'), 'button label is correct');
check(false !== strpos($button, 'pov_product=125'), 'button links to the quote page with the product id');

update_post_meta(126, '_pov_settings', array('quote' => 0, 'min' => 1, 'step' => 1, 'mode' => 'fixed'));
$GLOBALS['product'] = new WC_Product(126, 'Produs fara oferta');
ob_start();
$plugin->quote_button();
$button = ob_get_clean();
check('' === trim($button), 'no button when the option is off');

echo "\nProduct page hooks\n";
update_post_meta(123, '_pov_settings', array('quote' => 1, 'min' => 1, 'step' => 1, 'mode' => 'fixed'));
$GLOBALS['product'] = new WC_Product(123, 'Meniu restaurant A4', 12.5, true);
ob_start();
do_action('woocommerce_after_add_to_cart_form');
do_action('woocommerce_single_product_summary');
$output = ob_get_clean();
check(1 === substr_count($output, 'pov-quote-btn'), 'button prints once for a purchasable product');

// A product without a price is not purchasable, so WooCommerce never renders
// the add-to-cart form; the summary hook still has to output the button.
update_post_meta(124, '_pov_settings', array('quote' => 1, 'min' => 1, 'step' => 1, 'mode' => 'fixed'));
$GLOBALS['product'] = new WC_Product(124, 'Proiect complex fara pret', '', false);
ob_start();
do_action('woocommerce_single_product_summary');
$output = ob_get_clean();
check(1 === substr_count($output, 'pov-quote-btn'), 'button appears for a product without a price');
check(false !== strpos($output, 'pov_product=124'), 'button keeps the product id');

echo "\nQuote page URL\n";
check('https://example.test/?p=10' === POV_Quote::page_url(), 'page url comes from the stored page');

echo "\n" . ($failures ? "FAILURES: {$failures}" : 'All checks passed') . " ({$checks} checks)\n";
exit($failures ? 1 : 0);
