=== Synced Pattern Popups ===
Contributors: webdevmattcrom
Tags: popup, modal, synced-patterns, ai, tldr
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 1.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Trigger a sleek modal popup from any WordPress Synced Pattern with a class name or "spp-trigger-{id}".

== Description ==

Synced Pattern Popups is a lightweight, developer-friendly WordPress plugin that displays beautiful modal popups with content from WordPress Synced Patterns. No build process required - everything works out of the box.

**Perfect for:**
* Marketing campaigns and promotional popups
* Terms of service and privacy policy modals
* Product details and specifications
* Image galleries and media showcases
* Contact forms and newsletter signups
* Any content you want to display in a modal

**Key Benefits:**

* **Easy Content Management**: Create popup content using WordPress's native Block Editor - no coding required
* **Simple Trigger System**: Add a class or href attribute to any element to open a popup
* **Performance Optimized**: Intelligent caching and lazy loading ensure fast page loads
* **Mobile Friendly**: Responsive design that works perfectly on all devices
* **Accessibility First**: Full keyboard navigation, screen reader support, and ARIA attributes
* **AI-Powered TLDR**: Generate AI summaries of your content with a single click (requires AI Experiments plugin)
* **Developer Friendly**: Clean code, WordPress hooks, and extensible architecture

= What are Synced Patterns? =

Synced Patterns (stored as `wp_block` posts) are reusable block layouts you can create and manage once, then reuse across your site. When you edit a synced pattern, any place it’s used is updated automatically.

This plugin surfaces them with a dedicated admin screen at **Appearance → Synced Patterns**, making it easy to:
- See each pattern's ID at a glance
- Copy the trigger code
- Edit or delete the pattern
- Clear the cached popup output when needed

= Major Features =

= Modal popups powered by Synced Patterns =
Create your popup content using the native Block Editor - layouts, images, galleries, buttons, and third-party blocks all work.

= Simple trigger system (class or href) =
Trigger popups with either:
- `class="spp-trigger-{id}"`
- `href="#spp-trigger-{id}"` (handy in the Block Editor)

You can also set a custom modal width with: `spp-trigger-{id}-{width}`.

= On-demand loading (AJAX) =
Popup content loads only when needed, which keeps initial page loads fast.

= Smart caching with automatic invalidation =
Rendered popup output is cached using WordPress transients for performance, and automatically cleared when synced patterns change. You can also clear a single pattern’s cache from the admin UI.

= Full block styling support (core + third‑party) =
The plugin loads the styles required to render the synced pattern correctly inside the modal - including WordPress core block styles and supported third-party block styles.

= Accessibility-first modal behavior =
Includes keyboard navigation (Escape to close, Tab stays within the popup), focus management, and screen-reader friendly UI.

= AI-powered TLDR (optional) =
Generate AI summaries of the current page content on demand by adding a `spp-trigger-tldr` trigger. This feature requires the **AI Experiments** plugin and configured credentials (you’ll see guided setup in the TLDR tab).

= Abilities API integration (WordPress 6.9+) =
On WordPress 6.9+, the plugin registers Abilities to enable programmatic use cases (e.g., rendering popup content via `wp_execute_ability()` in custom workflows). If the Abilities API isn’t available, the plugin simply skips this integration.

= How It Works =

1. Create a Synced Pattern in WordPress (Appearance → Synced Patterns)
2. Add a trigger to any element: `class="spp-trigger-123"` or `href="#spp-trigger-123"`
3. When clicked, the popup opens with your pattern content
4. Content loads via AJAX for optimal performance

= No Configuration Needed =

The plugin works immediately after activation. Simply create a synced pattern, note its ID, and add a trigger to any element on your site.

== Installation ==

1. Upload the `sppopups` folder to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Appearance → Synced Patterns to manage your popup content
4. Create a synced pattern and note its ID
5. Add `class="spp-trigger-{id}"` or `href="#spp-trigger-{id}"` to any element
6. That's it! Your popup is ready.

== Frequently Asked Questions ==

= How do I trigger a popup? =

You can trigger a popup in two ways:

**Method 1: Class Name** (Recommended for custom HTML)
Add the class `spp-trigger-{id}` to any clickable element, where `{id}` is the numeric ID of your Synced Pattern.

Example:
`<a href="#" class="spp-trigger-1359">Open Popup</a>`
`<button class="spp-trigger-1359">Click Me</button>`

