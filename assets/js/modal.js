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

		// Store the requested max-width for resize handling
		currentMaxWidth = maxWidth;
		
		// Calculate and apply max-width with 6% margin
		var calculatedWidth = calculateMaxWidth(maxWidth);
		container.style.maxWidth = calculatedWidth + 'px';

		// Prevent body scroll on mobile by saving scroll position
		if (window.innerWidth <= 768) {
			body.style.top = '-' + window.scrollY + 'px';
		}
		
		modal.classList.add('active');
		modal.style.display = 'flex';
		body.classList.add('simplest-popup-open');
		content.innerHTML = loadingHtml;
		
		// Focus close button for accessibility (but don't scroll on mobile)
		if (window.innerWidth > 768) {
			closeBtn.focus();
		}

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
				if (data.success && data.data && data.data.html) {
					content.innerHTML = data.data.html;
				} else {
					var errorMsg = (data.data && data.data.message) ? data.data.message : simplestPopup.strings.notFound;
					content.innerHTML = '<div class="simplest-popup-loading"><p>' + errorMsg + '</p></div>';
				}
			})
			.catch(function(error) {
				console.error('Simplest Popup: Error loading block:', error);
				content.innerHTML = '<div class="simplest-popup-loading"><p>' + simplestPopup.strings.error + '</p></div>';
			});
	}

	/**
	 * Close modal
	 */
	function closeModal() {
		modal.classList.remove('active');
		modal.style.display = 'none';
		body.classList.remove('simplest-popup-open');
		
		// Reset max-width to default
		container.style.maxWidth = '';
		currentMaxWidth = null;
		
		// Restore body scroll position on mobile
		if (body.style.top) {
			window.scrollTo(0, parseInt(body.style.top || '0') * -1);
			body.style.top = '';
		}
	}

	// Close on overlay click
	overlay.addEventListener('click', closeModal);

	// Close on close button click
	closeBtn.addEventListener('click', closeModal);

	// Close on footer close button click
	if (closeFooterBtn) {
		closeFooterBtn.addEventListener('click', closeModal);
	}

	// Close on Escape key
	document.addEventListener('keydown', function(e) {
		if (e.key === 'Escape' && modal.classList.contains('active')) {
			closeModal();
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

