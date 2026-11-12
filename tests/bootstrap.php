<?php
/**
 * Minimal WordPress stubs used by the local smoke tests.
 * Not part of the distributed plugin.
 */

define('ABSPATH', __DIR__ . '/fake-wp/');
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);
define('POV_VERSION', getenv('POV_TEST_VERSION') ?: '1.7.0');
define('POV_GITHUB_REPO', 'sorinciupitu/print-options');
define('POV_FILE', dirname(__DIR__) . '/print-options.php');

$GLOBALS['pov_test'] = array(
    'options'     => array(),
    'transients'  => array(),
    'meta'        => array(),
    'posts'       => array(),
    'next_id'     => 500,
    'mails'       => array(),
    'redirect'    => null,
    'uploads'     => array(),
    'remote'      => null,
    'actions'     => array(),
    'shortcodes'  => array(),
);

class WP_Post {
    public $ID;
    public $post_type;
    public $post_content;
    public $post_status = 'publish';
    public function __construct($id, $type = 'page', $content = '') {
        $this->ID = $id;
        $this->post_type = $type;
        $this->post_content = $content;
    }
}

class WP_Error {
    private $message;
    public function __construct($code = '', $message = '') {
        $this->message = $message;
    }
    public function get_error_message() {
        return $this->message;
    }
}

function is_wp_error($thing) {
    return $thing instanceof WP_Error;
}

/* Hooks ---------------------------------------------------------------- */

function add_action($hook, $callback, $priority = 10, $args = 1) {
    $GLOBALS['pov_test']['actions'][$hook][] = $callback;
}
function add_filter($hook, $callback, $priority = 10, $args = 1) {
    $GLOBALS['pov_test']['actions'][$hook][] = $callback;
}
function do_action($hook, ...$args) {
    if (empty($GLOBALS['pov_test']['actions'][$hook])) {
        return;
    }
    foreach ($GLOBALS['pov_test']['actions'][$hook] as $callback) {
        call_user_func_array($callback, $args);
    }
}
function apply_filters($hook, $value, ...$args) {
    if (!empty($GLOBALS['pov_test']['actions'][$hook])) {
        foreach ($GLOBALS['pov_test']['actions'][$hook] as $callback) {
            $value = call_user_func($callback, $value, ...$args);
        }
    }
    return $value;
}
function add_shortcode($tag, $callback) {
    $GLOBALS['pov_test']['shortcodes'][$tag] = $callback;
}
function has_shortcode($content, $tag) {
    return false !== strpos((string) $content, '[' . $tag . ']');
}

/* Escaping / i18n ------------------------------------------------------ */

