# Synced Pattern Popups

A lightweight, developer-friendly WordPress plugin that displays modal popups with content from WordPress Synced Patterns. Built with vanilla JavaScript and PHP, requiring no build process or complex dependencies.

## What This Repo Is All About

This repository contains the **Synced Pattern Popups** plugin - a simple, performant solution for creating modal popups in WordPress. The plugin leverages WordPress's native Synced Patterns (formerly Reusable Blocks) to create popup content, making it easy for content editors to manage popup content without touching code.

The plugin is designed with developers in mind:
- **No build process required** - plain JavaScript and CSS
- **Clean, object-oriented PHP architecture**
- **WordPress coding standards compliant**
- **Fully translatable** with POT file included
- **WordPress.org directory ready** - passes all Plugin Check tests

## Main Features

### Core Functionality
- **Simple Trigger System**: Add `spp-trigger-{id}` class or `#spp-trigger-{id}` href to any element
- **Synced Pattern Integration**: Dynamically loads content from WordPress Synced Patterns via AJAX
- **Admin Interface**: Manage patterns and find IDs easily under Appearance → Synced Patterns
- **Asset Collection**: Automatically collects and injects required CSS/JS from blocks
- **Intelligent Caching**: Transient-based caching with automatic invalidation

### AI-Powered TLDR Feature
- **AI-Generated Summaries**: Click `spp-trigger-tldr` to generate AI-powered page summaries
- **Configurable Prompts**: Customize the AI prompt template in settings
- **Smart Caching**: TLDR results are cached to reduce API calls
- **AI Experiments Integration**: Works seamlessly with the WordPress AI Experiments plugin

### Developer Features
- **Abilities API Support**: WordPress 6.9+ Abilities API integration for machine-readable popup rendering
- **Hooks & Filters**: Extensible architecture with WordPress hooks
- **Rate Limiting**: Built-in rate limiting for AJAX requests
- **Accessibility**: ARIA attributes, keyboard navigation, focus management
- **Security**: Nonce verification, input sanitization, output escaping

### Performance & UX
- **Lazy Loading**: Assets only load when triggers are detected on the page
- **Scroll Position Preservation**: Maintains scroll position when modal opens/closes
- **Responsive Design**: Works on all screen sizes with mobile-optimized interactions
- **Custom Width Support**: Optional max-width specification via `spp-trigger-{id}-{width}`

## How to Install This Plugin Locally for Development

### Prerequisites
- Local WordPress installation (WP-CLI recommended)
- PHP 7.4 or higher
- WordPress 5.8 or higher

### Installation Steps

1. **Clone or download this repository:**
   ```bash
   cd wp-content/plugins
   git clone <repository-url> sppopups
   # or download and extract to wp-content/plugins/sppopups
   ```

2. **Activate the plugin:**
   ```bash
   # Via WP-CLI
   wp plugin activate sppopups --path=.
   
   # Or via WordPress Admin → Plugins
   ```

3. **Verify installation:**
   - Check that "Synced Patterns" appears under Appearance menu
   - Create a test synced pattern
   - Add a trigger to a page/post and test

### Development Setup

The plugin requires no build process. Simply edit files and refresh:

- **PHP files**: Edit in `includes/` directory
- **JavaScript**: Edit `assets/js/modal.js` or `assets/js/admin.js`
- **CSS**: Edit `assets/css/modal.css` or `assets/css/admin.css`

### Running Plugin Check

Before submitting to WordPress.org, always run Plugin Check:

```bash
wp plugin check sppopups --path=.
```

## Requirements and Dependencies

### Required
- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher

### Optional Dependencies

#### AI Experiments Plugin (for TLDR feature)
- **Plugin**: [WordPress AI Experiments](https://github.com/WordPress/ai-experiments)
- **Purpose**: Enables AI-powered TLDR generation
- **Graceful Degradation**: TLDR feature is disabled if plugin is not active

### WordPress Core Dependencies
- Synced Patterns (wp_block post type) - Core WordPress feature
- WordPress REST API - For AJAX endpoints
- WordPress Transients API - For caching

### Browser Support
- Modern browsers with ES5 JavaScript support
- CSS3 support (animations, backdrop-filter)
- Fetch API support

## Tips for Developers

### Extending the Plugin

#### Custom Cache TTL
```php
add_filter( 'sppopups_cache_ttl', function( $ttl ) {
    return 6 * HOUR_IN_SECONDS; // 6 hours instead of 12
});
```

#### Custom Modal Width
Use the width suffix in your trigger:
```html
<a href="#" class="spp-trigger-123-800">Open 800px Modal</a>
```

#### Force Assets on Specific Pages
The plugin automatically detects triggers, but for dynamic content:
```php
// In your theme's functions.php
add_filter( 'sppopups_force_assets', function( $force, $post_id ) {
    if ( $post_id === 123 ) {
        return true; // Force assets on post ID 123
    }
    return $force;
}, 10, 2 );
```

#### Using Abilities API (WordPress 6.9+)
```php
// Render popup content programmatically
$result = wp_execute_ability( 'sppopups/render-popup', array(
    'pattern_id' => 123
) );
```

### Code Structure

```
sppopups/
├── sppopups.php                    # Main plugin file
├── includes/
│   ├── class-sppopups-plugin.php   # Main plugin class
│   ├── class-sppopups-ajax.php     # AJAX handlers
│   ├── class-sppopups-cache.php    # Cache service
│   ├── class-sppopups-pattern.php  # Pattern retrieval
│   ├── class-sppopups-admin.php    # Admin interface
│   ├── class-sppopups-asset-collector.php  # Asset collection
│   ├── class-sppopups-trigger-parser.php   # Trigger parsing
│   ├── class-sppopups-abilities.php       # Abilities API
│   ├── class-sppopups-settings.php        # Settings management
│   └── class-sppopups-tldr.php            # TLDR service
├── assets/
│   ├── css/                        # Stylesheets
│   ├── js/                         # JavaScript
│   └── img/                        # Screenshots
└── languages/                      # Translation files
```

### Coding Standards
- Follows [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- JavaScript uses ES5 (no transpilation needed)
- All strings are translatable
- Security: nonces, sanitization, escaping throughout

### Testing
- Run Plugin Check before commits
- Test on multiple WordPress versions (5.8+)
- Test with and without AI Experiments plugin
- Verify accessibility with screen readers

## How to Contribute to This Repo

### Reporting Issues
1. Check existing issues first
2. Create a new issue with:
   - WordPress version
   - PHP version
   - Steps to reproduce
   - Expected vs actual behavior

### Submitting Pull Requests
1. **Fork the repository**
2. **Create a feature branch:**
   ```bash
   git checkout -b feature/your-feature-name
   ```

3. **Make your changes:**
   - Follow WordPress coding standards
   - Add/update translations if adding strings
   - Update documentation as needed
   - Run Plugin Check before committing

4. **Test thoroughly:**
   - Test on multiple WordPress versions
   - Test with different themes
   - Verify no console errors
   - Check accessibility

5. **Commit with clear messages:**
   ```bash
   git commit -m "Add feature: description of changes"
   ```

6. **Push and create PR:**
   ```bash
   git push origin feature/your-feature-name
   ```

### Translation Contributions
- Translation files are in `languages/` directory
- Use WP-CLI to generate/update POT file:
  ```bash
  wp i18n make-pot . languages/sppopups.pot --path=.
  ```

### Code Review Process
- All PRs require review
- Must pass Plugin Check
- Must follow WordPress coding standards
- Documentation updates welcome

## License

GPL v2 or later

## Support

For issues, questions, or contributions, please use the GitHub Issues page.
