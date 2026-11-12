<?php
/**
 * Cerere ofertă — quote request page, submissions, storage and admin list.
 *
 * @package print-options
 */

if (!defined('ABSPATH')) {
    exit;
}

final class POV_Quote {

    const CPT           = 'pov_quote';
    const PAGE_OPTION   = 'pov_quote_page_id';
    const EMAIL_OPTION  = 'pov_quote_email';
    const STATUS_META   = '_pov_quote_status';
    const SHORTCODE     = 'pov_quote_form';
    const MAX_ITEMS     = 10;
    const MAX_FILES     = 5;
    const MAX_FILE_SIZE = 20971520; // 20 MB.

    /** @var string[] */
    private static $errors = array();

    /** @var array */
    private static $old = array();

    /** @var bool */
    private static $uploading = false;

    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_type'));
        add_shortcode(self::SHORTCODE, array(__CLASS__, 'shortcode'));
        add_action('template_redirect', array(__CLASS__, 'maybe_handle_submission'));
        add_action('admin_init', array(__CLASS__, 'ensure_page'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 20);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'), 20);
        add_action('admin_post_pov_quote_delete', array(__CLASS__, 'admin_delete'));
        add_action('admin_post_pov_quote_status', array(__CLASS__, 'admin_toggle_status'));
        add_action('admin_post_pov_quote_settings', array(__CLASS__, 'admin_save_settings'));
        add_filter('upload_mimes', array(__CLASS__, 'allow_mimes'));
        add_filter('body_class', array(__CLASS__, 'body_class'));
        add_filter('wp_check_filetype_and_ext', array(__CLASS__, 'allow_postscript'), 10, 4);
    }

    /* ---------------------------------------------------------------------
     * Page plumbing
     * ------------------------------------------------------------------ */

    public static function register_post_type() {
        register_post_type(
            self::CPT,
            array(
                'label'           => 'Cereri ofertă',
                'public'          => false,
                'show_ui'         => false,
                'show_in_rest'    => false,
                'supports'        => array('title'),
                'capability_type' => 'post',
                'map_meta_cap'    => true,
            )
        );
    }

    public static function page_url() {
        $id = (int) get_option(self::PAGE_OPTION);
        if ($id && 'publish' === get_post_status($id)) {
            $permalink = get_permalink($id);
            if ($permalink) {
                return $permalink;
            }
        }
        return home_url('/cere-oferta/');
    }

    public static function button_url($product_id) {
        return add_query_arg('pov_product', (int) $product_id, self::page_url());
    }

