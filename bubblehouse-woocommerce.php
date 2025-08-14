<?php
/**
 * Plugin Name: Bubblehouse WooCommerce Integration
 * Description: Provides iframe block for Bubblehouse integration
 * Version: 1.0.0
 * Author: Bubblehouse
 */

if (!defined('ABSPATH')) {
    exit;
}

function bubblehouse_init() {
    if (function_exists('register_block_type')) {
        register_block_type('bubblehouse/iframe', [
            'render_callback' => 'bubblehouse_render_iframe_block',
            'attributes' => [
                'page' => [
                    'type' => 'string',
                    'default' => 'Rewards7'
                ],
                'height' => [
                    'type' => 'number',
                    'default' => 600
                ]
            ]
        ]);
    }

    add_action('enqueue_block_editor_assets', 'bubblehouse_enqueue_block_editor_assets');
}

function bubblehouse_admin_init() {
    add_settings_section('bubblehouse_settings', 'Bubblehouse Settings', null, 'bubblehouse');
    add_settings_field('bubblehouse_host', 'API Host', 'bubblehouse_host_field', 'bubblehouse', 'bubblehouse_settings');
    add_settings_field('bubblehouse_shop_slug', 'Shop Slug', 'bubblehouse_shop_slug_field', 'bubblehouse', 'bubblehouse_settings');
    add_settings_field('bubblehouse_kid', 'KID (Key ID)', 'bubblehouse_kid_field', 'bubblehouse', 'bubblehouse_settings');
    add_settings_field('bubblehouse_shared_secret', 'Shared Secret (Base64)', 'bubblehouse_shared_secret_field', 'bubblehouse', 'bubblehouse_settings');
    register_setting('bubblehouse_settings', 'bubblehouse_host');
    register_setting('bubblehouse_settings', 'bubblehouse_shop_slug');
    register_setting('bubblehouse_settings', 'bubblehouse_kid');
    register_setting('bubblehouse_settings', 'bubblehouse_shared_secret');
}

function bubblehouse_admin_menu() {
    add_options_page('Bubblehouse Settings', 'Bubblehouse', 'manage_options', 'bubblehouse', 'bubblehouse_settings_page');
}

function bubblehouse_host_field() {
    $value = get_option('bubblehouse_host', 'app.bubblehouse.com');
    echo '<input type="text" name="bubblehouse_host" value="' . esc_attr($value) . '" size="50" />';
}

function bubblehouse_shop_slug_field() {
    $value = get_option('bubblehouse_shop_slug', '');
    echo '<input type="text" name="bubblehouse_shop_slug" value="' . esc_attr($value) . '" size="50" />';
}

function bubblehouse_kid_field() {
    $value = get_option('bubblehouse_kid', '');
    echo '<input type="text" name="bubblehouse_kid" value="' . esc_attr($value) . '" size="50" />';
}

function bubblehouse_shared_secret_field() {
    $value = get_option('bubblehouse_shared_secret', '');
    echo '<input type="text" name="bubblehouse_shared_secret" value="' . esc_attr($value) . '" size="50" />';
}

