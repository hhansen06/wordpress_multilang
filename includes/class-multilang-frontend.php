<?php
/**
 * Frontend:
 *  - Injects language flag switcher into the "Top Bar Menu" (menu-2) or
 *    after it as a widget-style block.
 *  - Adds a <html lang=""> attribute matching the current language.
 *  - Provides the [multilang_switcher] shortcode for manual placement.
 */
defined('ABSPATH') || exit;

class Multilang_Frontend
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Inject flag switcher as nav menu items into menu-2 (Top Bar)
        add_filter('wp_nav_menu_items', array($this, 'inject_flags_into_menu'), 10, 2);

        // Set <html lang="">
        add_filter('language_attributes', array($this, 'filter_language_attributes'), 10, 2);

        // Shortcode for manual placement
        add_shortcode('multilang_switcher', array($this, 'render_switcher_shortcode'));

        // Enqueue frontend styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // Provide the switching URL even for AJAX
        add_action('wp_head', array($this, 'print_inline_data'));
    }

    // ── Language attributes ───────────────────────────────────────────────────

    public function filter_language_attributes($output, $doctype)
    {
        $lang = multilang_current();
        $info = Multilang_Languages::get_instance()->get($lang);
        if ($info && isset($info['locale'])) {
            // Convert de_DE → de-DE for HTML lang attribute
            $html_lang = str_replace('_', '-', $info['locale']);
            $output = preg_replace('/lang=["\'][^"\']*["\']/', 'lang="' . esc_attr($html_lang) . '"', $output);
        }
        return $output;
    }

    // ── Inject flag items into the top bar nav menu ───────────────────────────

    /**
     * Appends flag links to the top bar menu (theme_location = menu-2).
     */
    public function inject_flags_into_menu($items, $args)
    {
        // Only inject into the top bar menu
        if (!isset($args->theme_location) || 'menu-2' !== $args->theme_location) {
            return $items;
        }

        $items .= $this->render_switcher_html('li', true);
        return $items;
    }

    // ── Shortcode ─────────────────────────────────────────────────────────────

    /**
     * [multilang_switcher style="flags|list|dropdown"] 
     */
    public function render_switcher_shortcode($atts)
    {
        $atts = shortcode_atts(array('style' => 'flags'), $atts, 'multilang_switcher');
        return $this->render_switcher_html('span', false, $atts['style']);
    }

    // ── Switcher HTML builder ─────────────────────────────────────────────────

    /**
     * Builds the language switcher HTML.
     *
     * @param  string $wrapper   'li' (for nav menus) or 'span' (for shortcodes)
     * @param  bool   $nav_mode  When true, wraps in <li> matching WP nav markup
     * @param  string $style     'flags' | 'list' | 'dropdown'
     * @return string
     */
    private function render_switcher_html($wrapper = 'li', $nav_mode = false, $style = 'flags')
    {
        $langs = Multilang_Languages::get_instance()->get_enabled();
        $current = multilang_current();

        if (empty($langs) || count($langs) < 2) {
            return '';
        }

        $current_url = (is_ssl() ? 'https://' : 'http://') . sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'] ?? '')) . sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'));
        // Strip existing lang param
        $base_url = remove_query_arg('lang', $current_url);

        $html = '';

        if ('dropdown' === $style) {
            // Dropdown select style
            $html .= '<' . esc_attr($wrapper) . ' class="multilang-switcher multilang-dropdown">';
            $html .= '<select onchange="window.location.href=this.value" aria-label="' . esc_attr__('Sprache wählen', 'multilang') . '">';
            foreach ($langs as $code => $lang) {
                $url = esc_url(add_query_arg('lang', $code, $base_url));
                $selected = ($code === $current) ? ' selected' : '';
                $html .= '<option value="' . $url . '"' . $selected . '>' . esc_html($lang['flag'] . ' ' . $lang['label']) . '</option>';
            }
            $html .= '</select>';
            $html .= '</' . esc_attr($wrapper) . '>';

        } elseif ('list' === $style) {
            // Text list
            $html .= '<' . esc_attr($wrapper) . ' class="multilang-switcher multilang-list">';
            $parts = array();
            foreach ($langs as $code => $lang) {
                $url = esc_url(add_query_arg('lang', $code, $base_url));
                $active = ($code === $current) ? ' class="multilang-active"' : '';
                $parts[] = '<a href="' . $url . '"' . $active . ' hreflang="' . esc_attr($code) . '">' . esc_html($lang['flag'] . ' ' . $lang['label']) . '</a>';
            }
            $html .= implode(' | ', $parts);
            $html .= '</' . esc_attr($wrapper) . '>';

        } else {
            // Flags (default) — nav menu style: one <li> per language
            foreach ($langs as $code => $lang) {
                $url = esc_url(add_query_arg('lang', $code, $base_url));
                $active = ($code === $current) ? ' current-language-item' : '';
                if ($nav_mode) {
                    $html .= '<li class="menu-item multilang-switcher-item' . $active . '">';
                    $html .= '<a href="' . $url . '" hreflang="' . esc_attr($code) . '" title="' . esc_attr($lang['label']) . '">'
                        . '<span class="multilang-flag" aria-hidden="true">' . esc_html($lang['flag']) . '</span>'
                        . '<span class="multilang-label screen-reader-text">' . esc_html($lang['label']) . '</span>'
                        . '</a>';
                    $html .= '</li>';
                } else {
                    $html .= '<span class="multilang-switcher-item' . $active . '">';
                    $html .= '<a href="' . $url . '" hreflang="' . esc_attr($code) . '" title="' . esc_attr($lang['label']) . '">'
                        . esc_html($lang['flag'])
                        . '</a>';
                    $html .= '</span>';
                }
            }
        }

        return $html;
    }

    // ── Assets ────────────────────────────────────────────────────────────────

    public function enqueue_assets()
    {
        wp_enqueue_style(
            'multilang-frontend',
            MULTILANG_URL . 'assets/frontend.css',
            array(),
            MULTILANG_VERSION
        );
    }

    public function print_inline_data()
    {
        $lang = multilang_current();
        $info = Multilang_Languages::get_instance()->get($lang);
        echo '<meta name="multilang-current" content="' . esc_attr($lang) . '">' . "\n";
    }
}
