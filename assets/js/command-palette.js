/**
 * Synced Pattern Popups Command Palette Integration
 * Registers commands for WordPress Command Palette (Ctrl+K / Cmd+K)
 *
 * Features:
 * 1. "popup"/"popups" keywords to navigate to settings
 * 2. "Clear Popup Cache" command when typing patterns/popups
 *
 * @package SPPopups
 */

(function () {
	'use strict';

	// Wait for WordPress dependencies to be available.
	if (
		typeof wp === 'undefined' ||
		!wp.commands ||
		!wp.element ||
		!wp.i18n ||
		!wp.url ||
		!wp.primitives
	) {
		return;
	}

	// Wait for localized data.
	if (typeof sppopupsCommandPalette === 'undefined') {
		return;
	}

	const { useCommand } = wp.commands;
	const { createElement, render } = wp.element;
	const { addQueryArgs } = wp.url;

	// Icon for cache clearing (trash/delete icon).
	const trashIcon = createElement(
		wp.primitives.SVG,
		{
			xmlns: 'http://www.w3.org/2000/svg',
			viewBox: '0 0 24 24',
		},
		createElement(wp.primitives.Path, {
			d: 'M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z',
		})
	);

	/**
	 * React component that registers all command palette commands
	 * All hooks must be called inside this component
	 */
	function SPPopupsCommands() {
		/**
		 * Feature 1: Static command for "popup"/"popups" keywords
		 * Allows users to navigate to settings by typing "popup" or "popups"
		 */
		useCommand({
			name: 'sppopups/go-to-settings',
			label: sppopupsCommandPalette.strings.goToSettings,
			searchLabel: 'popup popups synced pattern popups settings',
			keywords: ['popup', 'popups', 'synced', 'pattern', 'popups', 'modal'],
			callback: function ({ close }) {
				document.location.href = addQueryArgs('themes.php', {
					page: 'simplest-popup-patterns',
				});
				close();
			},
		});

		/**
		 * Feature 2: Static command for "Clear Popup Cache"
		 * Appears when typing "pattern", "patterns", "popup", or "popups"
		 * Only shown to users with manage_options capability
		 */
		if (sppopupsCommandPalette.canManageOptions) {
			useCommand({
				name: 'sppopups/clear-cache',
				label: sppopupsCommandPalette.strings.clearCache,
				searchLabel: 'clear popup cache transient patterns popups',
				keywords: [
					'clear',
					'cache',
					'popup',
					'popups',
					'pattern',
					'patterns',
					'transient',
				],
				icon: trashIcon,
				callback: function ({ close }) {
					// Navigate to clear cache URL with nonce.
					const clearUrl = addQueryArgs('themes.php', {
						page: 'simplest-popup-patterns',
						action: 'clear_cache',
						_wpnonce: sppopupsCommandPalette.clearCacheNonce,
					});
					document.location.href = clearUrl;
					close();
				},
			});
		}

		return null; // Component doesn't render anything
	}

	// Render the component to register commands
	// We need to render this component so hooks are called in the right context
	function initializeCommands() {
		if (!render || !createElement) {
			return;
		}

		// Create a container and render the component
		const container = document.createElement('div');
		container.id = 'sppopups-command-palette-root';
		container.style.display = 'none'; // Hidden, just for React hooks
		document.body.appendChild(container);

		render(createElement(SPPopupsCommands), container);
	}

	// Wait for DOM to be ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initializeCommands);
	} else {
		// DOM already ready, but wait a bit for WordPress to initialize
		setTimeout(initializeCommands, 100);
	}
})();
