<?php
/**
 * Plugin Name: Bubblehouse WooCommerce Integration
 * Description: Provides iframe block for Bubblehouse integration
 * Version: 1.1.0
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

    add_settings_section('bubblehouse_maintenance', 'Maintenance Routines', null, 'bubblehouse');
    add_settings_field('bubblehouse_sync_products', 'Product Sync', 'bubblehouse_sync_products_field', 'bubblehouse', 'bubblehouse_maintenance');

    if (isset($_POST['bubblehouse_manual_sync']) && check_admin_referer('bubblehouse_manual_sync')) {
        bubblehouse_schedule_product_sync_once();
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>Product sync initiated. Check error logs for results.</p></div>';
        });
    }
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

function bubblehouse_sync_products_field() {
    $next_scheduled = wp_next_scheduled('bubblehouse_sync_products');
    $next_run = $next_scheduled ? date('Y-m-d H:i:s', $next_scheduled) : 'Not scheduled';

    echo '<p>Automatic sync runs hourly. Next scheduled: ' . esc_html($next_run) . '</p>';
    /* echo '<form method="post" style="display: inline;">'; */
    wp_nonce_field('bubblehouse_manual_sync');
    echo '<input type="submit" name="bubblehouse_manual_sync" class="button button-secondary" value="Sync Products Now" />';
    /* echo '</form>'; */
}

function bubblehouse_settings_page() {
    ?>
    <div class="wrap">
        <h1>Bubblehouse Settings</h1>
        <form method="post" action="options-general.php?page=bubblehouse">
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

    if (empty($host)) {
        $host = "app.bubblehouse.com";
    }

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

// Product sync hooks
add_action('bubblehouse_sync_products', 'bubblehouse_sync_products_job');
add_filter('cron_schedules', 'bubblehouse_add_cron_interval');

// Schedule product sync on activation
register_activation_hook(__FILE__, 'bubblehouse_schedule_product_sync');
register_deactivation_hook(__FILE__, 'bubblehouse_unschedule_product_sync');

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

function bubblehouse_add_cron_interval($schedules) {
    $schedules['bubblehouse_hourly'] = array(
        'interval' => 3600,
        'display' => 'Every Hour'
    );
    return $schedules;
}

function bubblehouse_schedule_product_sync_once() {
    bubblehouse_schedule_product_sync();
    wp_schedule_single_event(time(), 'bubblehouse_sync_products');
}

function bubblehouse_schedule_product_sync() {
    if (!wp_next_scheduled('bubblehouse_sync_products')) {
        wp_schedule_event(time(), 'bubblehouse_hourly', 'bubblehouse_sync_products');
    }
}

function bubblehouse_unschedule_product_sync() {
    wp_clear_scheduled_hook('bubblehouse_sync_products');
}

function bubblehouse_sync_products_job() {
    $host = get_option('bubblehouse_host', 'app.bubblehouse.com');
    $shop_slug = get_option('bubblehouse_shop_slug', '');
    $kid = get_option('bubblehouse_kid', '');
    $shared_secret = get_option('bubblehouse_shared_secret', '');

    if (empty($host)) {
        $host = "app.bubblehouse.com";
    }

    if (empty($host) || empty($shop_slug) || empty($kid) || empty($shared_secret)) {
        error_log('Bubblehouse: Missing configuration for product sync');
        return;
    }

    error_log('Bubblehouse: Initiating products sync');

    $products = bubblehouse_get_woocommerce_products();
    $collections = bubblehouse_get_woocommerce_collections();

    $payload = array(
        'products' => $products,
        'collections' => $collections,
        'replace_products' => false,
        'replace_collections' => false,
        'debug' => false
    );

    $auth_token = bubblehouse_generate_jwt($shop_slug, $kid, $shared_secret, 3600);
    $api_url = "https://{$host}/api/v2023061/$shop_slug/UpdateProducts3";

    $response = wp_remote_post($api_url, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $auth_token
        ),
        'body' => json_encode($payload),
        'timeout' => 60
    ));

    if (is_wp_error($response)) {
        error_log('Bubblehouse: Product sync failed - ' . $response->get_error_message());
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 200) {
            error_log('Bubblehouse: Product sync completed successfully');
        } else {
            error_log('Bubblehouse: Product sync failed with status ' . $response_code);
        }
    }
}

function bubblehouse_format_monetary($amount) {
    return $amount ? number_format((float)$amount, 6, '.', '') : '0.000000';
}

function bubblehouse_format_datetime($datetime) {
    return $datetime ? $datetime->format('c') : null;
}

function bubblehouse_get_woocommerce_products() {
    $products = array();

    $wc_products = wc_get_products(array(
        'limit' => -1,
        'status' => array('publish', 'private', 'draft')
    ));

    foreach ($wc_products as $wc_product) {
        $product_data = array(
            'id' => (string)$wc_product->get_id(),
            'title' => $wc_product->get_name(),
            'slug' => $wc_product->get_slug(),
            'inactive' => $wc_product->get_status() !== 'publish',
            'tags' => array_map('strval', wp_get_post_terms($wc_product->get_id(), 'product_tag', array('fields' => 'names'))),
            'created_at' => bubblehouse_format_datetime($wc_product->get_date_created()),
            'updated_at' => bubblehouse_format_datetime($wc_product->get_date_modified()),
            'deleted' => false
        );

        $image_id = $wc_product->get_image_id();
        if ($image_id) {
            $product_data['some_image_url'] = wp_get_attachment_url($image_id);
        }

        $category_ids = $wc_product->get_category_ids();
        if (!empty($category_ids)) {
            $product_data['collection_ids'] = array_map('strval', $category_ids);
        }

        $variants = array();
        if ($wc_product->is_type('variable')) {
            $variation_ids = $wc_product->get_children();
            foreach ($variation_ids as $variation_id) {
                $variation = wc_get_product($variation_id);
                if ($variation) {
                    $variants[] = array(
                        'id' => (string)$variation->get_id(),
                        'title' => $variation->get_name(),
                        'price' => bubblehouse_format_monetary($variation->get_price()),
                        'price_known' => !empty($variation->get_price()),
                        'deleted' => false
                    );
                }
            }
        } else {
            $variants[] = array(
                'id' => (string)$wc_product->get_id(),
                'title' => $wc_product->get_name(),
                'price' => bubblehouse_format_monetary($wc_product->get_price()),
                'price_known' => !empty($wc_product->get_price()),
                'deleted' => false
            );
        }

        $product_data['variants'] = $variants;
        $products[] = $product_data;
    }

    return $products;
}

function bubblehouse_get_woocommerce_collections() {
    $collections = array();

    $categories = get_terms(array(
        'taxonomy' => 'product_cat',
        'hide_empty' => false
    ));

    if (!is_wp_error($categories)) {
        foreach ($categories as $category) {
            $collection_data = array(
                'id' => (string)$category->term_id,
                'title' => $category->name,
                'slug' => $category->slug,
                'deleted' => false
            );

            $thumbnail_id = get_term_meta($category->term_id, 'thumbnail_id', true);
            if ($thumbnail_id) {
                $collection_data['some_image_url'] = wp_get_attachment_url($thumbnail_id);
            }

            $product_ids = get_posts(array(
                'post_type' => 'product',
                'numberposts' => -1,
                'post_status' => array('publish', 'private', 'draft'),
                'fields' => 'ids',
                'tax_query' => array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'term_id',
                        'terms' => $category->term_id
                    )
                )
            ));

            if (!empty($product_ids)) {
                $collection_data['product_ids'] = array_map('strval', $product_ids);
            }

            $collections[] = $collection_data;
        }
    }

    return $collections;
}