    /**
     * Creates the "Cere ofertă" page on activation and repairs the reference
     * when the page is deleted later.
     */
    public static function ensure_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        $id = (int) get_option(self::PAGE_OPTION);
        if ($id && 'publish' === get_post_status($id)) {
            return;
        }
        self::create_page();
    }

    public static function create_page() {
        $id = (int) get_option(self::PAGE_OPTION);
        if ($id && 'publish' === get_post_status($id)) {
            return $id;
        }

        $existing = get_page_by_path('cere-oferta');
        if ($existing instanceof WP_Post) {
            update_option(self::PAGE_OPTION, (int) $existing->ID);
            return (int) $existing->ID;
        }

        $page_id = wp_insert_post(
            array(
                'post_title'   => 'Cere ofertă',
                'post_name'    => 'cere-oferta',
                'post_content' => '[' . self::SHORTCODE . ']',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ),
            true
        );

        if (is_wp_error($page_id) || !$page_id) {
            return 0;
        }

        update_option(self::PAGE_OPTION, (int) $page_id);
        if (!get_option(self::EMAIL_OPTION)) {
            update_option(self::EMAIL_OPTION, get_option('admin_email'));
        }
        return (int) $page_id;
    }

    public static function enqueue() {
        if (!self::is_quote_page()) {
            return;
        }
        wp_enqueue_style('pov-quote', plugins_url('assets/quote.css', dirname(__DIR__) . '/print-options.php'), array(), POV_VERSION);
        wp_enqueue_script('pov-quote', plugins_url('assets/quote.js', dirname(__DIR__) . '/print-options.php'), array(), POV_VERSION, true);
        wp_localize_script(
            'pov-quote',
            'povQuote',
            array(
                'max'   => self::MAX_ITEMS,
                'label' => 'Adaugă încă un produs sau serviciu',
                'error' => 'Poți adăuga maximum ' . self::MAX_ITEMS . ' produse într-o cerere.',
            )
        );
    }

    /**
     * The shortcode renders its own H1, so the theme page title is hidden on
     * this page only (see assets/quote.css).
     */
    public static function body_class($classes) {
        if (self::is_quote_page()) {
            $classes[] = 'pov-quote-page';
        }
        return $classes;
    }

    public static function is_quote_page() {
        if (!is_singular()) {
            return false;
        }
        $id = (int) get_option(self::PAGE_OPTION);
        if ($id && is_page($id)) {
            return true;
        }
        $post = get_post();
        return $post instanceof WP_Post && has_shortcode($post->post_content, self::SHORTCODE);
    }

    /* ---------------------------------------------------------------------
     * Rendering
     * ------------------------------------------------------------------ */

    public static function shortcode($atts = array()) {
        if (self::$errors) {
            wp_enqueue_style('pov-quote', plugins_url('assets/quote.css', dirname(__DIR__) . '/print-options.php'), array(), POV_VERSION);
        }

        $sent  = isset($_GET['pov_sent']) ? absint($_GET['pov_sent']) : 0;
        $items = self::old_items();
        $html  = '<div class="pov-quote">';
        $html .= self::head_html(count($items));
        $html .= '<form class="pov-quote-form" method="post" enctype="multipart/form-data" action="' . esc_url(self::page_url()) . '">';

        if ($sent) {
            $html .= self::success_html($sent);
        }
        if (self::$errors) {
            $html .= '<div class="pov-quote-alert" role="alert"><strong>Nu am putut trimite cererea:</strong><ul>';
            foreach (self::$errors as $error) {
                $html .= '<li>' . esc_html($error) . '</li>';
            }
            $html .= '</ul></div>';
        }

        $html .= wp_nonce_field('pov_quote_submit', 'pov_quote_nonce', true, false);
        $html .= '<input type="hidden" name="pov_quote_action" value="1">';
        $html .= '<input type="hidden" name="pov_quote_ts" value="' . esc_attr((string) time()) . '">';
        $html .= '<div class="pov-quote-hp" aria-hidden="true"><label>Nu completa acest câmp <input type="text" name="pov_quote_hp" value="" tabindex="-1" autocomplete="off"></label></div>';

        $html .= '<div class="pov-quote-layout">';
        $html .= '<div class="pov-quote-main">';
        $html .= '<div class="pov-quote-items" data-pov-items>';
        foreach ($items as $key => $item) {
            $html .= self::item_html($key, $item);
        }
        $html .= '</div>';
        $html .= '<button type="button" class="pov-quote-add" data-pov-add><span aria-hidden="true">+</span> Adaugă încă un produs sau serviciu</button>';
        $html .= '</div>';
        $html .= self::sidebar_html();
        $html .= '</div>';
        $html .= self::template_html();
        $html .= '</form></div>';

        return $html;
    }

    private static function head_html($count = 1) {
        $out  = '<header class="pov-quote-head">';
        $out .= '<h1 class="pov-quote-title"><span>Cerere ofertă</span><span class="pov-quote-count" data-pov-count>' . max(1, (int) $count) . '</span></h1>';
        $out .= '<p class="pov-quote-intro">' . self::icon('info');
        $out .= '<span>Dacă nu ai găsit exact produsul, serviciul sau cantitatea dorită, trimite-ne o cerere de ofertă personalizată în care să ne oferi cât mai multe detalii despre comanda pe care dorești să o realizăm.</span></p>';
        $out .= '</header>';
        return $out;
    }

    private static function success_html($id) {
        $out  = '<div class="pov-quote-success" role="status">';
        $out .= '<span class="pov-quote-success-icon" aria-hidden="true">✓</span>';
        $out .= '<div><strong>Cererea ta a fost trimisă.</strong><p>Îți mulțumim! Revenim cu o ofertă personalizată în cel mai scurt timp la adresa de email indicată.</p>';
        $out .= '<p class="pov-quote-ref">Referință cerere: #' . (int) $id . '</p></div>';
        $out .= '</div>';
        return $out;
    }

    private static function old_items() {
        if (self::$old && isset(self::$old['items']) && is_array(self::$old['items'])) {
            return array_slice(self::$old['items'], 0, self::MAX_ITEMS, true);
        }

        $items      = array();
        $product_id = isset($_GET['pov_product']) ? absint($_GET['pov_product']) : 0;
        if ($product_id) {
            $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
            $name    = $product ? $product->get_name() : get_the_title($product_id);
            if ($name) {
                $item               = self::empty_item();
                $item['name']       = $name;
                $item['product_id'] = $product_id;
                $items[]            = $item;
            }
        }

        return $items ? $items : array(self::empty_item());
    }

    private static function empty_item() {
        return array('name' => '', 'desc' => '', 'qty' => 1, 'product_id' => 0, 'files' => array());
    }

    private static function item_html($key, $item) {
        $key        = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $key);
        if ('' === $key) {
            $key = '0';
        }
        $uid        = 'pov-quote-' . $key;
        $name_id    = $uid . '-name';
        $desc_id    = $uid . '-desc';
        $qty_id     = $uid . '-qty';
        $file_id    = $uid . '-files';
        $product_id = isset($item['product_id']) ? (int) $item['product_id'] : 0;
        $qty        = isset($item['qty']) ? max(1, (int) $item['qty']) : 1;
        $desc       = isset($item['desc']) ? (string) $item['desc'] : '';
        $files      = isset($item['files']) && is_array($item['files']) ? $item['files'] : array();

        $out  = '<section class="pov-quote-card pov-quote-item" data-pov-item data-key="' . esc_attr($key) . '">';
        $out .= '<div class="pov-quote-item-head"><h2>Produs sau serviciu</h2>';
        $out .= '<button type="button" class="pov-quote-remove" data-pov-remove aria-label="Șterge produsul">' . self::icon('trash') . '</button></div>';

        $out .= '<div class="pov-quote-row">';
        $out .= '<div class="pov-quote-col">';
        $out .= '<div class="pov-quote-field">';
        $out .= '<label for="' . esc_attr($name_id) . '">Produs sau serviciu solicitat</label>';
        $out .= '<input type="text" id="' . esc_attr($name_id) . '" name="pov_quote[items][' . esc_attr($key) . '][name]" value="' . esc_attr(isset($item['name']) ? $item['name'] : '') . '" placeholder="Ex.: Meniuri" autocomplete="off">';
        $out .= '</div>';

        $out .= '<div class="pov-quote-qty">';
        $out .= '<label for="' . esc_attr($qty_id) . '">Cantitate</label>';
        $out .= '<div class="pov-quote-qty-controls">';
        $out .= '<input type="number" id="' . esc_attr($qty_id) . '" name="pov_quote[items][' . esc_attr($key) . '][qty]" value="' . esc_attr((string) $qty) . '" min="1" step="1" inputmode="numeric">';
        $out .= '<button type="button" class="pov-quote-plus" data-pov-plus aria-label="Crește cantitatea">+</button>';
        $out .= '</div></div>';
        $out .= '</div>';

        $out .= '<div class="pov-quote-col pov-quote-col-desc">';
        $out .= '<div class="pov-quote-field">';
        $out .= '<label for="' . esc_attr($desc_id) . '">Descriere</label>';
        $out .= '<textarea id="' . esc_attr($desc_id) . '" name="pov_quote[items][' . esc_attr($key) . '][desc]" rows="5" placeholder="Spune-ne mai în detaliu ce ai nevoie">' . esc_textarea($desc) . '</textarea>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="pov-quote-upload">';
        $out .= '<p class="pov-quote-upload-title">Ai imagini sau design pentru produsul sau serviciul solicitat?</p>';
        $out .= '<div class="pov-quote-upload-actions">';
        $out .= '<input type="file" id="' . esc_attr($file_id) . '" class="pov-quote-file-input" name="pov_files[' . esc_attr($key) . '][]" multiple accept="' . esc_attr(self::accept_attr()) . '" data-pov-file>';
        $out .= '<label for="' . esc_attr($file_id) . '" class="pov-quote-file-btn">' . self::icon('upload') . '<span>Încarcă fișiere</span></label>';
        $out .= '</div>';
        $out .= '<p class="pov-quote-filelist" data-pov-files>';
        foreach ($files as $file) {
            $out .= '<span>' . esc_html($file) . '</span>';
        }
        $out .= '</p>';
        $out .= '<p class="pov-quote-upload-hint">Imagine, PDF, EPS, AI, PSD sau ZIP — maximum ' . self::MAX_FILES . ' fișiere, ' . size_format(self::MAX_FILE_SIZE) . ' fiecare.</p>';
        $out .= '</div>';

        $out .= '<input type="hidden" name="pov_quote[items][' . esc_attr($key) . '][product_id]" value="' . esc_attr((string) $product_id) . '">';
        $out .= '</section>';

        return $out;
    }

    private static function sidebar_html() {
        $old   = self::$old;
        $name  = isset($old['name']) ? (string) $old['name'] : '';
        $email = isset($old['email']) ? (string) $old['email'] : '';
        $phone = isset($old['phone']) ? (string) $old['phone'] : '';

        $out  = '<aside class="pov-quote-side">';
        $out .= '<div class="pov-quote-card pov-quote-contact">';
        $out .= '<h2>Detaliile tale de contact</h2>';
        $out .= '<div class="pov-quote-field"><label for="pov-quote-name">Nume</label>';
        $out .= '<input type="text" id="pov-quote-name" name="pov_quote[name]" value="' . esc_attr($name) . '" placeholder="Numele tău" autocomplete="name" required aria-required="true"></div>';
        $out .= '<div class="pov-quote-field"><label for="pov-quote-email">E-mail</label>';
        $out .= '<input type="email" id="pov-quote-email" name="pov_quote[email]" value="' . esc_attr($email) . '" placeholder="Adresa ta e-mail" autocomplete="email" required aria-required="true"></div>';
        $out .= '<div class="pov-quote-field"><label for="pov-quote-phone">Telefon</label>';
        $out .= '<input type="tel" id="pov-quote-phone" name="pov_quote[phone]" value="' . esc_attr($phone) . '" placeholder="Numărul tău de telefon" autocomplete="tel"></div>';
        $out .= '<button type="submit" class="pov-quote-submit"><span>Trimite cererea</span>' . self::icon('arrow') . '</button>';
        $out .= '<p class="pov-quote-privacy">Folosim datele tale doar pentru a pregăti oferta cerută. Vezi <a href="' . esc_url(get_privacy_policy_url() ?: home_url('/')) . '">politica de confidențialitate</a>.</p>';
        $out .= '</div>';
        $out .= '</aside>';

        return $out;
    }

    private static function template_html() {
        $item = array('name' => '', 'desc' => '', 'qty' => 1, 'product_id' => 0, 'files' => array());
        return '<template data-pov-template>' . self::item_html('__KEY__', $item) . '</template>';
    }

    /* ---------------------------------------------------------------------
     * Request handling
     * ------------------------------------------------------------------ */

    public static function maybe_handle_submission() {
        if (empty($_POST['pov_quote_action']) || !self::is_quote_page()) {
            return;
        }
        if (!isset($_POST['pov_quote_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pov_quote_nonce'])), 'pov_quote_submit')) {
            self::$errors[] = 'Sesiunea formularului a expirat. Reîncarcă pagina și încearcă din nou.';
            self::$old      = self::posted_values();
            return;
        }

        $posted = self::posted_values();
        self::$old = $posted;

        if (self::is_bot($posted)) {
            wp_safe_redirect(add_query_arg('pov_sent', '1', self::page_url()));
            exit;
        }

        if (!self::rate_limit_ok()) {
            self::$errors[] = 'Ai trimis deja mai multe cereri în ultima oră. Te rugăm să încerci din nou mai târziu sau să ne contactezi telefonic.';
            return;
        }

        $clean = self::validate($posted);
        if (self::$errors) {
            return;
        }

        $quote_id = self::store($clean);
        if (!$quote_id) {
            self::$errors[] = 'A apărut o eroare la salvarea cererii. Te rugăm să încerci din nou.';
            return;
        }

        self::notify($quote_id, $clean);
        self::count_rate_limit();
        wp_safe_redirect(add_query_arg('pov_sent', $quote_id, self::page_url()));
        exit;
    }

    private static function posted_values() {
        $raw = isset($_POST['pov_quote']) && is_array($_POST['pov_quote']) ? wp_unslash($_POST['pov_quote']) : array();
        $out = array(
            'name'  => isset($raw['name']) ? sanitize_text_field($raw['name']) : '',
            'email' => isset($raw['email']) ? sanitize_email($raw['email']) : '',
            'phone' => isset($raw['phone']) ? sanitize_text_field($raw['phone']) : '',
            'items' => array(),
            'ts'    => isset($_POST['pov_quote_ts']) ? absint($_POST['pov_quote_ts']) : 0,
            'hp'    => isset($_POST['pov_quote_hp']) ? (string) wp_unslash($_POST['pov_quote_hp']) : '',
        );

        $items = isset($raw['items']) && is_array($raw['items']) ? array_slice($raw['items'], 0, self::MAX_ITEMS, true) : array();
        foreach ($items as $key => $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $key);
            if ('' === $key) {
                continue;
            }
            $out['items'][$key] = array(
                'name'       => isset($item['name']) ? sanitize_text_field($item['name']) : '',
                'desc'       => isset($item['desc']) ? sanitize_textarea_field($item['desc']) : '',
                'qty'        => isset($item['qty']) ? max(1, min(1000000, absint($item['qty']))) : 1,
                'product_id' => isset($item['product_id']) ? absint($item['product_id']) : 0,
                'files'      => array(),
            );
        }

        if (!$out['items']) {
            $out['items'] = array('0' => self::empty_item());
        }

        return $out;
    }

    private static function is_bot($posted) {
        if (!empty($posted['hp'])) {
            return true;
        }
        if ($posted['ts'] && (time() - (int) $posted['ts']) < 1) {
            return true;
        }
        return false;
    }

    private static function rate_limit_ok() {
        $key   = 'pov_quote_rl_' . md5(self::client_ip());
        $count = (int) get_transient($key);
        return $count < 6;
    }

    private static function count_rate_limit() {
        $key   = 'pov_quote_rl_' . md5(self::client_ip());
        $count = (int) get_transient($key);
        set_transient($key, $count + 1, HOUR_IN_SECONDS);
    }

    private static function client_ip() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        return $ip ? $ip : '0.0.0.0';
    }

    private static function validate($posted) {
        $clean = $posted;

        if ('' === $posted['name']) {
            self::$errors[] = 'Completează numele tău.';
        } elseif (self::text_length($posted['name']) > 120) {
            self::$errors[] = 'Numele poate avea maximum 120 de caractere.';
        }

        if ('' === $posted['email'] || !is_email($posted['email'])) {
            self::$errors[] = 'Introdu o adresă de email validă.';
        }

        if ('' !== $posted['phone'] && !preg_match('/^[0-9+().\s\-]{6,30}$/', $posted['phone'])) {
            self::$errors[] = 'Numărul de telefon nu pare valid.';
        }

        $items = array();
        $index = 0;
        foreach ($posted['items'] as $key => $item) {
            $has_files = !empty($_FILES['pov_files']['name'][$key]);
            if ('' === $item['name'] && '' === $item['desc'] && !$has_files) {
                continue;
            }
            if ('' === $item['name']) {
                self::$errors[] = 'Completează produsul sau serviciul solicitat pentru fiecare rând.';
                continue;
            }
            if (self::text_length($item['name']) > 200) {
                self::$errors[] = 'Denumirea produsului poate avea maximum 200 de caractere.';
                continue;
            }
            if (self::text_length($item['desc']) > 5000) {
                self::$errors[] = 'Descrierea poate avea maximum 5000 de caractere.';
                continue;
            }

            $upload_errors = array();
            $files         = self::upload_files($key, $upload_errors);
            if ($upload_errors) {
                self::$errors = array_merge(self::$errors, $upload_errors);
                continue;
            }

            $item['files']    = $files;
            $items[++$index]  = $item;
        }

        if (!$items) {
            self::$errors[] = 'Adaugă cel puțin un produs sau serviciu pentru care ceri ofertă.';
        }

        $clean['items'] = $items;
        return $clean;
    }

    private static function store($data) {
        $title = sprintf('%s — %s', wp_date('d.m.Y H:i'), $data['name']);
        $id    = wp_insert_post(
            array(
                'post_type'   => self::CPT,
                'post_status' => 'publish',
                'post_title'  => $title,
                'meta_input'  => array(
                    '_pov_quote_name'   => $data['name'],
                    '_pov_quote_email'  => $data['email'],
                    '_pov_quote_phone'  => $data['phone'],
                    '_pov_quote_items'  => $data['items'],
                    '_pov_quote_source' => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '',
                    '_pov_quote_ip'     => self::client_ip(),
                    '_pov_quote_ua'     => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
                ),
            ),
            true
        );

        if (is_wp_error($id) || !$id) {
            return 0;
        }
        update_post_meta($id, self::STATUS_META, 'new');
        return (int) $id;
    }

    /* ---------------------------------------------------------------------
     * File uploads
     * ------------------------------------------------------------------ */

    public static function accepted_extensions() {
        return array('jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'eps', 'ai', 'psd', 'zip');
    }

    private static function accept_attr() {
        return '.' . implode(',.', self::accepted_extensions());
    }

    private static function mime_map() {
        return array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'webp'         => 'image/webp',
            'gif'          => 'image/gif',
            'pdf'          => 'application/pdf',
            'eps'          => 'application/postscript',
            'ai'           => 'application/postscript',
            'psd'          => 'image/vnd.adobe.photoshop',
            'zip'          => 'application/zip',
        );
    }

    public static function allow_mimes($mimes) {
        $mimes['eps'] = 'application/postscript';
        $mimes['ai']  = 'application/postscript';
        $mimes['psd'] = 'image/vnd.adobe.photoshop';
        $mimes['zip'] = 'application/zip';
        return $mimes;
    }

    /**
     * EPS/AI/PSD are often reported as application/octet-stream by finfo.
     * Accept them only when our own signature check already passed.
     */
    public static function allow_postscript($data, $file, $filename, $mimes) {
        if (!self::$uploading || !is_array($data) || !empty($data['ext'])) {
            return $data;
        }
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $map = array('eps' => 'application/postscript', 'ai' => 'application/postscript', 'psd' => 'image/vnd.adobe.photoshop');
        if (isset($map[$ext]) && self::signature_ok($file, $ext)) {
            $data['ext']  = $ext;
            $data['type'] = $map[$ext];
        }
        return $data;
    }

    private static function signature_ok($file, $ext) {
        if (!is_string($file) || !is_readable($file)) {
            return false;
        }
        $handle = fopen($file, 'rb');
        if (!$handle) {
            return false;
        }
        $sig = fread($handle, 8);
        fclose($handle);

        if ('psd' === $ext) {
            return 0 === strncmp($sig, '8BPS', 4);
        }
        return 0 === strncmp($sig, '%!PS', 4);
    }

    private static function upload_files($key, &$errors) {
        $files = array();
        if (empty($_FILES['pov_files']['name'][$key])) {
            return $files;
        }

        $names = (array) $_FILES['pov_files']['name'][$key];
        $tmps  = (array) $_FILES['pov_files']['tmp_name'][$key];
        $errs  = (array) $_FILES['pov_files']['error'][$key];
        $sizes = (array) $_FILES['pov_files']['size'][$key];

        if (count($names) > self::MAX_FILES) {
            $errors[] = 'Poți încărca maximum ' . self::MAX_FILES . ' fișiere pentru fiecare produs.';
            return $files;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        foreach ($names as $index => $name) {
            if ('' === $name || UPLOAD_ERR_NO_FILE === (int) $errs[$index]) {
                continue;
            }
            if (UPLOAD_ERR_OK !== (int) $errs[$index]) {
                $errors[] = 'Fișierul „' . sanitize_file_name($name) . '” nu a putut fi încărcat.';
                continue;
            }
            if ((int) $sizes[$index] > self::MAX_FILE_SIZE) {
                $errors[] = 'Fișierul „' . sanitize_file_name($name) . '” depășește limita de ' . size_format(self::MAX_FILE_SIZE) . '.';
                continue;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, self::accepted_extensions(), true)) {
                $errors[] = 'Fișierul „' . sanitize_file_name($name) . '” are un format neacceptat.';
                continue;
            }
            if (in_array($ext, array('eps', 'ai', 'psd'), true) && !self::signature_ok($tmps[$index], $ext)) {
                $errors[] = 'Fișierul „' . sanitize_file_name($name) . '” nu pare a fi un ' . strtoupper($ext) . ' valid.';
                continue;
            }

            self::$uploading = true;
            $upload = wp_handle_upload(
                array(
                    'name'     => $name,
                    'type'     => isset($_FILES['pov_files']['type'][$key][$index]) ? sanitize_mime_type($_FILES['pov_files']['type'][$key][$index]) : '',
                    'tmp_name' => $tmps[$index],
                    'error'    => $errs[$index],
                    'size'     => $sizes[$index],
                ),
                array('test_form' => false, 'mimes' => self::mime_map())
            );
            self::$uploading = false;

            if (!empty($upload['error'])) {
                $errors[] = 'Fișierul „' . sanitize_file_name($name) . '” nu a putut fi salvat.';
                continue;
            }
            $files[] = array(
                'name' => sanitize_file_name($name),
                'url'  => esc_url_raw($upload['url']),
                'type' => sanitize_mime_type($upload['type']),
                'size' => (int) $sizes[$index],
            );
        }

        return $files;
    }

    private static function text_length($value) {
        $value = (string) $value;
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    /* ---------------------------------------------------------------------
     * Notifications
     * ------------------------------------------------------------------ */

    private static function recipients() {
        $raw  = (string) get_option(self::EMAIL_OPTION, '');
        $list = array_filter(array_map('sanitize_email', preg_split('/[,;\s]+/', $raw)));
        if (!$list) {
            $list = array(sanitize_email(get_option('admin_email')));
        }
        return array_values(array_unique(array_filter($list)));
    }

    private static function notify($id, $data) {
        $to      = apply_filters('pov_quote_recipients', self::recipients(), $id, $data);
        $subject = sprintf('Cerere ofertă nouă #%d — %s', $id, $data['name']);
        $body    = self::email_html($id, $data);

        $from_name = wp_specialchars_decode(get_option('woocommerce_email_from_name', get_bloginfo('name')), ENT_QUOTES);
        $from_mail = sanitize_email(get_option('woocommerce_email_from_address', get_option('admin_email')));
        $from_name = trim(preg_replace('/[\r\n]+/', ' ', $from_name));

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_mail . '>',
            'Reply-To: ' . $data['name'] . ' <' . $data['email'] . '>',
        );

        $sent = wp_mail($to, $subject, $body, $headers);
        update_post_meta($id, '_pov_quote_email_sent', $sent ? 1 : 0);
        do_action('pov_quote_notification', $id, $data, $sent);
    }

    private static function email_html($id, $data) {
        $edit = admin_url('admin.php?page=pov-quotes&quote=' . (int) $id);
        $out  = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#1d2939;line-height:1.55">';
        $out .= '<h2 style="margin:0 0 12px;font-size:20px">Cerere ofertă #' . (int) $id . '</h2>';
        $out .= '<p style="margin:0 0 16px">';
        $out .= '<strong>Nume:</strong> ' . esc_html($data['name']) . '<br>';
        $out .= '<strong>E-mail:</strong> ' . esc_html($data['email']) . '<br>';
        if (!empty($data['phone'])) {
            $out .= '<strong>Telefon:</strong> ' . esc_html($data['phone']) . '<br>';
        }
        $out .= '<strong>Trimis:</strong> ' . esc_html(wp_date('d.m.Y H:i')) . '</p>';

        foreach ($data['items'] as $index => $item) {
            $out .= '<div style="border:1px solid #e4e7ec;border-radius:10px;padding:14px 16px;margin:0 0 12px">';
            $out .= '<p style="margin:0 0 6px"><strong>' . (int) $index . '. ' . esc_html($item['name']) . '</strong>';
            $out .= ' — ' . (int) $item['qty'] . ' buc.</p>';
            if (!empty($item['product_id'])) {
                $link = get_permalink((int) $item['product_id']);
                if ($link) {
                    $out .= '<p style="margin:0 0 6px"><a href="' . esc_url($link) . '">Pagina produsului</a></p>';
                }
            }
            if (!empty($item['desc'])) {
                $out .= '<p style="margin:0 0 6px;white-space:pre-line">' . esc_html($item['desc']) . '</p>';
            }
            if (!empty($item['files'])) {
                $out .= '<p style="margin:0">Fișiere:<br>';
                foreach ($item['files'] as $file) {
                    $out .= '<a href="' . esc_url($file['url']) . '">' . esc_html($file['name']) . '</a><br>';
                }
                $out .= '</p>';
            }
            $out .= '</div>';
        }

        $out .= '<p style="margin:16px 0 0"><a href="' . esc_url($edit) . '" style="background:#f47735;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;display:inline-block">Deschide cererea în dashboard</a></p>';
        $out .= '</div>';
        return $out;
    }

    /* ---------------------------------------------------------------------
     * Admin
     * ------------------------------------------------------------------ */

    public static function admin_menu() {
        $count = self::new_count();
        $label = 'Cereri ofertă';
        if ($count) {
            $label .= ' <span class="awaiting-mod"><span class="pending-count">' . (int) $count . '</span></span>';
        }
        add_submenu_page('woocommerce', 'Cereri ofertă', $label, 'manage_woocommerce', 'pov-quotes', array(__CLASS__, 'admin_page'));
    }

    private static function new_count() {
        $query = new WP_Query(
            array(
                'post_type'      => self::CPT,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_key'       => self::STATUS_META,
                'meta_value'     => 'new',
                'no_found_rows'  => false,
            )
        );
        return (int) $query->found_posts;
    }

    public static function admin_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        $id = isset($_GET['quote']) ? absint($_GET['quote']) : 0;
        if ($id) {
            self::admin_detail($id);
            return;
        }
        self::admin_list();
    }

    private static function admin_notice() {
        if (empty($_GET['pov_notice'])) {
            return;
        }
        $messages = array(
            'deleted'  => 'Cererea a fost ștearsă.',
            'status'   => 'Statusul cererii a fost actualizat.',
            'settings' => 'Setările au fost salvate.',
        );
        $key = sanitize_key($_GET['pov_notice']);
        if (isset($messages[$key])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$key]) . '</p></div>';
        }
    }

    private static function admin_list() {
        $paged = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
        $query = new WP_Query(
            array(
                'post_type'      => self::CPT,
                'post_status'    => 'publish',
                'posts_per_page' => 20,
                'paged'          => $paged,
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        );
        $total = (int) $query->found_posts;
        $new   = self::new_count();

        echo '<div class="wrap pov-quotes-page">';
        echo '<h1 class="wp-heading-inline">Cereri ofertă</h1>';
        echo '<span class="pov-quotes-total">' . (int) $total . ' cereri · ' . (int) $new . ' noi</span>';
        self::admin_notice();
        self::settings_form();
        echo '<p class="pov-quotes-help">Cererile trimise din pagina <a href="' . esc_url(self::page_url()) . '" target="_blank" rel="noopener">Cere ofertă</a> apar aici și sunt trimise și pe email.</p>';

        if (!$query->have_posts()) {
            echo '<div class="pov-quotes-empty"><p>Nu există cereri de ofertă încă.</p></div></div>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped pov-quotes-table"><thead><tr>';
        echo '<th class="pov-col-id">#</th><th class="pov-col-date">Data</th><th>Nume</th><th>Contact</th><th>Produse</th><th class="pov-col-files">Fișiere</th><th class="pov-col-status">Status</th><th class="pov-col-actions">Acțiuni</th>';
        echo '</tr></thead><tbody>';

        while ($query->have_posts()) {
            $query->the_post();
            $qid     = get_the_ID();
            $name    = (string) get_post_meta($qid, '_pov_quote_name', true);
            $email   = (string) get_post_meta($qid, '_pov_quote_email', true);
            $phone   = (string) get_post_meta($qid, '_pov_quote_phone', true);
            $items   = get_post_meta($qid, '_pov_quote_items', true);
            $items   = is_array($items) ? $items : array();
            $status  = (string) get_post_meta($qid, self::STATUS_META, true);
            $files   = 0;
            $titles  = array();
            foreach ($items as $item) {
                $titles[] = isset($item['name']) ? $item['name'] : '';
                if (!empty($item['files'])) {
                    $files += count($item['files']);
                }
            }
            $summary = $titles ? implode(', ', array_slice($titles, 0, 3)) : '—';
            if (count($titles) > 3) {
                $summary .= ' +' . (count($titles) - 3);
            }

            echo '<tr>';
            echo '<td class="pov-col-id">' . (int) $qid . '</td>';
            echo '<td class="pov-col-date">' . esc_html(get_the_date('d.m.Y H:i')) . '</td>';
            echo '<td><a class="row-title" href="' . esc_url(admin_url('admin.php?page=pov-quotes&quote=' . (int) $qid)) . '">' . esc_html($name ? $name : '(fără nume)') . '</a></td>';
            echo '<td><a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
            if ($phone) {
                echo '<br><span class="pov-muted">' . esc_html($phone) . '</span>';
            }
            echo '</td>';
            echo '<td>' . esc_html($summary) . '</td>';
            echo '<td class="pov-col-files">' . ($files ? '<span class="pov-badge-files">' . (int) $files . '</span>' : '—') . '</td>';
            echo '<td class="pov-col-status">' . ('new' === $status ? '<span class="pov-status pov-status-new">Nouă</span>' : '<span class="pov-status pov-status-done">Rezolvată</span>') . '</td>';
            echo '<td class="pov-col-actions"><a class="button button-small" href="' . esc_url(admin_url('admin.php?page=pov-quotes&quote=' . (int) $qid)) . '">Deschide</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        $pages = paginate_links(
            array(
                'base'      => add_query_arg('paged', '%#%', admin_url('admin.php?page=pov-quotes')),
                'format'    => '',
                'current'   => $paged,
                'total'     => max(1, (int) $query->max_num_pages),
                'prev_text' => '‹',
                'next_text' => '›',
                'type'      => 'plain',
            )
        );
        if ($pages) {
            echo '<div class="tablenav"><div class="tablenav-pages">' . $pages . '</div></div>';
        }
        wp_reset_postdata();
        echo '</div>';
    }

    private static function settings_form() {
        $email = (string) get_option(self::EMAIL_OPTION, get_option('admin_email'));
        echo '<form class="pov-quote-settings" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('pov_quote_settings');
        echo '<input type="hidden" name="action" value="pov_quote_settings">';
        echo '<label for="pov-quote-email-to">Email destinatar cereri</label>';
        echo '<input type="text" id="pov-quote-email-to" class="regular-text" name="pov_quote_email" value="' . esc_attr($email) . '" placeholder="oferte@exemplu.ro">';
        echo '<button class="button">Salvează</button>';
        echo '<span class="pov-quotes-help">Poți introduce mai multe adrese, separate prin virgulă.</span>';
        echo '</form>';
    }

    private static function admin_detail($id) {
        $post = get_post($id);
        if (!$post || self::CPT !== $post->post_type) {
            echo '<div class="wrap"><p>Cererea nu există.</p></div>';
            return;
        }
        $name   = (string) get_post_meta($id, '_pov_quote_name', true);
        $email  = (string) get_post_meta($id, '_pov_quote_email', true);
        $phone  = (string) get_post_meta($id, '_pov_quote_phone', true);
        $source = (string) get_post_meta($id, '_pov_quote_source', true);
        $items  = get_post_meta($id, '_pov_quote_items', true);
        $items  = is_array($items) ? $items : array();
        $status = (string) get_post_meta($id, self::STATUS_META, true);
        $sent   = (int) get_post_meta($id, '_pov_quote_email_sent', true);

        echo '<div class="wrap pov-quote-detail">';
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=pov-quotes')) . '">← Toate cererile</a></p>';
        echo '<h1>Cerere ofertă #' . (int) $id . '</h1>';
        self::admin_notice();

        echo '<div class="pov-quote-detail-grid">';
        echo '<div class="pov-quote-detail-main">';
        foreach ($items as $index => $item) {
            echo '<section class="pov-detail-card">';
            echo '<h2>' . (int) $index . '. ' . esc_html(isset($item['name']) ? $item['name'] : '') . '</h2>';
            echo '<p class="pov-detail-meta">Cantitate: <strong>' . (int) (isset($item['qty']) ? $item['qty'] : 1) . '</strong>';
            if (!empty($item['product_id'])) {
                $link = get_permalink((int) $item['product_id']);
                $text = get_the_title((int) $item['product_id']);
                if ($link) {
                    echo ' · Produs sursă: <a href="' . esc_url($link) . '" target="_blank" rel="noopener">' . esc_html($text) . '</a>';
                }
            }
            echo '</p>';
            if (!empty($item['desc'])) {
                echo '<div class="pov-detail-desc">' . nl2br(esc_html($item['desc'])) . '</div>';
            }
            if (!empty($item['files'])) {
                echo '<ul class="pov-detail-files">';
                foreach ($item['files'] as $file) {
                    echo '<li><a href="' . esc_url($file['url']) . '" target="_blank" rel="noopener">' . esc_html($file['name']) . '</a> <span class="pov-muted">' . esc_html(size_format(isset($file['size']) ? $file['size'] : 0)) . '</span></li>';
                }
                echo '</ul>';
            }
            echo '</section>';
        }
        echo '</div>';

        echo '<aside class="pov-quote-detail-side">';
        echo '<section class="pov-detail-card">';
        echo '<h2>Contact</h2>';
        echo '<p><strong>' . esc_html($name) . '</strong><br><a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
        if ($phone) {
            echo '<br><a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $phone)) . '">' . esc_html($phone) . '</a>';
        }
        echo '</p>';
        echo '<p class="pov-muted">Trimisă la ' . esc_html(get_the_date('d.m.Y H:i')) . '</p>';
        echo '<p class="pov-muted">Email notificare: ' . ($sent ? 'trimis' : 'netrimis') . '</p>';
        if ($source) {
            echo '<p class="pov-muted">Pagina sursă: <a href="' . esc_url($source) . '" target="_blank" rel="noopener">' . esc_html(wp_parse_url($source, PHP_URL_PATH)) . '</a></p>';
        }
        echo '<p>Status: ' . ('new' === $status ? '<span class="pov-status pov-status-new">Nouă</span>' : '<span class="pov-status pov-status-done">Rezolvată</span>') . '</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="pov-detail-actions">';
        wp_nonce_field('pov_quote_status');
        echo '<input type="hidden" name="action" value="pov_quote_status">';
        echo '<input type="hidden" name="quote" value="' . (int) $id . '">';
        echo '<input type="hidden" name="target" value="' . ('new' === $status ? 'done' : 'new') . '">';
        echo '<button class="button button-primary">' . ('new' === $status ? 'Marchează rezolvată' : 'Redeschide cererea') . '</button>';
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="pov-detail-actions" onsubmit="return confirm(\'Ștergi definitiv această cerere?\');">';
        wp_nonce_field('pov_quote_delete');
        echo '<input type="hidden" name="action" value="pov_quote_delete">';
        echo '<input type="hidden" name="quote" value="' . (int) $id . '">';
        echo '<button class="button button-link-delete">Șterge cererea</button>';
        echo '</form>';
        echo '</section>';
        echo '</aside>';
        echo '</div></div>';
    }

    public static function admin_delete() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Acces interzis.');
        }
        check_admin_referer('pov_quote_delete');
        $id = isset($_POST['quote']) ? absint($_POST['quote']) : 0;
        if ($id && self::CPT === get_post_type($id)) {
            wp_delete_post($id, true);
        }
        wp_safe_redirect(add_query_arg(array('page' => 'pov-quotes', 'pov_notice' => 'deleted'), admin_url('admin.php')));
        exit;
    }

    public static function admin_toggle_status() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Acces interzis.');
        }
        check_admin_referer('pov_quote_status');
        $id     = isset($_POST['quote']) ? absint($_POST['quote']) : 0;
        $target = isset($_POST['target']) && 'done' === $_POST['target'] ? 'done' : 'new';
        if ($id && self::CPT === get_post_type($id)) {
            update_post_meta($id, self::STATUS_META, $target);
        }
        wp_safe_redirect(add_query_arg(array('page' => 'pov-quotes', 'quote' => $id, 'pov_notice' => 'status'), admin_url('admin.php')));
        exit;
    }

    public static function admin_save_settings() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Acces interzis.');
        }
        check_admin_referer('pov_quote_settings');
        $raw  = isset($_POST['pov_quote_email']) ? sanitize_text_field(wp_unslash($_POST['pov_quote_email'])) : '';
        $list = array_filter(array_map('sanitize_email', preg_split('/[,;\s]+/', $raw)));
        update_option(self::EMAIL_OPTION, implode(', ', $list));
        wp_safe_redirect(add_query_arg(array('page' => 'pov-quotes', 'pov_notice' => 'settings'), admin_url('admin.php')));
        exit;
    }

    /* ---------------------------------------------------------------------
     * Icons
     * ------------------------------------------------------------------ */

    private static function icon($name) {
        $svg = array(
            'info'   => '<circle cx="12" cy="12" r="9"/><path d="M12 10.5v5.5"/><circle cx="12" cy="7.8" r=".9" fill="currentColor" stroke="none"/>',
            'trash'  => '<path d="M4 7h16"/><path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7"/><path d="M6.5 7l.8 11.2A2 2 0 0 0 9.3 20h5.4a2 2 0 0 0 2-1.8L17.5 7"/><path d="M10 11v5M14 11v5"/>',
            'upload' => '<path d="M12 16V5"/><path d="m8 9 4-4 4 4"/><path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>',
            'arrow'  => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
            'mail'   => '<rect x="3" y="5.5" width="18" height="13" rx="2.5"/><path d="m4 8 8 5.5L20 8"/>',
        );
        $path = isset($svg[$name]) ? $svg[$name] : '';
        return '<svg class="pov-icon pov-icon-' . esc_attr($name) . '" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
    }

    public static function button_icon() {
        return self::icon('mail');
    }
}