**Method 2: Href Attribute** (Perfect for Block Editor)
Set the `href` attribute to `#spp-trigger-{id}` on any link element. This is especially useful in the WordPress Block Editor where you can't easily add custom classes.

Example:
`<a href="#spp-trigger-1359">Open Popup</a>`

= Where do I find the pattern ID? =

Go to WordPress Admin → Appearance → Synced Patterns. The ID column shows the pattern ID prominently. You can also click the "Copy Trigger" button in the Actions column to copy the complete trigger code.

= Can I use multiple popups on one page? =

Yes! You can have multiple different popups on the same page - just use different pattern IDs. Mix and match both trigger methods as needed.

= Can I customize the modal width? =

Yes! Add a width suffix to your trigger: `spp-trigger-{id}-{width}` where width is in pixels (100-5000px).

Example:
`<a href="#" class="spp-trigger-1359-800">Open 800px Modal</a>`

= What is the TLDR feature? =

The TLDR (Too Long; Didn't Read) feature generates AI-powered summaries of your page content. Simply add `class="spp-trigger-tldr"` or `href="#spp-trigger-tldr"` to any element, and when clicked, it will generate a concise summary of the current page.

**Requirements:**
* WordPress AI Experiments plugin must be installed and activated
* AI credentials must be configured in Settings → AI Experiments

The TLDR feature can be enabled/configured in Appearance → Synced Patterns → AI TLDR Settings.

= Why isn't my popup opening? =

Check these common issues:

1. **Verify the trigger format**: Use exactly `spp-trigger-{id}` (class) or `#spp-trigger-{id}` (href) where `{id}` is numeric
2. **Check pattern ID**: Verify the pattern ID exists in Appearance → Synced Patterns
3. **Pattern status**: Ensure the pattern is published (not draft)
4. **Sync status**: Only synced patterns work - unsynced patterns are excluded
5. **Toggle Forced On**: In your post or page, there's a setting called "Synced Pattern Popup Support". Toggle this on if your class or link is output on the page dynamically, like via a form or AJAX.
6. **Browser console**: Check for JavaScript errors in browser developer tools. 

Try to answer as many of those questions as you can when [submitting a support ticket](https://wordpress.org/support/plugin/synced-pattern-popups/). 

= Why isn't my content loading? =

Troubleshooting steps:

1. **Verify pattern ID**: Double-check the ID in Appearance → Synced Patterns
2. **Check pattern status**: Pattern must be published
3. **Verify sync status**: Only synced patterns can be used
4. **Network tab**: Check browser Network tab for AJAX errors
5. **Clear cache**: Try clearing the transient cache (button in admin interface)

= Can I use this with page builders? =

Yes! The plugin works with most page builders. For dynamically loaded content (like Gravity Forms or AJAX-loaded sections), you may need to enable "Forced On" in the post meta box (Synced Pattern Popup Support) to ensure assets load.

= Does this work with caching plugins? =

Yes! The plugin uses WordPress transients for caching, which works with all major caching plugins. Cache is automatically invalidated when patterns are updated.

= Is this accessible? =

Yes! The plugin includes:
* Full keyboard navigation (Escape to close, Tab navigation)
* Screen reader support with ARIA attributes
* Focus management (returns focus to trigger element on close)
* High contrast support

= Can I customize the modal styles? =

Yes! The plugin uses minimal CSS. You can override styles in your theme's CSS using the same class names. The modal uses these main classes:
* `.sppopups-modal` - Main modal container
* `.sppopups-overlay` - Background overlay
* `.sppopups-container` - Content container
* `.sppopups-content` - Content area

= What WordPress version is required? =

WordPress 5.8 or higher. The plugin is tested up to WordPress 6.9.

= What PHP version is required? =

PHP 7.4 or higher.

== Screenshots ==

1. An example Synced Pattern Popup using a 3rd party block: KadenceWP Blocks
2. The Synced Patterns Popup settings screen listing your sites Synced Patterns. From here you can copy the trigger code, edit the pattern, delete the pattern, or clear the pattern transient cache.
3. The TLDR settings panel, showing it enabled (because AI Experiments is enabled and configured correctly). Here you can customize your prompt and your TLDR cache length.

== Changelog ==

= 1.1.3 =
* New: Added delayed admin review notice to encourage user reviews (appears after 10 days on settings page)
* New: Custom styled review notice with dismiss functionality (AJAX-based)
* New: Settings link added to plugin action links for quick access
* Improved: Admin UI consistency with unified 960px max-width constraint for all settings content
* Improved: Review notice styling matches modern WordPress design patterns

= 1.1.2 =
* Fixed: Added translators comment for sprintf with placeholders (Plugin Check compliance)
* Fixed: Added proper nonce verification documentation for GET parameter handling
* Improved: Added load_plugin_textdomain() for proper internationalization support
* Improved: Updated German translation file (de_DE) with all current strings
* Improved: Removed duplicate POT files for cleaner translation management
* Improved: Enhanced internationalization support across all plugin strings

= 1.1.1 =
* Fixed: Block styles now properly load for all block types including core blocks and third-party blocks (Kadence, Genesis Blocks, etc.)
* Fixed: Improved asset collection to ensure all necessary styles are loaded in modal popups
* Fixed: Corrected rendering order to apply `the_content` filters before `do_blocks()` for proper block asset enqueuing
* Fixed: Removed all debugging instrumentation code
* Improved: Better handling of `style-blocks-*.css` files for third-party block libraries
* Improved: Enhanced dependency collection for block styles to ensure all required styles are included

= 1.1.0 =
* New: Tabbed admin interface with Patterns, TLDR, and How to Use tabs
* New: URL hash navigation for direct linking to specific admin tabs
* New: Clipboard icon button in trigger code column for quick copying
* New: Individual pattern transient deletion ("Delete Transient #{id}" button)
* New: "Learn more" link in admin header for quick access to documentation
* Improved: Admin UI styling with modern tabbed interface matching WordPress design patterns
* Improved: Better accessibility with ARIA attributes and keyboard navigation for tabs
* Improved: Consistent styling across all admin tabs with proper max-width and spacing

= 1.0.1 =
* Hotfix: Updated text domain to match plugin slug (synced-pattern-popups) for WordPress.org compliance
* Fixed: Removed debug logging code that used ABSPATH directly
* Fixed: Updated all file path references to use WordPress standard functions
* Updated: Console messages now use consistent "Synced Pattern Popups" branding

= 1.0.0 =
* Initial release
* Synced Pattern popup system with class and href triggers
* Admin interface for managing patterns
* Intelligent caching system
* Asset collection for block styles and scripts
* AI-powered TLDR feature (requires AI Experiments plugin)
* Abilities API support for WordPress 6.9+
* Accessibility features (keyboard navigation, ARIA attributes)
* Custom width support via trigger suffix
* Rate limiting for AJAX requests
* Per-post popup support toggle
* Full translation support

== Upgrade Notice ==

= 1.1.3 =
Admin UI improvements and review notice feature. Settings page now has consistent width constraints, and a friendly review notice will appear after 10 days to encourage user feedback. All existing functionality remains unchanged.

= 1.1.2 =
Internationalization and code quality improvements. Enhanced translation support with proper textdomain loading and updated German translations. All Plugin Check compliance issues resolved. Recommended for all users.

= 1.1.1 =
Bug fix release addressing block style loading issues. All block styles (core and third-party) now properly load in modal popups. Recommended for all users.

= 1.1.0 =
Major admin interface update with tabbed navigation, improved usability, and individual pattern cache management. The admin interface now features a modern tabbed design with direct links to Patterns, TLDR settings, and usage instructions.

= 1.0.1 =
Hotfix release addressing WordPress.org Plugin Directory submission requirements. No user-facing changes.

= 1.0.0 =
Initial release of Synced Pattern Popups. Activate and start creating popups immediately - no configuration required.

== Tips for Developers ==

**Custom Cache TTL:**
Add this to your theme's `functions.php`:

`add_filter( 'sppopups_cache_ttl', function( $ttl ) { return 6 * HOUR_IN_SECONDS; });`

**Force Assets on Specific Pages:**
For dynamic content that loads via AJAX, you can force assets to load:

`add_filter( 'sppopups_force_assets', function( $force, $post_id ) { if ( $post_id === 123 ) { return true; } return $force; }, 10, 2 );`

**Using Abilities API (WordPress 6.9+):**
Render popup content programmatically:

`$result = wp_execute_ability( 'sppopups/render-popup', array( 'pattern_id' => 123 ) );`

**Hooks Available:**
* `sppopups_cache_ttl` - Filter cache TTL duration
* `sppopups_force_assets` - Force assets to load on specific posts
* `sppopups_pattern_content` - Filter pattern content before rendering
* `sppopups_modal_output` - Filter modal HTML output

== Support ==

For support, feature requests, or bug reports, please visit the plugin's support page or GitHub repository.
