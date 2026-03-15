<?php
/**
 * Admin settings page: configure available languages.
 */
defined('ABSPATH') || exit;

class Multilang_Admin
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
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_post_multilang_save', array($this, 'handle_save'));
        add_action('admin_post_multilang_add', array($this, 'handle_add'));
        add_action('admin_post_multilang_delete', array($this, 'handle_delete'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function add_menu()
    {
        add_options_page(
            __('Multilang', 'multilang'),
            __('Multilang', 'multilang'),
            'manage_options',
            'multilang',
            array($this, 'render_page')
        );
    }

    public function enqueue_assets($hook)
    {
        if ('settings_page_multilang' !== $hook) {
            return;
        }
        wp_enqueue_style(
            'multilang-admin',
            MULTILANG_URL . 'assets/admin.css',
            array(),
            MULTILANG_VERSION
        );
        wp_enqueue_script(
            'multilang-admin',
            MULTILANG_URL . 'assets/admin.js',
            array('jquery', 'jquery-ui-sortable'),
            MULTILANG_VERSION,
            true
        );
    }

    // ── Save existing languages (reorder, toggle, edit) ──────────────────────

    public function handle_save()
    {
        check_admin_referer('multilang_save');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'multilang'));
        }

        $submitted = isset($_POST['languages']) ? (array) $_POST['languages'] : array(); // phpcs:ignore
        $sanitized = array();

        foreach ($submitted as $raw) {
            $code = sanitize_key($raw['code'] ?? '');
            if (empty($code)) {
                continue;
            }
            $sanitized[] = array(
                'code' => $code,
                'locale' => sanitize_text_field($raw['locale'] ?? ''),
                'label' => sanitize_text_field($raw['label'] ?? ''),
                'flag' => $this->sanitize_emoji($raw['flag'] ?? ''),
                'enabled' => !empty($raw['enabled']),
                'default' => isset($_POST['default_lang']) && sanitize_key($_POST['default_lang']) === $code, // phpcs:ignore
            );
        }

        update_option(MULTILANG_OPTION, $sanitized);

        wp_safe_redirect(
            add_query_arg(array('page' => 'multilang', 'saved' => '1'), admin_url('options-general.php'))
        );
        exit;
    }

    // ── Add a new language ────────────────────────────────────────────────────

    public function handle_add()
    {
        check_admin_referer('multilang_add');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'multilang'));
        }

        $code = sanitize_key($_POST['new_code'] ?? ''); // phpcs:ignore
        $locale = sanitize_text_field($_POST['new_locale'] ?? ''); // phpcs:ignore
        $label = sanitize_text_field($_POST['new_label'] ?? ''); // phpcs:ignore
        $flag = $this->sanitize_emoji($_POST['new_flag'] ?? ''); // phpcs:ignore

        if (empty($code) || empty($label)) {
            wp_safe_redirect(
                add_query_arg(array('page' => 'multilang', 'error' => 'missing'), admin_url('options-general.php'))
            );
            exit;
        }

        $existing = get_option(MULTILANG_OPTION, array());

        // Prevent duplicate codes
        foreach ($existing as $lang) {
            if ($lang['code'] === $code) {
                wp_safe_redirect(
                    add_query_arg(array('page' => 'multilang', 'error' => 'duplicate'), admin_url('options-general.php'))
                );
                exit;
            }
        }

        $existing[] = array(
            'code' => $code,
            'locale' => $locale ?: $code,
            'label' => $label,
            'flag' => $flag,
            'enabled' => true,
            'default' => empty($existing), // First lang becomes default
        );

        update_option(MULTILANG_OPTION, $existing);

        wp_safe_redirect(
            add_query_arg(array('page' => 'multilang', 'added' => '1'), admin_url('options-general.php'))
        );
        exit;
    }

    // ── Delete a language ─────────────────────────────────────────────────────

    public function handle_delete()
    {
        check_admin_referer('multilang_delete');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'multilang'));
        }

        // Support both GET (link) and POST (form fallback)
        $code = sanitize_key( isset( $_GET['del_code'] ) ? wp_unslash( $_GET['del_code'] ) : ( $_POST['del_code'] ?? '' ) ); // phpcs:ignore
        $existing = get_option(MULTILANG_OPTION, array());
        $filtered = array_filter($existing, static function ($l) use ($code) {
            return $l['code'] !== $code;
        });
        update_option(MULTILANG_OPTION, array_values($filtered));

        wp_safe_redirect(
            add_query_arg(array('page' => 'multilang', 'deleted' => '1'), admin_url('options-general.php'))
        );
        exit;
    }

    // ── Settings page HTML ────────────────────────────────────────────────────

    public function render_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $languages = get_option(MULTILANG_OPTION, array());
        ?>
        <div class="wrap multilang-admin">
            <h1><?php esc_html_e('Multilang – Sprachkonfiguration', 'multilang'); ?></h1>

            <?php if (isset($_GET['saved'])):  // phpcs:ignore ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Einstellungen gespeichert.', 'multilang'); ?></p>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['added'])):  // phpcs:ignore ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Sprache hinzugefügt.', 'multilang'); ?></p>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['deleted'])):  // phpcs:ignore ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Sprache entfernt.', 'multilang'); ?></p>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error']) && 'duplicate' === $_GET['error']):  // phpcs:ignore ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php esc_html_e('Ein Sprachcode mit diesem Kürzel existiert bereits.', 'multilang'); ?></p>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error']) && 'missing' === $_GET['error']):  // phpcs:ignore ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php esc_html_e('Sprachcode und Bezeichnung sind Pflichtfelder.', 'multilang'); ?></p>
                </div>
            <?php endif; ?>

            <h2><?php esc_html_e('Konfigurierte Sprachen', 'multilang'); ?></h2>
            <p class="description">
                <?php esc_html_e('Reihenfolge per Drag &amp; Drop ändern. Die erste aktive Sprache mit "Standard" ist die Fallback-Sprache.', 'multilang'); ?>
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="multilang-languages-form">
                <?php wp_nonce_field('multilang_save'); ?>
                <input type="hidden" name="action" value="multilang_save">

                <table class="wp-list-table widefat fixed striped multilang-table" id="multilang-sortable">
                    <thead>
                        <tr>
                            <th class="sort-handle" style="width:30px"></th>
                            <th><?php esc_html_e('Code', 'multilang'); ?></th>
                            <th><?php esc_html_e('Locale', 'multilang'); ?></th>
                            <th><?php esc_html_e('Bezeichnung', 'multilang'); ?></th>
                            <th><?php esc_html_e('Flagge / Emoji', 'multilang'); ?></th>
                            <th><?php esc_html_e('Aktiv', 'multilang'); ?></th>
                            <th><?php esc_html_e('Standard', 'multilang'); ?></th>
                            <th><?php esc_html_e('Löschen', 'multilang'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($languages as $idx => $lang): ?>
                            <tr class="multilang-row" data-code="<?php echo esc_attr($lang['code']); ?>">
                                <td><span class="dashicons dashicons-move multilang-handle" style="cursor:move"></span></td>
                                <td>
                                    <input type="hidden" name="languages[<?php echo esc_attr($idx); ?>][code]"
                                        value="<?php echo esc_attr($lang['code']); ?>">
                                    <strong><?php echo esc_html($lang['code']); ?></strong>
                                </td>
                                <td>
                                    <input type="text" class="small-text" name="languages[<?php echo esc_attr($idx); ?>][locale]"
                                        value="<?php echo esc_attr($lang['locale']); ?>" placeholder="de_DE">
                                </td>
                                <td>
                                    <input type="text" class="regular-text" name="languages[<?php echo esc_attr($idx); ?>][label]"
                                        value="<?php echo esc_attr($lang['label']); ?>" required>
                                </td>
                                <td>
                                    <input type="text" class="small-text multilang-flag-input"
                                        name="languages[<?php echo esc_attr($idx); ?>][flag]"
                                        value="<?php echo esc_attr($lang['flag']); ?>" placeholder="🇩🇪" maxlength="10">
                                </td>
                                <td>
                                    <input type="checkbox" name="languages[<?php echo esc_attr($idx); ?>][enabled]" value="1"
                                        <?php checked(!empty($lang['enabled'])); ?>>
                                </td>
                                <td>
                                    <input type="radio" name="default_lang" value="<?php echo esc_attr($lang['code']); ?>" <?php checked(!empty($lang['default'])); ?>>
                                </td>
                                <td>
                                    <?php
                                    $delete_url = wp_nonce_url(
                                        add_query_arg(
                                            array(
                                                'action'   => 'multilang_delete',
                                                'del_code' => $lang['code'],
                                            ),
                                            admin_url( 'admin-post.php' )
                                        ),
                                        'multilang_delete'
                                    );
                                    ?>
                                    <a href="<?php echo esc_url( $delete_url ); ?>"
                                       class="button button-link-delete"
                                       onclick="return confirm('<?php esc_attr_e( 'Sprache wirklich löschen?', 'multilang' ); ?>')">
                                        <?php esc_html_e( 'Löschen', 'multilang' ); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit"
                        class="button button-primary"><?php esc_html_e('Änderungen speichern', 'multilang'); ?></button>
                </p>
            </form>

            <hr>

            <h2><?php esc_html_e('Neue Sprache hinzufügen', 'multilang'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="multilang-add-form">
                <?php wp_nonce_field('multilang_add'); ?>
                <input type="hidden" name="action" value="multilang_add">
                <table class="form-table">
                    <tr>
                        <th><label for="new_code"><?php esc_html_e('Sprachcode (z.B. fr)', 'multilang'); ?></label></th>
                        <td><input type="text" id="new_code" name="new_code" class="small-text" required pattern="[a-z]{2,5}"
                                placeholder="fr"></td>
                    </tr>
                    <tr>
                        <th><label for="new_locale"><?php esc_html_e('Locale (z.B. fr_FR)', 'multilang'); ?></label></th>
                        <td><input type="text" id="new_locale" name="new_locale" class="small-text" placeholder="fr_FR"></td>
                    </tr>
                    <tr>
                        <th><label for="new_label"><?php esc_html_e('Bezeichnung (z.B. Français)', 'multilang'); ?></label>
                        </th>
                        <td><input type="text" id="new_label" name="new_label" class="regular-text" required
                                placeholder="Français"></td>
                    </tr>
                    <tr>
                        <th><label for="new_flag"><?php esc_html_e('Flagge / Emoji (z.B. 🇫🇷)', 'multilang'); ?></label></th>
                        <td><input type="text" id="new_flag" name="new_flag" class="small-text" placeholder="🇫🇷"
                                maxlength="10"></td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit"
                        class="button button-secondary"><?php esc_html_e('Sprache hinzufügen', 'multilang'); ?></button>
                </p>
            </form>

            <hr>
            <h2><?php esc_html_e('Hinweise', 'multilang'); ?></h2>
            <ul class="ul-disc" style="max-width:700px">
                <li><?php esc_html_e('Im Menü-Editor erscheint unter jedem Menüpunkt ein Abschnitt „Übersetzungen" mit einem Namensfeld pro aktiver Sprache.', 'multilang'); ?>
                </li>
                <li><?php esc_html_e('In Seiten und Beiträgen werden oben Sprach-Tabs angezeigt (Titel, Inhalt, Auszug je Sprache).', 'multilang'); ?>
                </li>
                <li><?php esc_html_e('Dem Top-Menü (menu-2) wird automatisch ein Flaggen-Sprach-Schalter hinzugefügt.', 'multilang'); ?>
                </li>
                <li><?php esc_html_e('Die Sprache wird automatisch aus dem Browser-Header erkannt und kann über Den Flaggen-Schalter überschrieben werden (Cookie, 1 Jahr).', 'multilang'); ?>
                </li>
            </ul>
        </div>
        <?php
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Sanitize an emoji / flag input – allow only emoji characters and basic ASCII.
     * @param  string $raw
     * @return string
     */
    private function sanitize_emoji($raw)
    {
        // Strip HTML tags and control characters, keep emoji code points
        $clean = wp_strip_all_tags($raw);
        // Limit length
        return mb_substr($clean, 0, 10);
    }
}
