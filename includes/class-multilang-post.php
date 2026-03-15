<?php
/**
 * Pages & Posts — per-language title, content and excerpt.
 *
 * Strategy: Store translations as post meta.
 * Meta keys:
 *   _multilang_title_{code}
 *   _multilang_content_{code}
 *   _multilang_excerpt_{code}
 *
 * Classic editor: a tabbed meta box above the default editor.
 * Block editor  : a Sidebar Panel injected via the REST API + JS.
 *
 * On the frontend the post title / content / excerpt are filtered
 * via standard WordPress hooks (only on singular views to keep
 * archive pages unaffected – or translated too if desired).
 */
defined('ABSPATH') || exit;

class Multilang_Post
{

    private static $instance = null;

    const META_TITLE = '_multilang_title_';
    const META_CONTENT = '_multilang_content_';
    const META_EXCERPT = '_multilang_excerpt_';

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Register post meta for REST API access (Block Editor)
        add_action('init', array($this, 'register_meta'));

        // Classic editor meta box
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_meta_box'), 10, 2);

        // Block editor: enqueue sidebar panel
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_assets'));

        // Frontend filtering
        add_filter('the_title', array($this, 'filter_title'), 10, 2);
        // Run before core do_blocks/wpautop filters so block markup from
        // translated content is parsed and rendered correctly.
        add_filter('the_content', array($this, 'filter_content'), 1);
        add_filter('the_excerpt', array($this, 'filter_excerpt'), 10);
        // Also filter wp_title (older themes)
        add_filter('wp_title', array($this, 'filter_wp_title'), 10, 3);
    }

    // ── Meta registration (REST / Block Editor) ───────────────────────────────

    public function register_meta()
    {
        $langs = Multilang_Languages::get_instance()->get_codes();
        $post_types = get_post_types(array('show_in_rest' => true));

        foreach ($langs as $code) {
            foreach ($post_types as $pt) {
                $common = array(
                    'object_subtype' => $pt,
                    'single' => true,
                    'show_in_rest' => true,
                    'auth_callback' => static function () {
                        return current_user_can('edit_posts');
                    },
                );
                register_post_meta($pt, self::META_TITLE . $code, array_merge($common, array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field')));
                register_post_meta($pt, self::META_CONTENT . $code, array_merge($common, array('type' => 'string', 'sanitize_callback' => 'wp_kses_post')));
                register_post_meta($pt, self::META_EXCERPT . $code, array_merge($common, array('type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field')));
            }
        }
    }

    // ── Classic editor meta box ───────────────────────────────────────────────

    public function add_meta_box()
    {
        $post_types = get_post_types(array('public' => true));
        foreach ($post_types as $pt) {
            add_meta_box(
                'multilang_translations',
                __('🌐 Übersetzungen', 'multilang'),
                array($this, 'render_meta_box'),
                $pt,
                'normal',
                'high'
            );
        }
    }

    public function render_meta_box($post)
    {
        wp_nonce_field('multilang_post_save', 'multilang_post_nonce');
        $is_block_editor = function_exists('use_block_editor_for_post_type') && use_block_editor_for_post_type($post->post_type);

        $langs = Multilang_Languages::get_instance()->get_enabled();
        if (empty($langs)) {
            echo '<p>' . esc_html__('Keine Sprachen konfiguriert.', 'multilang') . '</p>';
            return;
        }

        $default = Multilang_Languages::get_instance()->get_default();
        ?>
        <style>
            .multilang-tabs {
                display: flex;
                gap: 0;
                margin-bottom: 0;
                border-bottom: 1px solid #ddd;
            }

            .multilang-tab-btn {
                background: #f0f0f0;
                border: 1px solid #ddd;
                border-bottom: none;
                padding: 6px 14px;
                cursor: pointer;
                font-size: 13px;
            }

            .multilang-tab-btn.active {
                background: #fff;
                font-weight: 600;
            }

            .multilang-tab-panel {
                display: none;
                padding: 12px 0;
            }

            .multilang-tab-panel.active {
                display: block;
            }

            .multilang-field-row {
                margin-bottom: 12px;
            }

            .multilang-field-row label {
                display: block;
                font-weight: 600;
                margin-bottom: 4px;
            }

            .multilang-content-preview {
                width: 100%;
            }
        </style>

        <div class="multilang-tabs" id="multilang-tabs">
            <?php $first = true;
            foreach ($langs as $code => $lang): ?>
                <button type="button" class="multilang-tab-btn<?php echo $first ? ' active' : ''; ?>"
                    data-target="multilang-panel-<?php echo esc_attr($code); ?>" onclick="multilangSwitchTab(this)">
                    <?php echo esc_html($lang['flag'] . ' ' . $lang['label']); ?>
                    <?php if (!empty($lang['default'])): ?>
                        <span style="font-size:10px;color:#888">(<?php esc_html_e('Standard', 'multilang'); ?>)</span>
                    <?php endif; ?>
                </button>
                <?php $first = false; endforeach; ?>
        </div>

        <?php $first = true;
        foreach ($langs as $code => $lang): ?>
            <?php
            $title = get_post_meta($post->ID, self::META_TITLE . $code, true);
            $content = get_post_meta($post->ID, self::META_CONTENT . $code, true);
            $excerpt = get_post_meta($post->ID, self::META_EXCERPT . $code, true);
            ?>
            <div class="multilang-tab-panel<?php echo $first ? ' active' : ''; ?>"
                id="multilang-panel-<?php echo esc_attr($code); ?>">

                <div class="multilang-field-row">
                    <label for="multilang_title_<?php echo esc_attr($code); ?>">
                        <?php echo esc_html($lang['flag'] . ' ' . $lang['label'] . ' – '); ?>
                        <?php esc_html_e('Titel', 'multilang'); ?>
                    </label>
                    <input type="text" id="multilang_title_<?php echo esc_attr($code); ?>"
                        name="multilang_title_<?php echo esc_attr($code); ?>" value="<?php echo esc_attr($title); ?>"
                        class="widefat" placeholder="<?php echo esc_attr($post->post_title); ?>">
                </div>

                <div class="multilang-field-row">
                    <label for="multilang_content_<?php echo esc_attr($code); ?>">
                        <?php esc_html_e('Inhalt', 'multilang'); ?>
                        <span style="font-weight:normal;color:#666">
                            <?php echo $is_block_editor ? esc_html__('(Block-Editor)', 'multilang') : esc_html__('(HTML erlaubt)', 'multilang'); ?>
                        </span>
                    </label>
                    <?php if ($is_block_editor): ?>
                        <input type="hidden"
                            id="multilang_content_<?php echo esc_attr($code); ?>"
                            name="multilang_content_<?php echo esc_attr($code); ?>"
                            value="<?php echo esc_attr($content); ?>">
                        <div class="multilang-metabox-block-editor"
                            data-input-id="multilang_content_<?php echo esc_attr($code); ?>"></div>
                    <?php else: ?>
                        <?php
                        wp_editor(
                            $content,
                            'multilang_content_' . $code,
                            array(
                                'textarea_name' => 'multilang_content_' . $code,
                                'media_buttons' => true,
                                'textarea_rows' => 10,
                                'teeny' => false,
                                'editor_class' => 'multilang-content-preview',
                            )
                        );
                        ?>
                    <?php endif; ?>
                </div>

                <div class="multilang-field-row">
                    <label for="multilang_excerpt_<?php echo esc_attr($code); ?>">
                        <?php esc_html_e('Auszug', 'multilang'); ?>
                    </label>
                    <textarea id="multilang_excerpt_<?php echo esc_attr($code); ?>"
                        name="multilang_excerpt_<?php echo esc_attr($code); ?>" class="widefat"
                        rows="3"><?php echo esc_textarea($excerpt); ?></textarea>
                </div>
            </div>
            <?php $first = false; endforeach; ?>

        <script>
            function multilangSwitchTab(btn) {
                var targetId = btn.getAttribute('data-target');
                document.querySelectorAll('.multilang-tab-btn').forEach(function (b) { b.classList.remove('active'); });
                document.querySelectorAll('.multilang-tab-panel').forEach(function (p) { p.classList.remove('active'); });
                btn.classList.add('active');
                var panel = document.getElementById(targetId);
                if (panel) panel.classList.add('active');
            }
        </script>
        <?php
    }

    public function save_meta_box($post_id, $post)
    {
        // Verify nonce
        if (
            !isset($_POST['multilang_post_nonce'])  // phpcs:ignore
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['multilang_post_nonce'])), 'multilang_post_save')
        ) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $langs = Multilang_Languages::get_instance()->get_codes();
        foreach ($langs as $code) {
            // Title
            $title_key = 'multilang_title_' . $code;
            if (isset($_POST[$title_key])) { // phpcs:ignore
                $val = sanitize_text_field(wp_unslash($_POST[$title_key])); // phpcs:ignore
                if ($val !== '') {
                    update_post_meta($post_id, self::META_TITLE . $code, $val);
                } else {
                    delete_post_meta($post_id, self::META_TITLE . $code);
                }
            }

            // Content — wp_editor POST key
            $content_key = 'multilang_content_' . $code;
            if (isset($_POST[$content_key])) { // phpcs:ignore
                $val = wp_kses_post(wp_unslash($_POST[$content_key])); // phpcs:ignore
                if ($val !== '') {
                    update_post_meta($post_id, self::META_CONTENT . $code, $val);
                } else {
                    delete_post_meta($post_id, self::META_CONTENT . $code);
                }
            }

            // Excerpt
            $excerpt_key = 'multilang_excerpt_' . $code;
            if (isset($_POST[$excerpt_key])) { // phpcs:ignore
                $val = sanitize_textarea_field(wp_unslash($_POST[$excerpt_key])); // phpcs:ignore
                if ($val !== '') {
                    update_post_meta($post_id, self::META_EXCERPT . $code, $val);
                } else {
                    delete_post_meta($post_id, self::META_EXCERPT . $code);
                }
            }
        }
    }

    // ── Block editor sidebar panel ────────────────────────────────────────────

    public function enqueue_block_assets()
    {
        $langs = Multilang_Languages::get_instance()->get_enabled();
        if (empty($langs)) {
            return;
        }

        wp_enqueue_script(
            'multilang-block-editor',
            MULTILANG_URL . 'assets/block-editor.js',
            array('wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-i18n', 'wp-api-fetch', 'wp-block-editor', 'wp-blocks'),
            MULTILANG_VERSION,
            true
        );

        wp_localize_script('multilang-block-editor', 'multilangData', array(
            'languages' => array_values($langs),
            'metaPrefixTitle' => self::META_TITLE,
            'metaPrefixContent' => self::META_CONTENT,
            'metaPrefixExcerpt' => self::META_EXCERPT,
            'nonce' => wp_create_nonce('wp_rest'),
            'labels' => array(
                'panelTitle' => esc_html__('Übersetzungen', 'multilang'),
                'title' => esc_html__('Titel', 'multilang'),
                'content' => esc_html__('Inhalt', 'multilang'),
                'contentHint' => esc_html__('Inhalt bitte unten im Metabox-Bereich pro Sprache mit dem Block Editor bearbeiten.', 'multilang'),
                'excerpt' => esc_html__('Auszug', 'multilang'),
                'notice' => esc_html__('Leer = Fallback auf Standardsprache', 'multilang'),
            ),
        ));

        wp_enqueue_style(
            'multilang-block-editor',
            MULTILANG_URL . 'assets/block-editor.css',
            array(),
            MULTILANG_VERSION
        );
    }

    // ── Frontend filters ──────────────────────────────────────────────────────

    public function filter_title($title, $post_id = null)
    {
        if (!$post_id || is_admin()) {
            return $title;
        }
        $lang = multilang_current();
        $meta = get_post_meta($post_id, self::META_TITLE . $lang, true);
        return ($meta !== '' && $meta !== false) ? esc_html($meta) : $title;
    }

    public function filter_content($content)
    {
        if (is_admin()) {
            return $content;
        }
        $post = get_post();
        if (!$post) {
            return $content;
        }
        $lang = multilang_current();
        $meta = get_post_meta($post->ID, self::META_CONTENT . $lang, true);
        return ($meta !== '' && $meta !== false) ? $meta : $content;
    }

    public function filter_excerpt($excerpt)
    {
        if (is_admin()) {
            return $excerpt;
        }
        $post = get_post();
        if (!$post) {
            return $excerpt;
        }
        $lang = multilang_current();
        $meta = get_post_meta($post->ID, self::META_EXCERPT . $lang, true);
        return ($meta !== '' && $meta !== false) ? esc_html($meta) : $excerpt;
    }

    public function filter_wp_title($title, $sep, $seplocation)
    {
        if (is_admin()) {
            return $title;
        }
        $post = get_post();
        if (!$post) {
            return $title;
        }
        $lang = multilang_current();
        $meta = get_post_meta($post->ID, self::META_TITLE . $lang, true);
        return ($meta !== '' && $meta !== false) ? esc_html($meta) : $title;
    }
}
