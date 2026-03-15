<?php
/**
 * Manages the configured languages list.
 * Single source of truth for all other modules.
 */
defined('ABSPATH') || exit;

class Multilang_Languages
{

    private static $instance = null;

    /** @var array Enabled languages keyed by language code */
    private $languages = array();

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->load();
    }

    private function load()
    {
        $raw = get_option(MULTILANG_OPTION, array());
        $this->languages = array();
        foreach ($raw as $lang) {
            if (!empty($lang['enabled'])) {
                $this->languages[$lang['code']] = $lang;
            }
        }
    }

    /**
     * Returns all enabled languages.
     * @return array  [ 'de' => [...], 'en' => [...], ... ]
     */
    public function get_enabled()
    {
        return $this->languages;
    }

    /**
     * Returns the default language array or false if none configured.
     * @return array|false
     */
    public function get_default()
    {
        foreach ($this->languages as $lang) {
            if (!empty($lang['default'])) {
                return $lang;
            }
        }
        // Fallback: first enabled
        return !empty($this->languages) ? reset($this->languages) : false;
    }

    /**
     * Checks whether a code is a valid enabled language.
     * @param  string $code  e.g. 'de'
     * @return bool
     */
    public function is_valid($code)
    {
        return isset($this->languages[sanitize_key($code)]);
    }

    /**
     * Returns data for a single language or false.
     * @param  string $code
     * @return array|false
     */
    public function get($code)
    {
        $code = sanitize_key($code);
        return isset($this->languages[$code]) ? $this->languages[$code] : false;
    }

    /**
     * Helper: returns language codes as a simple array.
     * @return string[]
     */
    public function get_codes()
    {
        return array_keys($this->languages);
    }
}
