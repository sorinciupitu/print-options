<?php
/**
 * GitHub release based update checker for the Print Options plugin.
 *
 * The repository is public, so the WordPress site only needs to read the
 * latest release from the GitHub API. Every push that touches plugin files
 * creates a new release through .github/workflows/release.yml.
 *
 * @package print-options
 */

if (!defined('ABSPATH')) {
    exit;
}

final class POV_Updater {

    const SLUG      = 'print-options/print-options.php';
    const CACHE_KEY = 'pov_github_latest_release';
    const CACHE_TTL = 6 * HOUR_IN_SECONDS;
    const FAIL_TTL  = 30 * MINUTE_IN_SECONDS;

    public static function init() {
        add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'inject_update'));
        add_filter('plugins_api', array(__CLASS__, 'plugin_info'), 10, 3);
        add_filter('plugin_row_meta', array(__CLASS__, 'row_meta'), 10, 2);
        add_action('admin_post_pov_check_updates', array(__CLASS__, 'force_check'));
        add_action('upgrader_process_complete', array(__CLASS__, 'clear_cache'), 10, 2);
        add_action('upgrader_source_selection', array(__CLASS__, 'fix_source_dir'), 10, 4);
        add_action('admin_init', array(__CLASS__, 'maybe_refresh'), 1);
        add_action('admin_notices', array(__CLASS__, 'check_notice'));
    }

    public static function repo() {
        return defined('POV_GITHUB_REPO') ? POV_GITHUB_REPO : 'sorinciupitu/print-options';
    }

    /**
     * Real plugin basename, so updates keep working even if the folder was
     * renamed (for example a GitHub "Download ZIP" install).
     */
    public static function basename() {
        if (defined('POV_FILE') && function_exists('plugin_basename')) {
            $basename = plugin_basename(POV_FILE);
            if ($basename) {
                return $basename;
            }
        }
        return self::SLUG;
    }

    public static function folder() {
        $basename = self::basename();
        $folder   = dirname($basename);
        return '.' === $folder ? 'print-options' : $folder;
    }

    public static function plugin_url() {
        return 'https://github.com/' . self::repo();
    }

    /**
     * Latest published release, or an empty array when GitHub is unreachable.
     *
     * @return array{version:string,package:string,body:string,url:string,date:string,assets:array}
     */
    public static function release() {
        $cached = get_site_transient(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }
        return self::fetch_release();
    }

    private static function fetch_release() {
        $args = array(
            'timeout' => 8,
            'headers' => array(
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'Print-Options/' . (defined('POV_VERSION') ? POV_VERSION : '0'),
            ),
        );

        $response = wp_remote_get('https://api.github.com/repos/' . self::repo() . '/releases/latest', $args);
        $release  = array(
            'version' => '',
            'package' => '',
            'body'    => '',
            'url'     => self::plugin_url() . '/releases/latest',
            'date'    => '',
            'assets'  => array(),
        );

        if (!is_wp_error($response) && 200 === (int) wp_remote_retrieve_response_code($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($data)) {
                $release['version'] = isset($data['tag_name']) ? ltrim((string) $data['tag_name'], 'vV') : '';
                $release['body']    = isset($data['body']) ? (string) $data['body'] : '';
                $release['url']     = isset($data['html_url']) ? (string) $data['html_url'] : $release['url'];
                $release['date']    = isset($data['published_at']) ? (string) $data['published_at'] : '';

                if (!empty($data['assets']) && is_array($data['assets'])) {
                    foreach ($data['assets'] as $asset) {
                        if (empty($asset['browser_download_url'])) {
                            continue;
                        }
                        $release['assets'][strtolower((string) $asset['name'])] = (string) $asset['browser_download_url'];
                    }
                }

                if (isset($release['assets']['print-options.zip'])) {
                    $release['package'] = $release['assets']['print-options.zip'];
                } elseif (!empty($data['zipball_url'])) {
                    $release['package'] = (string) $data['zipball_url'];
                }
            }
        }

        set_site_transient(
            self::CACHE_KEY,
            $release,
            $release['version'] ? self::CACHE_TTL : self::FAIL_TTL
        );

        return $release;
    }

    private static function has_update($release) {
        if (empty($release['version']) || empty($release['package'])) {
            return false;
        }
        if (!defined('POV_VERSION')) {
            return false;
        }
        return version_compare($release['version'], POV_VERSION, '>');
    }

    public static function inject_update($transient) {
        if (!is_object($transient)) {
            return $transient;
        }

        $release = self::release();
        if (!self::has_update($release)) {
            return $transient;
        }

        $key                = self::basename();
        $item               = new stdClass();
        $item->id           = $key;
        $item->slug         = 'print-options';
        $item->plugin       = $key;
        $item->new_version  = $release['version'];
        $item->url          = self::plugin_url();
        $item->package      = $release['package'];
        $item->icons        = array();
        $item->banners      = array();
        $item->tested       = '';
        $item->requires     = '';
        $item->requires_php = '7.4';

        if (!isset($transient->response) || !is_array($transient->response)) {
            $transient->response = array();
        }
        $transient->response[$key] = $item;

        if (isset($transient->no_update) && is_array($transient->no_update)) {
            unset($transient->no_update[$key]);
        }

        return $transient;
    }

    public static function plugin_info($result, $action, $args) {
        if ('plugin_information' !== $action || empty($args->slug) || 'print-options' !== $args->slug) {
            return $result;
        }

        $release = self::release();
        $info    = new stdClass();
        $info->name          = 'Print Options for WooCommerce';
        $info->slug          = 'print-options';
        $info->version       = $release['version'] ? $release['version'] : (defined('POV_VERSION') ? POV_VERSION : '');
        $info->author        = '<a href="' . esc_url('https://github.com/sorinciupitu') . '">Sorin Ciupitu</a>';
        $info->author_profile = 'https://github.com/sorinciupitu';
        $info->homepage      = self::plugin_url();
        $info->download_link = $release['package'];
        $info->requires      = '';
        $info->tested        = '';
        $info->requires_php  = '7.4';
        $info->last_updated  = $release['date'];
        $info->sections      = array(
            'description' => 'Opțiuni vizuale, cantități și prețuri pentru produse WooCommerce, plus pagina „Cere ofertă” și butonul aferent pe produse.',
            'changelog'   => $release['body'] ? wpautop(esc_html($release['body'])) : 'Vezi lista completă a modificărilor pe ' . '<a href="' . esc_url($release['url']) . '">GitHub Releases</a>.',
        );

        return $info;
    }

    public static function row_meta($links, $file) {
        if (self::basename() !== $file) {
            return $links;
        }

        $links[] = '<a href="' . esc_url(self::plugin_url()) . '" target="_blank" rel="noopener">GitHub</a>';

        if (current_user_can('update_plugins')) {
            $url = wp_nonce_url(
                add_query_arg('action', 'pov_check_updates', admin_url('admin-post.php')),
                'pov_check_updates'
            );
            $links[] = '<a href="' . esc_url($url) . '">Verifică actualizări</a>';
        }

        return $links;
    }

    public static function check_notice() {
        if (empty($_GET['pov_checked']) || !current_user_can('update_plugins')) {
            return;
        }
        $release = self::release();
        $message = self::has_update($release)
            ? 'Există o versiune nouă: <strong>' . esc_html($release['version']) . '</strong>.'
            : 'Pluginul Print Options este la zi.';
        echo '<div class="notice notice-info is-dismissible"><p>Verificare actualizări GitHub: ' . $message . '</p></div>';
    }

    public static function force_check() {
        if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
            wp_die('Acces interzis.');
        }
        check_admin_referer('pov_check_updates');

        delete_site_transient(self::CACHE_KEY);
        delete_site_transient('update_plugins');
        if (function_exists('wp_update_plugins')) {
            wp_update_plugins();
        }

        wp_safe_redirect(add_query_arg('pov_checked', '1', admin_url('plugins.php')));
        exit;
    }

    public static function clear_cache($upgrader, $options = array()) {
        delete_site_transient(self::CACHE_KEY);
    }

    /**
     * Keep the installed folder named "print-options" even when the fallback
     * zipball (print-options-main) is used instead of the release asset.
     */
    public static function fix_source_dir($source, $remote_source, $upgrader, $hook_extra) {
        if (!is_array($hook_extra) || empty($hook_extra['plugin']) || self::basename() !== $hook_extra['plugin']) {
            return $source;
        }

        $folder = self::folder();
        $source = untrailingslashit((string) $source);
        if ($folder === basename($source)) {
            return trailingslashit($source);
        }

        global $wp_filesystem;
        if (!$wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $target = trailingslashit($remote_source) . $folder;
        if (basename($source) !== $folder && $wp_filesystem && $wp_filesystem->move($source, $target, true)) {
            return trailingslashit($target);
        }

        return trailingslashit($source);
    }

    /**
     * When a new release is published, drop the cached update transient on the
     * Plugins screen so WordPress performs the check again immediately instead
     * of waiting for its 12 hour window.
     */
    public static function maybe_refresh() {
        if (!current_user_can('update_plugins')) {
            return;
        }
        if (empty($GLOBALS['pagenow']) || 'plugins.php' !== $GLOBALS['pagenow']) {
            return;
        }

        $release = self::release();
        if (!self::has_update($release)) {
            return;
        }

        $transient = get_site_transient('update_plugins');
        if (is_object($transient) && !empty($transient->response[ self::basename() ])) {
            return;
        }

        delete_site_transient('update_plugins');
    }
}
