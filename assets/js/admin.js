/**
 * Synced Pattern Popups Admin JavaScript
 * Handles copy-to-clipboard functionality
 */
(function() {
	'use strict';

	// Check if localized data is available
	if (typeof sppopupsAdmin === 'undefined') {
		return;
	}

	/**
	 * Copy text to clipboard
	 *
	 * @param {string} text Text to copy
	 * @return {Promise} Promise that resolves when copy is complete
	 */
	function copyToClipboard(text) {
		if (navigator.clipboard && window.isSecureContext) {
			// Use modern Clipboard API
			return navigator.clipboard.writeText(text);
		} else {
			// Fallback for older browsers
			return new Promise(function(resolve, reject) {
				var textArea = document.createElement('textarea');
				textArea.value = text;
				textArea.style.position = 'fixed';
				textArea.style.left = '-999999px';
				textArea.style.top = '-999999px';
				document.body.appendChild(textArea);
				textArea.focus();
				textArea.select();

				try {
					var successful = document.execCommand('copy');
					if (successful) {
						resolve();
					} else {
						reject(new Error('Copy command failed'));
					}
				} catch (err) {
					reject(err);
				} finally {
					document.body.removeChild(textArea);
				}
			});
		}
	}

	/**
	 * Handle copy button click
	 *
	 * @param {Event} event Click event
	 */
	function handleCopyClick(event) {
		event.preventDefault();
		event.stopPropagation();

		var button = event.currentTarget;
		var textToCopy = button.getAttribute('data-copy');

		if (!textToCopy) {
			return;
		}

		copyToClipboard(textToCopy)
			.then(function() {
				// Show success feedback
				button.classList.add('copied');
				var originalTitle = button.getAttribute('title') || button.getAttribute('aria-label') || '';

				// Update button state
				if (button.querySelector('.dashicons')) {
					button.setAttribute('title', sppopupsAdmin.strings.copied);
					button.setAttribute('aria-label', sppopupsAdmin.strings.copied);
				}

				// Reset after 2 seconds
				setTimeout(function() {
					button.classList.remove('copied');
					if (originalTitle) {
						button.setAttribute('title', originalTitle);
						button.setAttribute('aria-label', originalTitle);
					}
				}, 2000);
			})
			.catch(function(error) {
				console.error('Failed to copy:', error);
				alert(sppopupsAdmin.strings.copyFailed);
			});
	}

	// Initialize copy functionality when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	function init() {
		// Find all copy trigger buttons
		var copyButtons = document.querySelectorAll('.copy-trigger');

		// Add click handlers
		copyButtons.forEach(function(button) {
			button.addEventListener('click', handleCopyClick);
		});
	}
})();

