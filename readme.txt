=== Synced Pattern Popups ===
Contributors: wpproducttalk
Tags: popup, modal, synced-patterns, reusable-blocks
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight modal popup system that loads WordPress Synced Pattern content on demand. Trigger with class "spp-trigger-{id}".

== Description ==

A lightweight WordPress plugin that displays modal popups with content from WordPress Synced Patterns. No build process required - everything works out of the box.

== Installation ==

1. Upload the `simplest-popup` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. That's it! No configuration needed.

== Frequently Asked Questions ==

= How do I trigger a popup? =

You can trigger a popup in two ways:

1. Add the class `spp-trigger-{id}` to any clickable element, where `{id}` is the numeric ID of your Synced Pattern.
2. Set the `href` attribute to `#spp-trigger-{id}` on any link element.

= Where do I find the pattern ID? =

Go to WordPress Admin → Appearance → Synced Patterns. The ID column shows the pattern ID prominently.

= Can I use multiple popups on one page? =

Yes! You can have multiple different popups on the same page - just use different pattern IDs.

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release of The Simplest of Popups.

