/**
 * Synced Pattern Popups Admin JavaScript
 * Handles copy-to-clipboard functionality
 */
(function () {
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
			return navigator.clipboard.writeText( text );
		} else {
			// Fallback for older browsers
			return new Promise(
				function (resolve, reject) {
					var textArea            = document.createElement( 'textarea' );
					textArea.value          = text;
					textArea.style.position = 'fixed';
					textArea.style.left     = '-999999px';
					textArea.style.top      = '-999999px';
					document.body.appendChild( textArea );
					textArea.focus();
					textArea.select();

					try {
						var successful = document.execCommand( 'copy' );
						if (successful) {
							resolve();
						} else {
							reject( new Error( 'Copy command failed' ) );
						}
					} catch (err) {
						reject( err );
					} finally {
						document.body.removeChild( textArea );
					}
				}
			);
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

		var button     = event.currentTarget;
		var textToCopy = button.getAttribute( 'data-copy' );

		if ( ! textToCopy) {
			return;
		}

		copyToClipboard( textToCopy )
			.then(
				function () {
					// Show success feedback
					button.classList.add( 'copied' );
					var originalTitle = button.getAttribute( 'title' ) || button.getAttribute( 'aria-label' ) || '';

					// Update button state
					if (button.querySelector( '.dashicons' )) {
						button.setAttribute( 'title', sppopupsAdmin.strings.copied );
						button.setAttribute( 'aria-label', sppopupsAdmin.strings.copied );
					}

					// Reset after 2 seconds
					setTimeout(
						function () {
							button.classList.remove( 'copied' );
							if (originalTitle) {
								button.setAttribute( 'title', originalTitle );
								button.setAttribute( 'aria-label', originalTitle );
							}
						},
						2000
					);
				}
			)
			.catch(
				function (error) {
					console.error( 'Failed to copy:', error );
					alert( sppopupsAdmin.strings.copyFailed );
				}
			);
	}

	// Initialize copy functionality when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	/**
	 * Tab Navigation Handler
	 */
	function initTabs() {
		var tabNavLinks = document.querySelectorAll( '.sppopups-tab-nav-link' );
		var tabContents = document.querySelectorAll( '.sppopups-tab-content' );

		// Function to switch tabs
		function switchTab(tabId) {
			// Remove active class from all tabs and links
			tabNavLinks.forEach(
				function (link) {
					link.classList.remove( 'active' );
					link.setAttribute( 'aria-selected', 'false' );
				}
			);

			tabContents.forEach(
				function (content) {
					content.classList.remove( 'active' );
				}
			);

			// Add active class to selected tab
			var selectedLink    = document.querySelector( '.sppopups-tab-nav-link[href="#' + tabId + '"]' );
			var selectedContent = document.getElementById( 'sppopups-tab-' + tabId );

			if (selectedLink) {
				selectedLink.classList.add( 'active' );
				selectedLink.setAttribute( 'aria-selected', 'true' );
			}

			if (selectedContent) {
				selectedContent.classList.add( 'active' );
			}

			// Update URL hash without triggering scroll
			if (history.pushState) {
				history.pushState( null, null, '#' + tabId );
			} else {
				window.location.hash = '#' + tabId;
			}
		}

		// Handle tab link clicks
		tabNavLinks.forEach(
			function (link) {
				link.addEventListener(
					'click',
					function (e) {
						// Allow external links to open normally
						if (link.hasAttribute( 'target' ) && link.getAttribute( 'target' ) === '_blank') {
							return; // Don't prevent default for external links
						}
						e.preventDefault();
						var href = link.getAttribute( 'href' );
						if (href && href.startsWith( '#' )) {
							var tabId = href.substring( 1 );
							switchTab( tabId );
						}
					}
				);
			}
		);

		// Handle initial hash or default to 'patterns'
		function handleInitialTab() {
			var hash      = window.location.hash.substring( 1 );
			var validTabs = ['patterns', 'tldr', 'how-to-use'];

			if (hash && validTabs.indexOf( hash ) !== -1) {
				switchTab( hash );
			} else {
				// Default to patterns tab
				switchTab( 'patterns' );
			}
		}

		// Handle hash changes (back/forward buttons and direct hash links)
		function handleHashChange() {
			var hash      = window.location.hash.substring( 1 );
			var validTabs = ['patterns', 'tldr', 'how-to-use'];

			if (hash && validTabs.indexOf( hash ) !== -1) {
				switchTab( hash );
			}
		}

		window.addEventListener( 'hashchange', handleHashChange );

		// Handle clicks on any link with a hash that matches a tab
		document.addEventListener(
			'click',
			function (e) {
				var target = e.target.closest( 'a' );
				if (target && target.getAttribute( 'href' ) && target.getAttribute( 'href' ).startsWith( '#' )) {
					var hash      = target.getAttribute( 'href' ).substring( 1 );
					var validTabs = ['patterns', 'tldr', 'how-to-use'];

					if (validTabs.indexOf( hash ) !== -1) {
						// Only prevent default if it's not already a tab nav link (those are handled separately)
						if ( ! target.classList.contains( 'sppopups-tab-nav-link' )) {
							e.preventDefault();
							switchTab( hash );
						}
					}
				}
			}
		);

		// Initialize tab on page load
		handleInitialTab();
	}

	/**
	 * Handle install button click
	 * Shows loading state and disables button during installation
	 *
	 * @param {Event} event Click event
	 */
	function handleInstallClick(event) {
		var button      = event.currentTarget;
		var buttonText  = button.querySelector( '.sppopups-button-text' );
		var loadingDots = button.querySelector( '.sppopups-loading-dots' );

		if ( ! buttonText || ! loadingDots) {
			return;
		}

		// Update button state
		button.classList.add( 'sppopups-installing' );
		button.setAttribute( 'disabled', 'disabled' );
		button.style.pointerEvents = 'none';
		button.style.opacity       = '0.7';
		buttonText.textContent     = 'Installing';
		loadingDots.style.display  = 'inline-block';

		// Allow navigation to proceed
		// The button will remain in loading state until page reloads
	}

	/**
	 * Update scroll indicators for table wrapper
	 * Shows/hides visual indicators when table is scrollable
	 *
	 * @param {HTMLElement} wrapper Table wrapper element
	 */
	function updateScrollIndicators(wrapper) {
		if ( ! wrapper) {
			return;
		}

		var scrollLeft  = wrapper.scrollLeft;
		var scrollWidth = wrapper.scrollWidth;
		var clientWidth = wrapper.clientWidth;
		var maxScroll   = scrollWidth - clientWidth;

		// Update classes based on scroll position
		if (scrollLeft > 0) {
			wrapper.classList.add( 'scrollable-left' );
		} else {
			wrapper.classList.remove( 'scrollable-left' );
		}

		if (scrollLeft < maxScroll - 1) { // -1 for rounding issues
			wrapper.classList.add( 'scrollable-right' );
		} else {
			wrapper.classList.remove( 'scrollable-right' );
		}
	}

	/**
	 * Initialize scroll indicators for responsive tables
	 */
	function initTableScrollIndicators() {
		var tableWrappers = document.querySelectorAll( '.sppopups-table-wrapper' );

		tableWrappers.forEach(
			function (wrapper) {
				// Initial check
				updateScrollIndicators( wrapper );

				// Update on scroll
				wrapper.addEventListener(
					'scroll',
					function () {
						updateScrollIndicators( wrapper );
					}
				);

				// Update on resize
				var resizeObserver = new ResizeObserver(
					function () {
						updateScrollIndicators( wrapper );
					}
				);

				if (window.ResizeObserver) {
					resizeObserver.observe( wrapper );
				} else {
					// Fallback for browsers without ResizeObserver
					window.addEventListener(
						'resize',
						function () {
							updateScrollIndicators( wrapper );
						}
					);
				}
			}
		);
	}

	function init() {
		// Find all copy trigger buttons (both old .copy-trigger and new .sppopups-copy-trigger-icon)
		var copyButtons = document.querySelectorAll( '.copy-trigger, .sppopups-copy-trigger-icon' );

		// Add click handlers
		copyButtons.forEach(
			function (button) {
				button.addEventListener( 'click', handleCopyClick );
			}
		);

		// Handle install button clicks
		var installButtons = document.querySelectorAll( '.sppopups-install-button' );
		installButtons.forEach(
			function (button) {
				button.addEventListener( 'click', handleInstallClick );
			}
		);

		// Initialize tabs
		initTabs();

		// Initialize table scroll indicators
		initTableScrollIndicators();

		// Initialize TLDR prompt type toggle
		initTldrPromptToggle();
	}

	/**
	 * Initialize TLDR prompt type toggle (Default/Custom)
	 */
	function initTldrPromptToggle() {
		var promptTypeRadios = document.querySelectorAll( 'input[name="sppopups_tldr_prompt_type"]' );
		var customWrappers   = document.querySelectorAll( '#sppopups-tldr-prompt-custom-wrapper, #sppopups-tldr-prompt-custom-wrapper-tab' );

		function toggleCustomPrompt() {
			var selectedValue = '';
			promptTypeRadios.forEach(
				function (radio) {
					if (radio.checked) {
						selectedValue = radio.value;
					}
				}
			);

			customWrappers.forEach(
				function (wrapper) {
					if (wrapper) {
						wrapper.style.display = (selectedValue === 'custom') ? 'block' : 'none';
					}
				}
			);
		}

		// Set initial state
		toggleCustomPrompt();

		// Listen for changes
		promptTypeRadios.forEach(
			function (radio) {
				radio.addEventListener( 'change', toggleCustomPrompt );
			}
		);
	}

	/**
	 * Handle review notice dismissal
	 */
	function initReviewNoticeDismiss() {
		var dismissLink = document.querySelector( '.sppopups-review-notice-dismiss-link' );
		if ( ! dismissLink) {
			return;
		}

		var notice = dismissLink.closest( '.sppopups-review-notice' );
		if ( ! notice) {
			return;
		}

		var nonce = notice.getAttribute( 'data-nonce' );
		if ( ! nonce) {
			return;
		}

		dismissLink.addEventListener(
			'click',
			function (event) {
				event.preventDefault();

				// Hide notice immediately for better UX
				notice.style.transition = 'opacity 0.3s ease';
				notice.style.opacity    = '0';

				// Send AJAX request
				var formData = new FormData();
				formData.append( 'action', 'sppopups_dismiss_review_notice' );
				formData.append( 'nonce', nonce );

				var ajaxUrl = (typeof sppopupsAdmin !== 'undefined' && sppopupsAdmin.ajaxUrl) ? sppopupsAdmin.ajaxUrl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
				fetch(
					ajaxUrl,
					{
						method: 'POST',
						body: formData,
						credentials: 'same-origin'
					}
				)
				.then(
					function (response) {
						return response.json();
					}
				)
				.then(
					function (data) {
						if (data.success) {
							// Remove notice from DOM after fade out
							setTimeout(
								function () {
									notice.remove();
								},
								300
							);
						} else {
							// Show notice again on error
							notice.style.opacity = '1';
						}
					}
				)
				.catch(
					function (error) {
						console.error( 'Error dismissing notice:', error );
						// Show notice again on error
						notice.style.opacity = '1';
					}
				);
			}
		);
	}

	// Initialize review notice dismiss functionality
	initReviewNoticeDismiss();
})();