function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_textarea($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_url($url) { return htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8'); }
function esc_url_raw($url) { return (string) $url; }
function esc_html__($text, $domain = '') { return esc_html($text); }
function wp_kses_post($text) { return $text; }
function wp_specialchars_decode($text, $quote = ENT_NOQUOTES) { return html_entity_decode((string) $text, $quote, 'UTF-8'); }
function wpautop($text) { return '<p>' . $text . '</p>'; }

/* Sanitizing ----------------------------------------------------------- */

function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_email($value) { return filter_var((string) $value, FILTER_SANITIZE_EMAIL); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function sanitize_file_name($value) { return preg_replace('/[^A-Za-z0-9._\-]/', '-', (string) $value); }
function sanitize_mime_type($value) { return preg_replace('/[^a-z0-9\/+.\-]/', '', strtolower((string) $value)); }
function sanitize_hex_color($value) { return (string) $value; }
function wp_unslash($value) { return $value; }
function absint($value) { return abs((int) $value); }
function is_email($value) { return (bool) filter_var((string) $value, FILTER_VALIDATE_EMAIL); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function wp_rand($min = 0, $max = 0) { return random_int($min, max($min, $max)); }
function size_format($bytes, $decimals = 0) {
    $bytes = (float) $bytes;
    if ($bytes >= 1048576) { return round($bytes / 1048576, 1) . ' MB'; }
    if ($bytes >= 1024) { return round($bytes / 1024, 1) . ' KB'; }
    return (int) $bytes . ' B';
}

/* URLs ----------------------------------------------------------------- */

function home_url($path = '') { return 'https://example.test' . $path; }
function admin_url($path = '') { return 'https://example.test/wp-admin/' . $path; }
function plugins_url($path = '', $plugin = '') { return 'https://example.test/wp-content/plugins/print-options/' . $path; }
function get_permalink($post = 0) { return 'https://example.test/?p=' . (int) $post; }
function get_privacy_policy_url() { return 'https://example.test/privacy/'; }
function wp_parse_url($url, $component = -1) { return parse_url((string) $url, $component); }
function add_query_arg(...$args) {
    if (is_array($args[0])) {
        $params = $args[0];
        $url    = isset($args[1]) ? $args[1] : '';
    } else {
        $params = array($args[0] => $args[1]);
        $url    = isset($args[2]) ? $args[2] : '';
    }
    $sep = false === strpos((string) $url, '?') ? '?' : '&';
    $pairs = array();
    foreach ($params as $key => $value) {
        $pairs[] = rawurlencode($key) . '=' . rawurlencode((string) $value);
    }
    return $url . $sep . implode('&', $pairs);
}
function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $display = true) {
    $html = '<input type="hidden" name="' . esc_attr($name) . '" value="testnonce">';
    if ($display) {
        echo $html;
        return;
    }
    return $html;
}
function wp_nonce_url($url, $action = -1, $name = '_wpnonce') { return $url . (false === strpos($url, '?') ? '?' : '&') . $name . '=testnonce'; }
function check_admin_referer($action = -1, $query_arg = '_wpnonce') { return true; }
function wp_verify_nonce($nonce, $action = -1) { return 'testnonce' === $nonce; }
function wp_safe_redirect($url, $status = 302) {
    $GLOBALS['pov_test']['redirect'] = $url;
    throw new RuntimeException('redirect:' . $url);
}
function wp_die($message = '') { throw new RuntimeException('wp_die:' . $message); }

/* Options / meta / transients ------------------------------------------ */

function get_option($name, $default = false) {
    return array_key_exists($name, $GLOBALS['pov_test']['options']) ? $GLOBALS['pov_test']['options'][$name] : $default;
}
function update_option($name, $value, $autoload = null) { $GLOBALS['pov_test']['options'][$name] = $value; return true; }
function delete_option($name) { unset($GLOBALS['pov_test']['options'][$name]); }
function get_site_transient($name) { return $GLOBALS['pov_test']['transients'][$name] ?? false; }
function set_site_transient($name, $value, $expiration = 0) { $GLOBALS['pov_test']['transients'][$name] = $value; return true; }
function delete_site_transient($name) { unset($GLOBALS['pov_test']['transients'][$name]); }
function get_transient($name) { return $GLOBALS['pov_test']['transients']['t_' . $name] ?? false; }
function set_transient($name, $value, $expiration = 0) { $GLOBALS['pov_test']['transients']['t_' . $name] = $value; return true; }
function get_post_meta($id, $key, $single = false) {
    $value = $GLOBALS['pov_test']['meta'][$id][$key] ?? '';
    return $single ? $value : ('' === $value ? array() : array($value));
}
function update_post_meta($id, $key, $value) { $GLOBALS['pov_test']['meta'][$id][$key] = $value; return true; }
function delete_post_meta($id, $key) { unset($GLOBALS['pov_test']['meta'][$id][$key]); }

/* Posts ---------------------------------------------------------------- */

function wp_insert_post($args, $wp_error = false) {
    $id = ++$GLOBALS['pov_test']['next_id'];
    $GLOBALS['pov_test']['posts'][$id] = array_merge(array('post_type' => 'post', 'post_status' => 'publish', 'post_title' => '', 'post_content' => ''), (array) $args);
    if (!empty($args['meta_input'])) {
        foreach ($args['meta_input'] as $key => $value) {
            update_post_meta($id, $key, $value);
        }
    }
    return $id;
}
function wp_update_post($args) { return $args['ID'] ?? 0; }
function get_post($id = 0) {
    if (!$id || !isset($GLOBALS['pov_test']['posts'][$id])) {
        return null;
    }
    $data = $GLOBALS['pov_test']['posts'][$id];
    $post = new WP_Post($id, $data['post_type'], $data['post_content']);
    $post->post_status = $data['post_status'];
    return $post;
}
function get_post_status($id) {
    $post = get_post($id);
    return $post ? $post->post_status : false;
}
function get_the_title($id) {
    return $GLOBALS['pov_test']['posts'][$id]['post_title'] ?? '';
}
function get_the_ID() { return 0; }
function get_the_date($format = '', $post = null) { return '12.11.2026 10:00'; }
function get_page_by_path($path, $output = 'OBJECT', $type = 'page') { return null; }
function wp_delete_post($id, $force = false) { unset($GLOBALS['pov_test']['posts'][$id]); return true; }
function register_post_type($type, $args = array()) { return true; }
function current_user_can($cap, ...$args) { return true; }
function is_singular($types = '') { return true; }
function is_page($page = '') { return get_option('pov_quote_page_id') == $page; }
function add_submenu_page(...$args) { return true; }
function paginate_links($args = array()) { return ''; }
function wp_reset_postdata() {}
function wp_date($format, $timestamp = null) { return date($format, $timestamp ?: time()); }
function wp_enqueue_style(...$args) {}
function wp_enqueue_script(...$args) {}
function wp_localize_script(...$args) {}
function wp_remote_get($url, $args = array()) {
    if (is_callable($GLOBALS['pov_test']['remote'])) {
        return call_user_func($GLOBALS['pov_test']['remote'], $url, $args);
    }
    return new WP_Error('http', 'No remote stub');
}
function wp_remote_retrieve_response_code($response) { return is_array($response) ? ($response['response']['code'] ?? 0) : 0; }
function wp_remote_retrieve_body($response) { return is_array($response) ? ($response['body'] ?? '') : ''; }
function wp_mail($to, $subject, $message, $headers = array()) {
    $GLOBALS['pov_test']['mails'][] = compact('to', 'subject', 'message', 'headers');
    return true;
}
function wp_update_plugins() {}
function plugin_dir_path($file) { return rtrim(dirname($file), '/') . '/'; }
function plugin_basename($file) { return 'print-options/' . basename($file); }
function trailingslashit($value) { return rtrim((string) $value, '/\\') . '/'; }
function register_activation_hook($file, $callback) {}
function checked($checked, $current = true, $display = true) {
    $result = ((string) $checked === (string) $current) ? " checked='checked'" : '';
    if ($display) { echo $result; }
    return $result;
}
function selected($selected, $current = true, $display = true) {
    $result = ((string) $selected === (string) $current) ? " selected='selected'" : '';
    if ($display) { echo $result; }
    return $result;
}
function submit_button($text = 'Save Changes', $type = 'primary', $name = 'submit', $wrap = true, $other = '') { return ''; }
function wp_enqueue_media($args = array()) {}
function settings_fields($group) {}
function wp_parse_args($args, $defaults = array()) { return array_merge((array) $defaults, (array) $args); }
function untrailingslashit($value) { return rtrim((string) $value, '/\\'); }
function wp_handle_upload($file, $overrides = array()) {
    $uploads = $GLOBALS['pov_test']['uploads'];
    if (isset($uploads[$file['name']])) {
        return $uploads[$file['name']];
    }
    return array('error' => 'Upload stub missing for ' . $file['name']);
}

/* WooCommerce ---------------------------------------------------------- */

class WC_Product {
    protected $id = 0;
    protected $name = '';
    protected $price = '12.5';
    protected $purchasable = true;
    public function __construct($id = 0, $name = '', $price = '12.5', $purchasable = true) {
        $this->id = (int) $id;
        $this->name = $name;
        $this->price = $price;
        $this->purchasable = (bool) $purchasable;
    }
    public function get_id() { return $this->id; }
    public function get_name() { return $this->name; }
    public function get_price($context = 'view') { return $this->price; }
    public function is_purchasable() { return $this->purchasable; }
    public function is_type($type) { return 'simple' === $type; }
    public function get_tax_class() { return ''; }
    public function is_taxable() { return false; }
}

function wc_get_product($id) {
    if (123 === (int) $id) {
        return new WC_Product(123, 'Meniu restaurant A4');
    }
    if (124 === (int) $id) {
        return new WC_Product(124, 'Proiect complex fara pret', '', false);
    }
    return null;
}
function wc_prices_include_tax() { return false; }
function wc_get_price_decimals() { return 2; }
function get_woocommerce_currency() { return 'RON'; }
function get_locale() { return 'ro_RO'; }
function get_bloginfo($key = 'name') { return 'MBC Print'; }
function wc_tax_enabled() { return false; }
function wc_add_notice(...$args) {}
function wc_price($price) { return $price . ' lei'; }
