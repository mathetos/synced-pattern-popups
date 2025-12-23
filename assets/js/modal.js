/**
 * Synced Pattern Popups Modal JavaScript
 * No build process - plain vanilla JavaScript
 */
(function() {
	'use strict';

	// Get localized data from WordPress
	if (typeof sppopups === 'undefined') {
		console.error('Synced Pattern Popups: Localized data not found');
		return;
	}

	var modal = document.getElementById('sppopups-modal');
	if (!modal) {
		return;
	}

	var overlay = modal.querySelector('.sppopups-overlay');
	var closeBtn = modal.querySelector('.sppopups-close');
	var closeFooterBtn = modal.querySelector('.sppopups-close-footer');
	var content = modal.querySelector('.sppopups-content');
	var container = modal.querySelector('.sppopups-container');
	var body = document.body;

	if (!overlay || !closeBtn || !content || !container) {
		return;
	}

	var loadingHtml = '<div class="sppopups-loading"><div class="sppopups-spinner"></div><p>' + sppopups.strings.loading + '</p></div>';
	
	// Store current max-width setting for resize handling
	var currentMaxWidth = null;
	
	// Store last active element for focus restoration
	var lastActiveElement = null;
	
	// Store array of background elements that were hidden from AT
	var hiddenBackgroundElements = [];
	
	// Store scroll position to restore after modal closes
	var savedScrollPosition = 0;
	
	// Track which styles have been loaded to prevent duplicates
	var loadedStyles = new Set();
	
	// Track which scripts have been loaded to prevent duplicates
	var loadedScripts = new Set();

	/**
	 * Validate URL is safe for injection (same origin or relative)
	 * Prevents XSS via malicious script/style URLs
	 *
	 * @param {string} url URL to validate
	 * @return {boolean} True if URL is safe, false otherwise
	 */
	function isValidUrl(url) {
		if (!url || typeof url !== 'string') {
			return false;
		}

		// Allow relative URLs (same origin)
		if (url.indexOf('//') === -1 || url.indexOf('//') === 0) {
			// Relative URL or protocol-relative URL (same origin)
			return true;
		}

		// For absolute URLs, verify they're from the same origin
		try {
			var urlObj = new URL(url, window.location.href);
			var currentOrigin = window.location.origin;
			
			// Allow same origin
			if (urlObj.origin === currentOrigin) {
				return true;
			}

			// Reject external URLs (security risk)
			return false;
		} catch (e) {
			// Invalid URL format
			return false;
		}
	}

	/**
	 * Extract pattern ID and optional max-width from trigger string
	 * Unified function that handles both class names and href attributes
	 *
	 * @param {string} triggerString Class name or href attribute value
	 * @param {boolean} isHref Whether the string is an href (starts with #)
	 * @return {object|null} Object with id and maxWidth, or null if not found
	 */
	function extractPatternData(triggerString, isHref) {
		if (!triggerString) {
			return null;
		}

		var pattern;
		if (isHref) {
			// Match pattern: #wppt-popup-{id} or #wppt-popup-{id}-{width}
			pattern = /^#wppt-popup-(\d+)(?:-(\d+))?$/;
		} else {
			// Match pattern: wppt-popup-{id} or wppt-popup-{id}-{width}
			pattern = /^wppt-popup-(\d+)(?:-(\d+))?$/;
		}

		var match = triggerString.match(pattern);
		if (match && match[1]) {
			var id = parseInt(match[1], 10);
			var maxWidth = match[2] ? parseInt(match[2], 10) : null;
			
			// Validate pattern ID range (prevent extremely large IDs)
			if (id > 0 && id <= 2147483647) {
				// Validate max-width range (prevent excessive values)
				if (maxWidth !== null) {
					// Max-width must be between 100px and 5000px (reasonable bounds)
					if (maxWidth < 100 || maxWidth > 5000) {
						maxWidth = null; // Ignore invalid max-width
					}
				}
				
				return {
					id: id,
					maxWidth: maxWidth
				};
			}
		}

		return null;
	}


	/**
	 * Calculate max-width with 6% margin on each side
	 *
	 * @param {number|null} requestedWidth Requested max-width in pixels, or null for default
	 * @return {number} Calculated max-width in pixels
	 */
	function calculateMaxWidth(requestedWidth) {
		var viewportWidth = window.innerWidth;
		var maxViewportWidth = viewportWidth * 0.94; // 6% margin on each side (3% + 3%)
		
		// Default max-width is 600px
		var defaultWidth = 600;
		
		// If no width specified, use default (but still respect 6% margin)
		if (!requestedWidth) {
			return Math.min(defaultWidth, maxViewportWidth);
		}
		
		// If width is specified, use it but ensure 6% margin is maintained
		return Math.min(requestedWidth, maxViewportWidth);
	}

	/**
	 * Focus an element without causing the page to scroll
	 *
	 * @param {HTMLElement} element Element to focus
	 */
	function focusWithoutScroll(element) {
		if (!element) {
			return;
		}
		
		// Try modern preventScroll option first
		if (typeof element.focus === 'function') {
			try {
				element.focus({ preventScroll: true });
				return;
			} catch (e) {
				// Fallback for browsers that don't support preventScroll
			}
		}
		
		// Fallback: save scroll position, focus, then restore
		var currentScroll = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
		element.focus();
		window.scrollTo(0, currentScroll);
	}

	/**
	 * Inject inline CSS as a style tag
	 * Helper function to consolidate duplicate inline CSS injection logic
	 *
	 * @param {string} css CSS content to inject
	 * @param {string} idPrefix Prefix for the style element ID
	 * @param {string} dataAttribute Value for data-simplest-popup attribute
	 * @return {void}
	 */
	function injectInlineStyle(css, idPrefix, dataAttribute) {
		if (!css || typeof css !== 'string' || css.trim().length === 0) {
			return;
		}
		
		var styleId = 'sppopups-' + idPrefix + '-' + Date.now();
		var existingStyle = document.getElementById(styleId);
		if (!existingStyle) {
			var styleTag = document.createElement('style');
			styleTag.id = styleId;
			styleTag.setAttribute('data-simplest-popup', dataAttribute);
			styleTag.textContent = css;
			document.head.appendChild(styleTag);
		}
	}

	/**
	 * Inject stylesheet into document head if not already loaded
	 *
	 * @param {string} handle Style handle
	 * @return {Promise} Promise that resolves when style is loaded
	 */
	function injectStyle(handle) {
		// Check if already loaded
		if (loadedStyles.has(handle)) {
			return Promise.resolve();
		}
		
		// Check if style link already exists in DOM
		var existingLink = document.querySelector('link[data-handle="' + handle + '"]') ||
		                  document.querySelector('link[id="' + handle + '-css"]') ||
		                  document.querySelector('link[href*="' + handle + '"]');
		
		if (existingLink) {
			loadedStyles.add(handle);
			return Promise.resolve();
		}
		
		// Construct style URL
		// WordPress style URLs follow pattern: /wp-content/themes/theme-name/style.css?ver=version
		// For plugins: /wp-content/plugins/plugin-name/assets/style.css?ver=version
		// We'll use a generic approach: try to find the style via wp_styles or construct URL
		var styleUrl = '';
		
		// Try to get URL from wp_styles if available (would need to be localized)
		// For now, we'll construct a basic URL pattern
		// Most block styles are in wp-includes/css/dist/block-library/style.min.css
		// But third-party blocks vary, so we need a more robust solution
		
		// Check if sppopups has styleUrls object
		if (sppopups.styleUrls && sppopups.styleUrls[handle]) {
			styleUrl = sppopups.styleUrls[handle];
		} else {
			// Fallback: try to construct URL from handle
			// This is a best-effort approach
			// WordPress core styles: wp-block-{name} -> /wp-includes/css/dist/block-library/{name}.css
			// Plugin styles: plugin-{name} -> /wp-content/plugins/{plugin}/assets/{name}.css
			// This won't work for all cases, but it's a fallback
			console.warn('Simplest Popup: Style URL not found for handle "' + handle + '". Style may not load correctly.');
			return Promise.resolve(); // Don't break modal if style URL is unknown
		}

		// Validate URL before injection (security check)
		if (!isValidUrl(styleUrl)) {
			console.warn('Simplest Popup: Invalid or unsafe style URL for handle "' + handle + '". Style will not be loaded.');
			return Promise.resolve(); // Don't break modal, just skip unsafe style
		}
		
		// Create link element
		return new Promise(function(resolve, reject) {
			var link = document.createElement('link');
			link.rel = 'stylesheet';
			link.href = styleUrl;
			link.setAttribute('data-handle', handle);
			link.id = handle + '-css';
			
			// Handle load/error
			link.onload = function() {
				loadedStyles.add(handle);
				resolve();
			};
			
			link.onerror = function() {
				console.warn('Simplest Popup: Failed to load style "' + handle + '" from ' + styleUrl);
				// Don't reject - modal should still work
				resolve();
			};
			
			// Append to head
			document.head.appendChild(link);
		});
	}

	/**
	 * Inject multiple stylesheets
	 *
	 * @param {Array} styleHandles Array of style handles
	 * @return {Promise} Promise that resolves when all styles are loaded
	 */
	function injectStyles(styleHandles) {
		if (!styleHandles || !Array.isArray(styleHandles) || styleHandles.length === 0) {
			return Promise.resolve();
		}
		
		// Filter out empty handles
		var validHandles = styleHandles.filter(function(handle) {
			return handle && typeof handle === 'string' && handle.length > 0;
		});
		
		if (validHandles.length === 0) {
			return Promise.resolve();
		}
		
		// Inject all styles in parallel
		var promises = validHandles.map(function(handle) {
			return injectStyle(handle);
		});
		
		return Promise.all(promises);
	}

	/**
	 * Inject script into document head if not already loaded
	 *
	 * @param {string} handle Script handle
	 * @param {string} inlineBefore Optional inline JavaScript to inject before the script
	 * @param {string} inlineAfter Optional inline JavaScript to inject after the script
	 * @return {Promise} Promise that resolves when script is loaded
	 */
	function injectScript(handle, inlineBefore, inlineAfter) {
		// Check if already loaded
		if (loadedScripts.has(handle)) {
			return Promise.resolve();
		}
		
		// Check if script already exists in DOM
		var existingScript = document.querySelector('script[data-handle="' + handle + '"]') ||
		                    document.querySelector('script[id="' + handle + '-js"]') ||
		                    document.querySelector('script[src*="' + handle + '"]');
		
		if (existingScript) {
			loadedScripts.add(handle);
			return Promise.resolve();
		}
		
		// Get script URL
		var scriptUrl = '';
		
		// Check if sppopups has scriptUrls object
		if (sppopups.scriptUrls && sppopups.scriptUrls[handle]) {
			scriptUrl = sppopups.scriptUrls[handle];
		} else {
			// Fallback: try to construct URL from handle
			// This is a best-effort approach
			console.warn('Simplest Popup: Script URL not found for handle "' + handle + '". Script may not load correctly.');
			return Promise.resolve(); // Don't break modal if script URL is unknown
		}

		// Validate URL before injection (security check)
		if (scriptUrl && !isValidUrl(scriptUrl)) {
			console.warn('Simplest Popup: Invalid or unsafe script URL for handle "' + handle + '". Script will not be loaded.');
			return Promise.resolve(); // Don't break modal, just skip unsafe script
		}

		// Create promises array for all script parts
		var promises = [];

		// Inject inline before script if provided
		// Note: Inline scripts are trusted as they come from the server response
		// WordPress core do_blocks() sanitization should handle this, but we rely on server-side security
		if (inlineBefore && typeof inlineBefore === 'string' && inlineBefore.trim().length > 0) {
			var beforeScript = document.createElement('script');
			beforeScript.setAttribute('data-handle', handle + '-before');
			beforeScript.setAttribute('data-simplest-popup', 'inline-before');
			beforeScript.textContent = inlineBefore;
			document.head.appendChild(beforeScript);
		}

		// Inject external script if URL exists
		if (scriptUrl) {
			var scriptPromise = new Promise(function(resolve, reject) {
				var script = document.createElement('script');
				script.src = scriptUrl;
				script.setAttribute('data-handle', handle);
				script.id = handle + '-js';
				
				// Handle load/error
				script.onload = function() {
					loadedScripts.add(handle);
					resolve();
				};
				
				script.onerror = function() {
					console.warn('Simplest Popup: Failed to load script "' + handle + '" from ' + scriptUrl);
					// Don't reject - modal should still work
					resolve();
				};
				
				// Append to head
				document.head.appendChild(script);
			});
			promises.push(scriptPromise);
		}
		
		// Inject inline after script if provided
		if (inlineAfter && typeof inlineAfter === 'string' && inlineAfter.trim().length > 0) {
			var afterScript = document.createElement('script');
			afterScript.setAttribute('data-handle', handle + '-after');
			afterScript.setAttribute('data-simplest-popup', 'inline-after');
			afterScript.textContent = inlineAfter;
			document.head.appendChild(afterScript);
		}
		
		// If no promises (no external script), resolve immediately
		if (promises.length === 0) {
			loadedScripts.add(handle);
			return Promise.resolve();
		}
		
		return Promise.all(promises);
	}

	/**
	 * Inject multiple scripts
	 *
	 * @param {Array} scriptAssets Array of script asset objects with handle, src, inline_before, inline_after
	 * @return {Promise} Promise that resolves when all scripts are loaded
	 */
	function injectScripts(scriptAssets) {
		if (!scriptAssets || !Array.isArray(scriptAssets) || scriptAssets.length === 0) {
			return Promise.resolve();
		}
		
		// Filter out invalid assets
		var validAssets = scriptAssets.filter(function(asset) {
			return asset && asset.handle && typeof asset.handle === 'string' && asset.handle.length > 0;
		});
		
		if (validAssets.length === 0) {
			return Promise.resolve();
		}
		
		// Inject all scripts in parallel
		var promises = validAssets.map(function(asset) {
			return injectScript(
				asset.handle,
				asset.inline_before || '',
				asset.inline_after || ''
			);
		});
		
		return Promise.all(promises);
	}

	/**
	 * Get all focusable elements within the modal
	 *
	 * @return {Array} Array of focusable elements
	 */
	function getFocusableElements() {
		var card = modal.querySelector('.sppopups-card');
		if (!card) {
			return [];
		}
		
		// Selectors for focusable elements
		var selectors = [
			'a[href]',
			'button:not([disabled])',
			'textarea:not([disabled])',
			'input:not([disabled])',
			'select:not([disabled])',
			'[tabindex]:not([tabindex="-1"])'
		].join(', ');
		
		var focusable = Array.prototype.slice.call(card.querySelectorAll(selectors));
		
		// Filter out elements that are not visible
		return focusable.filter(function(el) {
			return el.offsetWidth > 0 && el.offsetHeight > 0;
		});
	}

	/**
	 * Hide background elements from assistive technology
	 */
	function hideBackgroundFromAT() {
		// Clear any previously hidden elements
		hiddenBackgroundElements = [];
		
		// Iterate through all direct children of body
		var bodyChildren = Array.prototype.slice.call(body.children);
		
		bodyChildren.forEach(function(element) {
			// Skip the modal itself
			if (element.id === 'sppopups-modal') {
				return;
			}
			
			// Check if element already has aria-hidden
			var alreadyHidden = element.getAttribute('aria-hidden') === 'true';
			
			// Set inert if supported (HTML5.1+)
			if ('inert' in element) {
				element.inert = true;
			}
			
			// Set aria-hidden as fallback
			if (!alreadyHidden) {
				element.setAttribute('aria-hidden', 'true');
				hiddenBackgroundElements.push(element);
			}
		});
	}

	/**
	 * Show background elements to assistive technology
	 */
	function showBackgroundToAT() {
		// Remove inert from all elements
		hiddenBackgroundElements.forEach(function(element) {
			if ('inert' in element) {
				element.inert = false;
			}
			element.removeAttribute('aria-hidden');
		});
		
		hiddenBackgroundElements = [];
	}

	/**
	 * Open modal and load content
	 *
	 * @param {number} patternId Synced pattern ID
	 * @param {number|null} maxWidth Optional max-width in pixels
	 */
	function openModal(patternId, maxWidth) {
		if (!patternId || !Number.isInteger(Number(patternId)) || patternId <= 0) {
			console.error('Simplest Popup: Invalid pattern ID');
			return;
		}

		// Save scroll position BEFORE any DOM changes (for all screen sizes)
		savedScrollPosition = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;

		// Store the element that triggered the modal for focus restoration
		lastActiveElement = document.activeElement;

		// Store the requested max-width for resize handling
		currentMaxWidth = maxWidth;
		
		// Calculate and apply max-width with 6% margin
		var calculatedWidth = calculateMaxWidth(maxWidth);
		container.style.maxWidth = calculatedWidth + 'px';

		// Prevent body scroll by saving scroll position (for all screen sizes)
		body.style.top = '-' + savedScrollPosition + 'px';
		
		// Update ARIA attributes
		modal.setAttribute('aria-hidden', 'false');
		modal.setAttribute('aria-busy', 'true');
		
		// Hide background from assistive technology
		hideBackgroundFromAT();
		
		modal.classList.add('active');
		modal.style.display = 'flex';
		body.classList.add('sppopups-open');
		content.innerHTML = loadingHtml;
		
		// Focus modal container or close button for accessibility
		// Use setTimeout to ensure modal is visible before focusing
		setTimeout(function() {
			// Try to focus the close button first, fallback to modal container
			// Use focusWithoutScroll to prevent page from jumping
			if (closeBtn) {
				focusWithoutScroll(closeBtn);
			} else {
				focusWithoutScroll(modal);
			}
		}, 0);

		// Prepare form data for POST request
		var formData = new FormData();
		formData.append('action', 'sppopups_get_block');
		formData.append('block_id', patternId);
		formData.append('nonce', sppopups.nonce);

		// Fetch synced pattern content via AJAX
		fetch(sppopups.ajaxUrl, {
			method: 'POST',
			body: formData
		})
			.then(function(response) {
				if (!response.ok) {
					throw new Error('HTTP ' + response.status + ': ' + response.statusText);
				}
				return response.json();
			})
			.then(function(data) {
				// Update aria-busy to false
				modal.setAttribute('aria-busy', 'false');
				
				// Update dialog title from AJAX response
				var titleElement = modal.querySelector('#sppopups-title');
				if (titleElement && data.success && data.data && data.data.title) {
					titleElement.textContent = data.data.title;
				} else if (titleElement) {
					// Fallback title
					titleElement.textContent = 'Popup';
				}
				
				if (data.success && data.data && data.data.html) {
					// Extract style handles, block support CSS, block style variation CSS, and global stylesheet from response
					var styleHandles = (data.data.styles && Array.isArray(data.data.styles)) ? data.data.styles : [];
					var blockSupportsCss = (data.data.block_supports_css && typeof data.data.block_supports_css === 'string') ? data.data.block_supports_css : '';
					var blockStyleVariationCss = (data.data.block_style_variation_css && typeof data.data.block_style_variation_css === 'string') ? data.data.block_style_variation_css : '';
					var globalStylesheet = (data.data.global_stylesheet && typeof data.data.global_stylesheet === 'string') ? data.data.global_stylesheet : '';
					var assetData = (data.data.asset_data && typeof data.data.asset_data === 'object') ? data.data.asset_data : { styles: [], scripts: [] };
					var assetStyles = (assetData.styles && Array.isArray(assetData.styles)) ? assetData.styles : [];
					var assetScripts = (assetData.scripts && Array.isArray(assetData.scripts)) ? assetData.scripts : [];
					
					// Collect all promises for asset injection
					var assetPromises = [];
					
					// Inject inline CSS stylesheets using helper function
					injectInlineStyle(globalStylesheet, 'global-styles', 'global-styles');
					injectInlineStyle(blockSupportsCss, 'block-supports', 'block-supports');
					injectInlineStyle(blockStyleVariationCss, 'block-style-variation', 'block-style-variation');
					
					// Inject styles from asset_data (with inline CSS)
					assetStyles.forEach(function(styleAsset) {
						if (!styleAsset || !styleAsset.handle) {
							return;
						}
						
						// Inject inline before CSS if provided
						if (styleAsset.inline_before && typeof styleAsset.inline_before === 'string' && styleAsset.inline_before.trim().length > 0) {
							var beforeStyle = document.createElement('style');
							beforeStyle.setAttribute('data-handle', styleAsset.handle + '-before');
							beforeStyle.setAttribute('data-simplest-popup', 'inline-before');
							beforeStyle.textContent = styleAsset.inline_before;
							document.head.appendChild(beforeStyle);
						}
						
						// Inject external stylesheet if src exists
						if (styleAsset.src && typeof styleAsset.src === 'string' && styleAsset.src.trim().length > 0) {
							// Validate URL before injection (security check)
							if (!isValidUrl(styleAsset.src)) {
								console.warn('Simplest Popup: Invalid or unsafe style URL for handle "' + styleAsset.handle + '". Style will not be loaded.');
								return; // Skip unsafe style
							}

							// Check if already loaded
							if (!loadedStyles.has(styleAsset.handle)) {
								var link = document.createElement('link');
								link.rel = 'stylesheet';
								link.href = styleAsset.src;
								link.setAttribute('data-handle', styleAsset.handle);
								link.id = styleAsset.handle + '-css';
								
								var linkPromise = new Promise(function(resolve) {
									link.onload = function() {
										loadedStyles.add(styleAsset.handle);
										resolve();
									};
									link.onerror = function() {
										console.warn('Simplest Popup: Failed to load style "' + styleAsset.handle + '" from ' + styleAsset.src);
										resolve(); // Don't break modal
									};
								});
								
								document.head.appendChild(link);
								assetPromises.push(linkPromise);
							}
						}
						
						// Inject inline after CSS if provided
						if (styleAsset.inline_after && typeof styleAsset.inline_after === 'string' && styleAsset.inline_after.trim().length > 0) {
							var afterStyle = document.createElement('style');
							afterStyle.setAttribute('data-handle', styleAsset.handle + '-after');
							afterStyle.setAttribute('data-simplest-popup', 'inline-after');
							afterStyle.textContent = styleAsset.inline_after;
							document.head.appendChild(afterStyle);
						}
					});
					
					// Inject legacy style handles (for backward compatibility)
					if (styleHandles.length > 0) {
						assetPromises.push(injectStyles(styleHandles));
					}
					
					// Inject scripts from asset_data (with inline JS)
					assetPromises.push(injectScripts(assetScripts));
					
					// Wait for all assets to load, then insert content
					Promise.all(assetPromises).then(function() {
						// All assets loaded (or failed gracefully), insert content
						content.innerHTML = data.data.html;
					}).catch(function(error) {
						// Even if asset injection fails, show content
						console.error('Synced Pattern Popups: Error injecting assets:', error);
						content.innerHTML = data.data.html;
					});
				} else {
					var errorMsg = (data.data && data.data.message) ? data.data.message : sppopups.strings.notFound;
					content.innerHTML = '<div class="sppopups-loading"><p>' + errorMsg + '</p></div>';
				}
			})
			.catch(function(error) {
				console.error('Synced Pattern Popups: Error loading block:', error);
				modal.setAttribute('aria-busy', 'false');
				content.innerHTML = '<div class="sppopups-loading"><p>' + sppopups.strings.error + '</p></div>';
			});
	}

	/**
	 * Close modal
	 */
	function closeModal() {
		// Update ARIA attributes
		modal.setAttribute('aria-hidden', 'true');
		modal.setAttribute('aria-busy', 'false');
		
		// Show background to assistive technology
		showBackgroundToAT();
		
		modal.classList.remove('active');
		modal.style.display = 'none';
		body.classList.remove('sppopups-open');
		
		// Reset max-width to default
		container.style.maxWidth = '';
		currentMaxWidth = null;
		
		// Restore scroll position BEFORE removing body styles and restoring focus
		// This prevents any scroll jumps
		if (savedScrollPosition > 0) {
			window.scrollTo(0, savedScrollPosition);
		}
		
		// Clear body scroll lock styles
		if (body.style.top) {
			body.style.top = '';
		}
		
		// Restore focus to the element that triggered the modal
		// Use focusWithoutScroll to prevent page from jumping
		if (lastActiveElement && document.body.contains(lastActiveElement)) {
			focusWithoutScroll(lastActiveElement);
		}
		
		// Clear saved values
		lastActiveElement = null;
		savedScrollPosition = 0;
	}

	// Close on overlay click
	overlay.addEventListener('click', closeModal);

	// Close on close button click
	closeBtn.addEventListener('click', closeModal);

	// Close on footer close button click
	if (closeFooterBtn) {
		closeFooterBtn.addEventListener('click', closeModal);
	}

	// Handle keyboard events for focus trap and Escape key
	document.addEventListener('keydown', function(e) {
		// Only handle if modal is active
		if (!modal.classList.contains('active')) {
			return;
		}
		
		// Close on Escape key
		if (e.key === 'Escape') {
			closeModal();
			return;
		}
		
		// Focus trap: handle Tab and Shift+Tab
		if (e.key === 'Tab') {
			var focusableElements = getFocusableElements();
			
			// If no focusable elements, prevent default tab behavior
			if (focusableElements.length === 0) {
				e.preventDefault();
				return;
			}
			
			var firstElement = focusableElements[0];
			var lastElement = focusableElements[focusableElements.length - 1];
			var currentElement = document.activeElement;
			
			// Find current element index
			var currentIndex = focusableElements.indexOf(currentElement);
			
			// If Shift+Tab (going backwards)
			if (e.shiftKey) {
				// If at first element or not in modal, go to last element
				if (currentIndex <= 0 || currentIndex === -1) {
					e.preventDefault();
					lastElement.focus();
				}
			} else {
				// If Tab (going forwards)
				// If at last element or not in modal, go to first element
				if (currentIndex >= focusableElements.length - 1 || currentIndex === -1) {
					e.preventDefault();
					firstElement.focus();
				}
			}
		}
	});

	// Open modal when elements with "wppt-popup-{id}" class or href="#wppt-popup-{id}" are clicked
	document.addEventListener('click', function(e) {
		var trigger = e.target;
		var patternData = null;

		// First, try to find pattern ID from class name
		// Check the clicked element and its closest parent with class
		var elementWithClass = trigger.closest('[class*="wppt-popup-"]');
		if (elementWithClass) {
			// Extract from class name (check all classes)
			if (elementWithClass.className) {
				var classes = elementWithClass.className.split(/\s+/);
				for (var j = 0; j < classes.length; j++) {
					patternData = extractPatternData(classes[j], false);
					if (patternData) {
						break;
					}
				}
			}
		}

		// If no class-based trigger found, check for href attribute
		if (!patternData) {
			// Check if the clicked element is a link or find closest link
			var linkElement = trigger.closest('a');
			if (linkElement && linkElement.href) {
				// Get the hash portion of the href
				var hash = linkElement.hash || '';
				// Also check the full href in case it's a relative URL
				var href = linkElement.getAttribute('href') || '';
				
				// Try to extract from hash first
				if (hash) {
					patternData = extractPatternData(hash, true);
				}
				
				// If no hash match, try the full href (for cases like href="#wppt-popup-123")
				if (!patternData && href) {
					patternData = extractPatternData(href, true);
				}
			}
		}

		// If we found valid pattern data, open the modal
		if (patternData && patternData.id && patternData.id > 0) {
			e.preventDefault();
			e.stopPropagation();
			openModal(patternData.id, patternData.maxWidth);
		}
	});

	// Handle window resize to maintain 6% margin
	var resizeTimeout;
	window.addEventListener('resize', function() {
		// Only recalculate if modal is open and we have a custom max-width
		if (modal.classList.contains('active') && currentMaxWidth !== null) {
			clearTimeout(resizeTimeout);
			resizeTimeout = setTimeout(function() {
				var calculatedWidth = calculateMaxWidth(currentMaxWidth);
				container.style.maxWidth = calculatedWidth + 'px';
			}, 100); // Debounce resize events
		}
	});
})();

