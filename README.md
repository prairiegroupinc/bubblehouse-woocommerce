# BubbleHouse WooCommerce Integration

A minimal WordPress plugin that provides a Gutenberg block for embedding BubbleHouse iframes with JWT authentication.

## Overview

This single-file plugin creates a Gutenberg block that renders secure BubbleHouse iframes. It automatically generates JWT tokens for authentication and supports customer-specific content delivery.

## Features

- Gutenberg block ([[bubblehouse/iframe]]) for easy iframe embedding
- JWT token generation with HS256 algorithm
- Customer ID integration for personalized content
- Configurable iframe height and page selection
- Secure iframe with sandbox attributes
- Automatic BubbleHouse JavaScript loading

## Installation

1. Upload [[bubblehouse-woocommerce.php]] to your [[/wp-content/plugins/]] directory
2. Activate the plugin through the WordPress admin panel
3. Configure the plugin settings at Settings → BubbleHouse

## Configuration

Navigate to **Settings → BubbleHouse** in your WordPress admin to configure the plugin.

### Required Settings

- **Host**: API host (defaults to [[app.bubblehouse.com]])
- **Shop Slug**: Your unique shop identifier from BubbleHouse
- **KID**: Key ID from your BubbleHouse dashboard
- **Shared Secret**: Base64 encoded secret from your BubbleHouse dashboard

### Getting Your Credentials

1. Log into your BubbleHouse dashboard
2. Navigate to the integration settings
3. Copy your KID and Shared Secret values
4. Paste them into the plugin settings

## Usage

### Adding the BubbleHouse Block

1. Edit any page or post in the Gutenberg editor
2. Add a new block and search for "BubbleHouse Iframe"
3. Configure the block settings in the sidebar:
   - **Page**: BubbleHouse page name (default: [[Rewards7]])
   - **Height**: Iframe height in pixels (default: [[600]])

### Block Attributes

- [[page]]: The BubbleHouse page identifier to load
- [[height]]: The iframe height in pixels

## Technical Details

### JWT Token Generation

The plugin generates JWT tokens with the following specifications:

- **Algorithm**: HS256
- **Header**: Contains [[typ]], [[alg]], and [[kid]] fields
- **Payload**: Contains [[aud: "BH"]], [[sub]], [[iat]], and [[exp]] fields
- **Expiration**: 1 hour (3600 seconds)

### Customer Integration

- **Logged-in users**: JWT subject = [[{shop_slug}/{user_id}]]
- **Anonymous users**: JWT subject = [[{shop_slug}]]

### Generated URL Format

```
https://{host}/blocks/v2023061/{shop_slug}/{page}?instance=bhpage&auth={jwt_token}
```

### Security Features

The iframe includes these sandbox attributes for security:
- [[allow-top-navigation]]
- [[allow-scripts]]
- [[allow-forms]]
- [[allow-modals]]
- [[allow-popups]]
- [[allow-popups-to-escape-sandbox]]
- [[allow-same-origin]]

Plus [[allow="clipboard-write"]] for clipboard access.

## File Structure

```
bubblehouse-woocommerce.php    # Main plugin file
README.md                      # Documentation
```

## Key Functions

### Core Functions
- [[bubblehouse_init()]]: Registers the Gutenberg block
- [[bubblehouse_render_iframe_block()]]: Renders the iframe HTML
- [[bubblehouse_generate_jwt()]]: Creates JWT tokens

### Admin Functions
- [[bubblehouse_admin_init()]]: Registers plugin settings
- [[bubblehouse_admin_menu()]]: Adds the admin menu item
- [[bubblehouse_settings_page()]]: Renders the settings page

## Troubleshooting

### Common Issues

**Iframe not loading**
- Verify all plugin settings are configured
- Check that your KID and Shared Secret are correct
- Ensure your Shop Slug matches your BubbleHouse account

**JWT authentication errors**
- Confirm your Shared Secret is properly base64 encoded
- Verify your KID matches what's in your BubbleHouse dashboard
- Check that your server time is synchronized

**Block not appearing in editor**
- Ensure Gutenberg is enabled
- Clear your browser cache
- Verify the plugin is activated

### Error Messages

**"Missing required configuration"**
- Configure all settings in Settings → BubbleHouse
- Make sure no required fields are empty

## Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **Gutenberg**: Block editor must be enabled

## Support

For technical support:
1. Check the BubbleHouse documentation
2. Verify your plugin configuration
3. Contact the BubbleHouse support team

## Changelog

### Version 1.0.0
- Initial release
- Gutenberg block implementation
- JWT token generation
- Admin settings interface
