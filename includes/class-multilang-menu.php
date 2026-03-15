<?php
/**
 * Menu editor integration:
 *  - Adds a "Translations" section to each nav menu item in the admin editor
 *  - Filters nav_menu_item_title on the frontend to replace with translated label
 */
defined('ABSPATH') || exit;

class Multilang_Menu
{

    private static $instance = null;
    // Meta key prefix for nav menu item translations
    const META_PREFIX = '_multilang_menu_title_';

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Admin: add extra fields to nav menu item editor
        add_filter('wp_setup_nav_menu_item', array($this, 'load_item_translations'));
        add_action('wp_nav_menu_item_custom_fields', array($this, 'render_item_fields'), 10, 4);
        add_action('wp_update_nav_menu_item', array($this, 'save_item_fields'), 10, 3);

        // Frontend: replace menu item title with translated version
        add_filter('nav_menu_item_title', array($this, 'translate_item_title'), 10, 4);

        // Also translate the "title" attribute (tooltip)
        add_filter('nav_menu_link_attributes', array($this, 'translate_link_attributes'), 10, 4);
    }

    // ── Admin: load translation data onto the menu item object ────────────────

    public function load_item_translations($item)
    {
        $langs = Multilang_Languages::get_instance()->get_enabled();
        foreach ($langs as $code => $lang) {
            $key = self::META_PREFIX . $code;
            $item->$key = get_post_meta($item->ID, $key, true);
        }
        return $item;
    }

    // ── Admin: render translation fields inside the menu item edit box ────────

    /**
     * @param int    $item_id   Menu item post ID
     * @param object $item      Menu item object
     * @param int    $depth
     * @param object $args
     */
    public function render_item_fields($item_id, $item, $depth, $args)
    {
        $langs = Multilang_Languages::get_instance()->get_enabled();
        if (empty($langs)) {
            return;
        }
        echo '<div class="multilang-menu-translations" style="border-top:1px solid #ddd;padding-top:8px;margin-top:8px">';
        echo '<p class="multilang-menu-translations-heading" style="font-weight:600;margin:0 0 6px">' . esc_html__('Übersetzungen', 'multilang') . '</p>';
        foreach ($langs as $code => $lang) {
            $meta_key = self::META_PREFIX . $code;
            $value = get_post_meta($item_id, $meta_key, true);
            echo '<p class="field-multilang-' . esc_attr($code) . ' description description-wide">';
            printf(
                '<label for="edit-menu-item-multilang-%1$s-%2$s">'
                . '<span class="multilang-flag">%3$s</span> %4$s'
                . '<input type="text" id="edit-menu-item-multilang-%1$s-%2$s" '
                . 'class="widefat edit-menu-item-multilang" '
                . 'name="_multilang_menu_title_%1$s[%2$s]" '
                . 'value="%5$s" placeholder="%6$s">'
                . '</label>',
                esc_attr($code),
                esc_attr($item_id),
                esc_html($lang['flag']),
                esc_html($lang['label']),
                esc_attr($value),
                esc_attr($item->title)
            );
            echo '</p>';
        }
        echo '</div>';
    }

    // ── Admin: save translation fields on menu save ───────────────────────────

    /**
     * @param int   $menu_id
     * @param int   $menu_item_db_id
     * @param array $args
     */
    public function save_item_fields($menu_id, $menu_item_db_id, $args)
    {
        $langs = Multilang_Languages::get_instance()->get_codes();
        foreach ($langs as $code) {
            $post_key = '_multilang_menu_title_' . $code;
            if (isset($_POST[$post_key][$menu_item_db_id])) { // phpcs:ignore WordPress.Security.NonceVerification
                $value = sanitize_text_field(wp_unslash($_POST[$post_key][$menu_item_db_id])); // phpcs:ignore
                if ($value !== '') {
                    update_post_meta($menu_item_db_id, $post_key, $value);
                } else {
                    delete_post_meta($menu_item_db_id, $post_key);
                }
            }
        }
    }

    // ── Frontend: swap menu item title with translation ───────────────────────

    /**
     * @param string   $title
     * @param WP_Post  $item
     * @param stdClass $args
     * @param int      $depth
     * @return string
     */
    public function translate_item_title($title, $item, $args, $depth)
    {
        $lang = multilang_current();
        $meta = get_post_meta($item->ID, self::META_PREFIX . $lang, true);
        return ($meta !== '' && $meta !== false) ? esc_html($meta) : $title;
    }

    /**
     * Translate the "title" attribute (tooltip) as well.
     */
    public function translate_link_attributes($atts, $item, $args, $depth)
    {
        if (isset($atts['title']) && '' !== $atts['title']) {
            $lang = multilang_current();
            $meta = get_post_meta($item->ID, self::META_PREFIX . $lang, true);
            if ($meta !== '' && $meta !== false) {
                $atts['title'] = esc_attr($meta);
            }
        }
        return $atts;
    }
}
