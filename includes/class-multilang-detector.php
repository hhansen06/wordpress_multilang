<?php
/**
 * Detects and stores the current active language.
 *
 * Priority:
 *  1. URL query-param  ?lang=xx  (for explicit switch)
 *  2. Cookie           multilang_lang
 *  3. Browser Accept-Language header
 *  4. Site default language
 */
defined('ABSPATH') || exit;

class Multilang_Detector
{

    private static $instance = null;
    private $current = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Run early so other hooks can already read the current language.
        add_action('init', array($this, 'detect'), 1);
    }

    public function detect()
    {
        $langs = Multilang_Languages::get_instance();

        // 1. Explicit switch via query param
        if (isset($_GET['lang'])) { // phpcs:ignore WordPress.Security.NonceVerification
            $requested = sanitize_key(wp_unslash($_GET['lang'])); // phpcs:ignore
            if ($langs->is_valid($requested)) {
                $this->current = $requested;
                $this->set_cookie($requested);
                return;
            }
        }

        // 2. Cookie
        if (isset($_COOKIE[MULTILANG_COOKIE])) {
            $cookie = sanitize_key(wp_unslash($_COOKIE[MULTILANG_COOKIE]));
            if ($langs->is_valid($cookie)) {
                $this->current = $cookie;
                return;
            }
        }

        // 3. Accept-Language header
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $accepted = sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT_LANGUAGE']));
            $detected = $this->parse_accept_language($accepted, $langs);
            if ($detected) {
                $this->current = $detected;
                return;
            }
        }

        // 4. Site default
        $default = $langs->get_default();
        if ($default) {
            $this->current = $default['code'];
        }
    }

    /**
     * Parses an Accept-Language header and returns the best matching code.
     *
     * @param  string             $header  e.g. "de-AT,de;q=0.9,en;q=0.8"
     * @param  Multilang_Languages $langs
     * @return string|null
     */
    private function parse_accept_language($header, $langs)
    {
        // Build list of (language-tag, quality) pairs, sorted by quality desc.
        $parts = explode(',', $header);
        $parsed = array();
        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/^([a-zA-Z\-]+)(?:;q=([\d.]+))?$/i', $part, $m)) {
                $parsed[] = array(
                    'tag' => strtolower($m[1]),
                    'q' => isset($m[2]) ? (float) $m[2] : 1.0,
                );
            }
        }
        usort($parsed, static function ($a, $b) {
            return $b['q'] <=> $a['q'];
        });

        foreach ($parsed as $item) {
            // Try exact match first (e.g. "de-at" → "de")
            $base = strtok($item['tag'], '-');
            if ($langs->is_valid($base)) {
                return $base;
            }
        }
        return null;
    }

    /**
     * Returns the currently active language code.
     * @return string
     */
    public function get_current()
    {
        if (null === $this->current) {
            $default = Multilang_Languages::get_instance()->get_default();
            $this->current = $default ? $default['code'] : 'de';
        }
        return $this->current;
    }

    /**
     * Sets the language cookie (1 year).
     * @param string $code
     */
    private function set_cookie($code)
    {
        if (!headers_sent()) {
            setcookie(
                MULTILANG_COOKIE,
                $code,
                time() + YEAR_IN_SECONDS,
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true  // httponly
            );
        }
    }
}

/**
 * Global helper — returns the current language code.
 * @return string
 */
function multilang_current()
{
    return Multilang_Detector::get_instance()->get_current();
}
