<?php
/**
 * Plugin Name: Multilang Support
 * Plugin URI:  https://github.com/local/wordpress_multilang
 * Description: Mehrsprachigkeit für WordPress: Automatische Spracherkennung, konfigurierbare Sprachen, Flaggen-Switcher im Menü, übersetzte Menüeinträge und Seiten/Beiträge ohne externe Dienste.
 * Version:     1.0.7
 * Author:      Custom Plugin
 * Text Domain: multilang
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

define('MULTILANG_VERSION', '1.0.7');
define('MULTILANG_DIR', plugin_dir_path(__FILE__));
define('MULTILANG_URL', plugin_dir_url(__FILE__));
define('MULTILANG_OPTION', 'multilang_languages');
define('MULTILANG_COOKIE', 'multilang_lang');

// ── Autoload modules ──────────────────────────────────────────────────────────
require_once MULTILANG_DIR . 'includes/class-multilang-languages.php';
require_once MULTILANG_DIR . 'includes/class-multilang-detector.php';
require_once MULTILANG_DIR . 'includes/class-multilang-admin.php';
require_once MULTILANG_DIR . 'includes/class-multilang-menu.php';
require_once MULTILANG_DIR . 'includes/class-multilang-post.php';
require_once MULTILANG_DIR . 'includes/class-multilang-frontend.php';

// ── Bootstrap ─────────────────────────────────────────────────────────────────
add_action('plugins_loaded', array('Multilang_Languages', 'get_instance'));
add_action('plugins_loaded', array('Multilang_Detector', 'get_instance'));
add_action('plugins_loaded', array('Multilang_Admin', 'get_instance'));
add_action('plugins_loaded', array('Multilang_Menu', 'get_instance'));
add_action('plugins_loaded', array('Multilang_Post', 'get_instance'));
add_action('plugins_loaded', array('Multilang_Frontend', 'get_instance'));

// ── Activation: install defaults ─────────────────────────────────────────────
register_activation_hook(__FILE__, 'multilang_activate');
function multilang_activate()
{
    if (!get_option(MULTILANG_OPTION)) {
        $defaults = array(
            array(
                'code' => 'de',
                'locale' => 'de_DE',
                'label' => 'Deutsch',
                'flag' => '🇩🇪',
                'enabled' => true,
                'default' => true,
            ),
            array(
                'code' => 'en',
                'locale' => 'en_US',
                'label' => 'English',
                'flag' => '🇬🇧',
                'enabled' => true,
                'default' => false,
            ),
        );
        update_option(MULTILANG_OPTION, $defaults);
    }
}
