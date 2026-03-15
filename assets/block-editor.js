/**
 * Multilang Block Editor Sidebar Panel
 *
 * Adds a "Übersetzungen" panel in the Gutenberg Document sidebar with
 * per-language Title, Content, and Excerpt fields backed by post meta.
 *
 * Dependencies (registered server-side):
 *   wp-plugins, wp-edit-post, wp-element, wp-components,
 *   wp-data, wp-core-data, wp-i18n
 */
(function (wp, multilangData) {
    'use strict';

    if (!wp || !multilangData || !wp.plugins || !wp.data || !wp.components || !wp.element || !wp.editPost || !wp.blockEditor || !wp.blocks) {
        return;
    }

    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var useEffect = wp.element.useEffect;
    var useRef = wp.element.useRef;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var TabPanel = wp.components.TabPanel;
    var Notice = wp.components.Notice;
    var Popover = wp.components.Popover;

    var BlockEditorProvider = wp.blockEditor.BlockEditorProvider;
    var BlockList = wp.blockEditor.BlockList;
    var BlockTools = wp.blockEditor.BlockTools;
    var WritingFlow = wp.blockEditor.WritingFlow;
    var ObserveTyping = wp.blockEditor.ObserveTyping;
    var BlockInspector = wp.blockEditor.BlockInspector;
    var InspectorControls = wp.blockEditor.InspectorControls;

    var useSelect = wp.data.useSelect;
    var useDispatch = wp.data.useDispatch;

    var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
    var registerPlugin = wp.plugins.registerPlugin;

    var langs = multilangData.languages || [];
    var labels = multilangData.labels || {};
    var pfxT = multilangData.metaPrefixTitle;
    var pfxC = multilangData.metaPrefixContent;
    var pfxE = multilangData.metaPrefixExcerpt;
    var defaultLang = (langs.find(function (lang) { return !!lang.default; }) || langs[0] || {}).code;

    if (!langs.length || !PluginDocumentSettingPanel) return;

    function hasValue(value) {
        return !!(value && String(value).trim() !== '');
    }

    function MiniBlockEditor(props) {
        var initialBlocks = wp.blocks.parse(props.initialValue || '');
        var state = wp.element.useState(initialBlocks.length ? initialBlocks : [wp.blocks.createBlock('core/paragraph')]);
        var blocks = state[0];
        var setBlocks = state[1];

        function sync(nextBlocks) {
            setBlocks(nextBlocks);
            props.onChange(wp.blocks.serialize(nextBlocks));
        }

        return el(
            BlockEditorProvider,
            {
                value: blocks,
                onInput: sync,
                onChange: sync,
                settings: {
                    hasFixedToolbar: false,
                    focusMode: false,
                },
            },
            el(
                'div',
                { className: 'multilang-mini-editor-layout' },
                el(
                    'div',
                    { className: 'multilang-mini-editor-canvas' },
                    el(
                        BlockTools,
                        null,
                        el(
                            WritingFlow,
                            null,
                            el(
                                ObserveTyping,
                                null,
                                el(BlockList, null)
                            )
                        )
                    )
                ),
                el(
                    'aside',
                    { className: 'multilang-mini-editor-inspector' },
                    BlockInspector
                        ? el(BlockInspector, null)
                        : (
                            InspectorControls && InspectorControls.Slot
                                ? el(InspectorControls.Slot, null)
                                : el('p', { className: 'multilang-mini-editor-inspector-empty' }, 'Block-Einstellungen erscheinen nach Auswahl eines Blocks.')
                        )
                )
            ),
            Popover && Popover.Slot ? el(Popover.Slot, null) : null
        );
    }

    function mountMetaboxEditors() {
        var nodes = document.querySelectorAll('.multilang-metabox-block-editor[data-input-id]');
        if (!nodes.length || !wp.element.render) {
            return;
        }

        nodes.forEach(function (node) {
            if (node.getAttribute('data-mounted') === '1') {
                return;
            }
            var inputId = node.getAttribute('data-input-id');
            var input = inputId ? document.getElementById(inputId) : null;
            if (!input) {
                return;
            }

            wp.element.render(
                el(MiniBlockEditor, {
                    initialValue: input.value || '',
                    onChange: function (value) {
                        input.value = value;
                    },
                }),
                node
            );

            node.setAttribute('data-mounted', '1');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountMetaboxEditors);
    } else {
        mountMetaboxEditors();
    }

    // ── Helper: build the tab list for TabPanel ────────────────────────────
    var tabs = langs.map(function (lang) {
        return {
            name: lang.code,
            title: lang.flag + ' ' + lang.label,
        };
    });

    // ── Sidebar Panel component ────────────────────────────────────────────
    function MultilangPanel() {
        var editorData = useSelect(function (select) {
            var editor = select('core/editor');
            var title = editor.getEditedPostAttribute('title');
            var content = editor.getEditedPostAttribute('content');
            var excerpt = editor.getEditedPostAttribute('excerpt');

            return {
                meta: editor.getEditedPostAttribute('meta') || {},
                title: typeof title === 'string' ? title : (title && title.raw ? title.raw : ''),
                content: typeof content === 'string' ? content : (content && content.raw ? content.raw : ''),
                excerpt: typeof excerpt === 'string' ? excerpt : (excerpt && excerpt.raw ? excerpt.raw : ''),
                isCleanNewPost: editor.isCleanNewPost(),
            };
        });

        var meta = editorData.meta;
        var editPost = useDispatch('core/editor').editPost;
        var didAutoSync = useRef(false);

        function setMeta(key, value) {
            editPost({
                meta: Object.assign({}, meta, {
                    [key]: value,
                }),
            });
        }

        function getStatus(code) {
            var filled = 0;
            if (hasValue(meta[pfxT + code])) filled += 1;
            if (hasValue(meta[pfxC + code])) filled += 1;
            if (hasValue(meta[pfxE + code])) filled += 1;

            if (filled === 3) {
                return { text: 'vollstandig', className: 'is-complete' };
            }
            if (filled > 0) {
                return { text: 'teilweise', className: 'is-partial' };
            }
            return { text: 'leer', className: 'is-empty' };
        }

        // Auto-fill for brand-new posts: prefill empty language fields from current
        // post title/content/excerpt (or from default language meta if already set).
        useEffect(function () {
            if (didAutoSync.current || !editorData.isCleanNewPost || !defaultLang) {
                return;
            }

            var sourceTitle = meta[pfxT + defaultLang] || editorData.title || '';
            var sourceContent = meta[pfxC + defaultLang] || editorData.content || '';
            var sourceExcerpt = meta[pfxE + defaultLang] || editorData.excerpt || '';

            var updates = {};
            langs.forEach(function (lang) {
                var code = lang.code;
                var keyT = pfxT + code;
                var keyC = pfxC + code;
                var keyE = pfxE + code;

                if (!hasValue(meta[keyT]) && hasValue(sourceTitle)) {
                    updates[keyT] = sourceTitle;
                }
                if (!hasValue(meta[keyC]) && hasValue(sourceContent)) {
                    updates[keyC] = sourceContent;
                }
                if (!hasValue(meta[keyE]) && hasValue(sourceExcerpt)) {
                    updates[keyE] = sourceExcerpt;
                }
            });

            didAutoSync.current = true;

            if (Object.keys(updates).length > 0) {
                editPost({
                    meta: Object.assign({}, meta, updates),
                });
            }
        }, [editorData.isCleanNewPost, editorData.title, editorData.content, editorData.excerpt, meta]);

        return el(
            PluginDocumentSettingPanel,
            {
                name: 'multilang-translations',
                title: labels.panelTitle || 'Übersetzungen',
                icon: '🌐',
                className: 'multilang-block-panel',
            },
            el(
                Notice,
                { status: 'info', isDismissible: false, className: 'multilang-notice' },
                labels.notice || 'Leer = Fallback auf Standardsprache'
            ),
            el(
                'div',
                { className: 'multilang-status-list' },
                langs.map(function (lang) {
                    var status = getStatus(lang.code);
                    return el(
                        'div',
                        { key: lang.code, className: 'multilang-status-item' },
                        el('span', { className: 'multilang-status-lang' }, lang.flag + ' ' + lang.label),
                        el('span', { className: 'multilang-status-badge ' + status.className }, status.text)
                    );
                })
            ),
            el(
                TabPanel,
                {
                    className: 'multilang-tab-panel',
                    tabs: tabs,
                },
                function (tab) {
                    var code = tab.name;
                    return el(
                        Fragment,
                        null,
                        el(TextControl, {
                            label: labels.title || 'Titel',
                            value: meta[pfxT + code] || '',
                            onChange: function (val) { setMeta(pfxT + code, val); },
                        }),
                        el('p', { className: 'multilang-content-hint' }, labels.contentHint || 'Inhalt bitte unten im Metabox-Bereich pro Sprache mit dem Block Editor bearbeiten.'),
                        el(TextareaControl, {
                            label: labels.excerpt || 'Auszug',
                            value: meta[pfxE + code] || '',
                            onChange: function (val) { setMeta(pfxE + code, val); },
                            rows: 3,
                        })
                    );
                }
            )
        );
    }

    // ── Register plugin ────────────────────────────────────────────────────
    registerPlugin('multilang-sidebar', {
        render: MultilangPanel,
    });

})(window.wp, window.multilangData);
