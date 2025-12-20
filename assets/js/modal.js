/**
 * Simplest Popup Modal JavaScript
 * No build process - plain vanilla JavaScript
 */
(function() {
	'use strict';

	// Get localized data from WordPress
	if (typeof simplestPopup === 'undefined') {
		console.error('Simplest Popup: Localized data not found');
		return;
	}

	var modal = document.getElementById('simplest-popup-modal');
	if (!modal) {
		return;
	}

	var overlay = modal.querySelector('.simplest-popup-overlay');
	var closeBtn = modal.querySelector('.simplest-popup-close');
	var closeFooterBtn = modal.querySelector('.simplest-popup-close-footer');
	var content = modal.querySelector('.simplest-popup-content');
	var container = modal.querySelector('.simplest-popup-container');
	var body = document.body;

	if (!overlay || !closeBtn || !content || !container) {
		return;
	}

	var loadingHtml = '<div class="simplest-popup-loading"><div class="simplest-popup-spinner"></div><p>' + simplestPopup.strings.loading + '</p></div>';
	
	// Store current max-width setting for resize handling
	var currentMaxWidth = null;
	
	// Store last active element for focus restoration
	var lastActiveElement = null;
	
	// Store array of background elements that were hidden from AT
	var hiddenBackgroundElements = [];
	
	// Store scroll position to restore after modal closes
	var savedScrollPosition = 0;

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
			
			if (id > 0) {
				return {
					id: id,
					maxWidth: maxWidth
				};
			}
		}

		return null;
	}

	/**
	 * Extract pattern ID and optional max-width from class name
	 *
	 * @param {string} className Class name string
	 * @return {object|null} Object with id and maxWidth, or null if not found
	 */
	function extractIdFromClass(className) {
		if (!className) {
			return null;
		}

		var classes = className.split(/\s+/);
		for (var i = 0; i < classes.length; i++) {
			var result = extractPatternData(classes[i], false);
			if (result) {
				return result;
			}
		}
		return null;
	}

	/**
	 * Extract pattern ID and optional max-width from href attribute
	 *
	 * @param {string} href Href attribute value
	 * @return {object|null} Object with id and maxWidth, or null if not found
	 */
	function extractIdFromHref(href) {
		return extractPatternData(href, true);
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
	 * Get all focusable elements within the modal
	 *
	 * @return {Array} Array of focusable elements
	 */
	function getFocusableElements() {
		var card = modal.querySelector('.simplest-popup-card');
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
			if (element.id === 'simplest-popup-modal') {
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
		body.classList.add('simplest-popup-open');
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
		formData.append('action', 'simplest_popup_get_block');
		formData.append('block_id', patternId);
		formData.append('nonce', simplestPopup.nonce);

		// Fetch synced pattern content via AJAX
		fetch(simplestPopup.ajaxUrl, {
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
				var titleElement = modal.querySelector('#simplest-popup-title');
				if (titleElement && data.success && data.data && data.data.title) {
					titleElement.textContent = data.data.title;
				} else if (titleElement) {
					// Fallback title
					titleElement.textContent = 'Popup';
				}
				
				if (data.success && data.data && data.data.html) {
					content.innerHTML = data.data.html;
				} else {
					var errorMsg = (data.data && data.data.message) ? data.data.message : simplestPopup.strings.notFound;
					content.innerHTML = '<div class="simplest-popup-loading"><p>' + errorMsg + '</p></div>';
				}
			})
			.catch(function(error) {
				console.error('Simplest Popup: Error loading block:', error);
				modal.setAttribute('aria-busy', 'false');
				content.innerHTML = '<div class="simplest-popup-loading"><p>' + simplestPopup.strings.error + '</p></div>';
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
		body.classList.remove('simplest-popup-open');
		
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
			patternData = extractIdFromClass(elementWithClass.className);
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
					patternData = extractIdFromHref(hash);
				}
				
				// If no hash match, try the full href (for cases like href="#wppt-popup-123")
				if (!patternData && href) {
					patternData = extractIdFromHref(href);
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