function bubblehouse_settings_page() {
    ?>
    <div class="wrap">
        <h1>Bubblehouse Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('bubblehouse_settings');
            do_settings_sections('bubblehouse');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function bubblehouse_render_iframe_block($attributes) {
    $page = esc_attr($attributes['page'] ?? 'Rewards7');
    $height = intval($attributes['height'] ?? 600);
    $host = get_option('bubblehouse_host', 'app.bubblehouse.com');
    $shop_slug = get_option('bubblehouse_shop_slug', '');
    $kid = get_option('bubblehouse_kid', '');
    $shared_secret = get_option('bubblehouse_shared_secret', '');

    if (empty($host) || empty($shop_slug) || empty($kid) || empty($shared_secret)) {
        return '<div style="padding: 20px; border: 1px solid #ccc; background: #f9f9f9;">Bubblehouse: Missing required configuration (host, shopSlug, kid, sharedSecret)</div>';
    }

    $customer_id = is_user_logged_in() ? get_current_user_id() : null;
    $subject = $customer_id ? "{$shop_slug}/{$customer_id}" : $shop_slug;

    $auth_token = bubblehouse_generate_jwt($subject, $kid, $shared_secret, 3600);

    $iframe_url = "https://{$host}/blocks/v2023061/{$shop_slug}/{$page}?instance=bhpage&auth={$auth_token}";
    $script_url = "https://{$host}/s/{$shop_slug}/bubblehouse.js";

    return sprintf(
        '<iframe id="bhpage" src="%s" sandbox="%s" allow="clipboard-write" style="border: 0; width: 100%%; height: %dpx;"></iframe><script src="%s"></script>',
        esc_url($iframe_url),
        "allow-top-navigation allow-scripts allow-forms allow-modals allow-popups allow-popups-to-escape-sandbox allow-same-origin",
        $height,
        esc_url($script_url)
    );
}

function bubblehouse_generate_jwt($subject, $kid, $shared_secret_base64, $validity_seconds = 3600) {
    $now_unix = time();

    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256', 'kid' => $kid]);

    $payload = json_encode([
        'aud' => 'BH',
        'sub' => $subject,
        'iat' => $now_unix,
        'exp' => $now_unix + $validity_seconds
    ]);

    $base64_url_encode = function($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    };

    $header_encoded = $base64_url_encode($header);
    $payload_encoded = $base64_url_encode($payload);

    $raw = "$header_encoded.$payload_encoded";
    $key = base64_decode($shared_secret_base64);
    $signature = hash_hmac('sha256', $raw, $key, true);

    $signature_encoded = $base64_url_encode($signature);

    return "$raw.$signature_encoded";
}

function bubblehouse_enqueue_block_editor_assets() {
    wp_enqueue_script(
        'bubblehouse-block-editor',
        plugin_dir_url(__FILE__) . 'block-editor.js',
        ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components'],
        '1.0.0'
    );
}

add_action('init', 'bubblehouse_init');
add_action('admin_init', 'bubblehouse_admin_init');
add_action('admin_menu', 'bubblehouse_admin_menu');

// Add inline block editor JS
add_action('admin_footer', function() {
    if (get_current_screen()->id !== 'post' && get_current_screen()->id !== 'page') {
        return;
    }
    ?>
    <script>
    (function(blocks, element, editor, components) {
        var el = element.createElement;
        var TextControl = components.TextControl;
        var InspectorControls = editor.InspectorControls;
        var PanelBody = components.PanelBody;

        blocks.registerBlockType('bubblehouse/iframe', {
            title: 'Bubblehouse Iframe',
            icon: 'embed-generic',
            category: 'embed',
            attributes: {
                page: { type: 'string', default: 'Rewards7' },
                height: { type: 'number', default: 600 }
            },
            edit: function(props) {
                var attributes = props.attributes;
                var setAttributes = props.setAttributes;

                return [
                    el(InspectorControls, {},
                        el(PanelBody, { title: 'Bubblehouse Settings' },
                            el(TextControl, {
                                label: 'Page',
                                value: attributes.page,
                                onChange: function(value) { setAttributes({ page: value }); }
                            }),
                            el(TextControl, {
                                label: 'Height (px)',
                                type: 'number',
                                value: attributes.height,
                                onChange: function(value) { setAttributes({ height: parseInt(value) }); }
                            })
                        )
                    ),
                    el('div', {
                        style: {
                            padding: '20px',
                            border: '1px solid #ccc',
                            background: '#f9f9f9',
                            textAlign: 'center'
                        }
                    }, 'Bubblehouse Block - Configure in sidebar')
                ];
            },
            save: function() {
                return null;
            }
        });
    })(
        window.wp.blocks,
        window.wp.element,
        window.wp.editor || window.wp.blockEditor,
        window.wp.components
    );
    </script>
    <?php
});
