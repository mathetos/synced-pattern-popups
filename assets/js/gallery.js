/**
 * Synced Pattern Popups Gallery Module
 * Handles gallery image modal functionality
 *
 * @module sppopups-gallery
 * @since 1.2.0
 */
(function () {
	'use strict';

	// Module state
	var currentGalleryData  = null;
	var currentGalleryIndex = 0;
	var galleryMode         = false;

	// Global gallery image cache (shared across all galleries on page)
	var galleryImageCache = {
		loadedImages: {}, // Track loaded images by URL
		loadingImages: {}, // Track images currently loading
		imageElements: {}, // Store pre-created img elements by URL
		preloadPromises: {} // Track preload promises
	};

	// Dependencies (injected from modal.js)
	var dependencies = {
		modal: null,
		content: null,
		container: null,
		closeBtn: null,
		closeModal: null,
		setupModalState: null,
		getTitleElement: null,
		focusWithoutScroll: null,
		currentMaxWidth: null,
		setCurrentMaxWidth: null
	};

	/**
	 * Get image URL from image object (with fallback)
	 *
	 * @param {object} image Image object
	 * @return {string} Image URL or empty string
	 * @private
	 */
	function getImageUrl(image) {
		if ( ! image) {
			return '';
		}
		return image.fullUrl || image.url || '';
	}

	/**
	 * Get gallery settings with defaults
	 *
	 * @param {object} galleryData Gallery data object
	 * @return {object} Normalized settings object
	 * @private
	 */
	function getGallerySettings(galleryData) {
		if ( ! galleryData) {
			return {
				closeButtons: 'both',
				imageNavigation: 'both',
				modalSize: 600
			};
		}

		var settings = {
			closeButtons: galleryData.closeButtons || 'both',
			imageNavigation: galleryData.imageNavigation || 'both',
			modalSize: galleryData.modalSize || 600
		};

		// Validate modal size
		if (isNaN( settings.modalSize ) || settings.modalSize < 100) {
			settings.modalSize = 600;
		}

		return settings;
	}

	/**
	 * Update container height based on image dimensions
	 *
	 * @param {HTMLElement} galleryContainer Gallery container element
	 * @param {HTMLImageElement} img Image element
	 * @private
	 */
	function updateContainerHeight(galleryContainer, img) {
		if ( ! galleryContainer || ! img) {
			return;
		}

		var modalContainer = dependencies.container;

		// Use requestAnimationFrame for better timing than setTimeout
		requestAnimationFrame(
			function () {
				// Use natural dimensions to calculate exact container size
				var imgHeight = img.naturalHeight || img.height || 0;
				var imgWidth  = img.naturalWidth || img.width || 0;

				if (imgHeight > 0 && imgWidth > 0) {
					// Get the modal container width (the actual max-width setting)
					var containerWidth = modalContainer ? (modalContainer.offsetWidth || modalContainer.clientWidth) : 600;

					// Calculate aspect ratio
					var aspectRatio = imgHeight / imgWidth;

					// Calculate height based on container width and image aspect ratio
					var calculatedHeight = containerWidth * aspectRatio;
					var maxHeight        = window.innerHeight * 0.8; // 80vh max

					// Use the smaller of calculated height or max height
					var finalHeight = Math.min( calculatedHeight, maxHeight );

					// Set height exactly to match image aspect ratio (ensure minimum 200px)
					galleryContainer.style.height    = Math.max( finalHeight, 200 ) + 'px';
					galleryContainer.style.minHeight = Math.max( finalHeight, 200 ) + 'px';
				}
			}
		);
	}

	/**
	 * Create image layer with navigation buttons
	 *
	 * @param {number} index Image index
	 * @param {string} imageUrl Image URL
	 * @param {HTMLElement} galleryContainer Gallery container
	 * @param {Function} callback Callback function
	 * @private
	 */
	function createImageLayer(index, imageUrl, galleryContainer, callback) {
		var image    = currentGalleryData.images[index];
		var settings = currentGalleryData.settings || getGallerySettings( currentGalleryData );

		// Create new wrapper layer
		var newWrapper       = document.createElement( 'div' );
		newWrapper.className = 'sppopups-gallery-image-wrapper fading-in';

		// Create image element
		var img       = document.createElement( 'img' );
		img.src       = imageUrl;
		img.alt       = image.alt || '';
		img.className = 'sppopups-gallery-image';

		// Wait for image to load to get dimensions
		img.onload = function () {
			// Update container min-height based on image dimensions
			updateContainerHeight( galleryContainer, img );

			// Add navigation buttons if needed
			if (settings.imageNavigation === 'image' || settings.imageNavigation === 'both') {
				// Previous button
				var prevBtn       = document.createElement( 'button' );
				prevBtn.className = 'sppopups-gallery-nav sppopups-gallery-nav--prev';
				prevBtn.setAttribute( 'aria-label', 'Previous image' );
				prevBtn.setAttribute( 'type', 'button' );
				prevBtn.setAttribute( 'title', 'Previous image (Left Arrow)' );
				prevBtn.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

				// Next button
				var nextBtn       = document.createElement( 'button' );
				nextBtn.className = 'sppopups-gallery-nav sppopups-gallery-nav--next';
				nextBtn.setAttribute( 'aria-label', 'Next image' );
				nextBtn.setAttribute( 'type', 'button' );
				nextBtn.setAttribute( 'title', 'Next image (Right Arrow)' );
				nextBtn.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

				newWrapper.appendChild( prevBtn );
				newWrapper.appendChild( nextBtn );
			}

			newWrapper.insertBefore( img, newWrapper.firstChild );
			galleryContainer.appendChild( newWrapper );

			// Callback with new wrapper
			if (callback) {
				callback( newWrapper );
			}
		};

		img.onerror = function () {
			// Even if image fails, still add it
			newWrapper.insertBefore( img, newWrapper.firstChild );
			galleryContainer.appendChild( newWrapper );
			if (callback) {
				callback( newWrapper );
			}
		};

		// If image is already cached, onload might not fire
		if (galleryImageCache.loadedImages[imageUrl] && img.complete) {
			img.onload();
		}
	}

	/**
	 * Crossfade to new image
	 *
	 * @param {number} index Image index
	 * @param {string} imageUrl Image URL
	 * @param {boolean} useTransition Whether to use transition
	 * @param {number} transitionDuration Transition duration in ms
	 * @private
	 */
	function crossfadeToImage(index, imageUrl, useTransition, transitionDuration) {
		// Ensure gallery container exists
		var galleryContainer = dependencies.content.querySelector( '.sppopups-gallery-image-container' );
		if ( ! galleryContainer) {
			galleryContainer               = document.createElement( 'div' );
			galleryContainer.className     = 'sppopups-gallery-image-container';
			dependencies.content.innerHTML = '';
			dependencies.content.appendChild( galleryContainer );
		}

		var existingWrapper = galleryContainer.querySelector( '.sppopups-gallery-image-wrapper.active' );

		if (useTransition && existingWrapper) {
			// Mark old layer as fading out
			existingWrapper.classList.remove( 'active' );
			existingWrapper.classList.add( 'fading-out' );

			// Create new layer and add it
			createImageLayer(
				index,
				imageUrl,
				galleryContainer,
				function (newWrapper) {
					// New layer is created, now fade both simultaneously
					// Force reflow to ensure initial state
					newWrapper.offsetHeight;

					// Start fade-in animation
					newWrapper.classList.add( 'active' );

					// Attach event listeners to new layer
					attachEventListeners();

					// Clean up old layer after transition
					setTimeout(
						function () {
							if (existingWrapper && existingWrapper.parentNode) {
								existingWrapper.parentNode.removeChild( existingWrapper );
							}
							// Remove fading-in class after transition
							if (newWrapper) {
								newWrapper.classList.remove( 'fading-in' );
							}
						},
						transitionDuration + 50
					);
				}
			);
		} else {
			// No transition, update immediately
			if (existingWrapper && existingWrapper.parentNode) {
				existingWrapper.parentNode.removeChild( existingWrapper );
			}
			updateImageContent( index, imageUrl, useTransition, transitionDuration, galleryContainer );
		}
	}

	/**
	 * Load and show image (fallback if not preloaded)
	 *
	 * @param {number} index Image index
	 * @param {string} imageUrl Image URL
	 * @param {boolean} useTransition Whether to use transition
	 * @param {number} transitionDuration Transition duration in ms
	 * @private
	 */
	function loadAndShowImage(index, imageUrl, useTransition, transitionDuration) {
		var galleryContainer = dependencies.content.querySelector( '.sppopups-gallery-image-container' );
		var img              = new Image();

		img.onload = function () {
			// Cache the loaded image
			galleryImageCache.loadedImages[imageUrl]  = true;
			galleryImageCache.imageElements[imageUrl] = img;

			// Now crossfade
			crossfadeToImage( index, imageUrl, useTransition, transitionDuration );
		};

		img.onerror = function () {
			// Even if image fails to load, still update (will show broken image)
			updateImageContent( index, imageUrl, useTransition, transitionDuration, galleryContainer );
		};

		img.src = imageUrl;
	}

	/**
	 * Update image content (non-transition version)
	 *
	 * @param {number} index Image index
	 * @param {string} imageUrl Image URL
	 * @param {boolean} useTransition Whether to use transition
	 * @param {number} transitionDuration Transition duration in ms
	 * @param {HTMLElement} galleryContainer Gallery container
	 * @private
	 */
	function updateImageContent(index, imageUrl, useTransition, transitionDuration, galleryContainer) {
		// Check if gallery container exists, create if not
		if ( ! galleryContainer) {
			galleryContainer = dependencies.content.querySelector( '.sppopups-gallery-image-container' );
		}

		if ( ! galleryContainer) {
			galleryContainer               = document.createElement( 'div' );
			galleryContainer.className     = 'sppopups-gallery-image-container';
			dependencies.content.innerHTML = '';
			dependencies.content.appendChild( galleryContainer );
		} else {
			// Remove all existing layers
			var existingLayers = galleryContainer.querySelectorAll( '.sppopups-gallery-image-wrapper' );
			existingLayers.forEach(
				function (layer) {
					layer.parentNode.removeChild( layer );
				}
			);
		}

		// Create new layer (non-transition version)
		createImageLayer(
			index,
			imageUrl,
			galleryContainer,
			function (newWrapper) {
				newWrapper.classList.add( 'active' );
				attachEventListeners();
			}
		);
	}

	/**
	 * Attach event listeners to footer and navigation buttons
	 *
	 * @private
	 */
	function attachEventListeners() {
		var settings = currentGalleryData.settings || getGallerySettings( currentGalleryData );
		var image    = currentGalleryData.images[currentGalleryIndex];

		// Update footer with navigation and caption
		var footer = dependencies.modal.querySelector( '.sppopups-footer' );
		if (footer) {
			// Use template literals for cleaner HTML building
			var navGroup = '';
			if (settings.imageNavigation === 'footer' || settings.imageNavigation === 'both') {
				navGroup           = `<div class="sppopups-gallery-nav-group">
					<button class="sppopups-gallery-nav-footer sppopups-gallery-nav-footer--prev" aria-label="Previous image" type="button">← Previous</button>
					<button class="sppopups-gallery-nav-footer sppopups-gallery-nav-footer--next" aria-label="Next image" type="button">Next →</button>
				</div>`;
			} else {
				navGroup = '<div class="sppopups-gallery-nav-group"></div>';
			}

			// Caption (center) - handle HTML content
			var caption = '';
			if (image.caption && image.caption.trim()) {
				// Caption may contain HTML, so we'll insert it as-is (already sanitized in PHP)
				caption = `<div class="sppopups-gallery-caption">${image.caption}</div>`;
			} else {
				caption = '<div class="sppopups-gallery-caption"></div>';
			}

			// Close button (right-aligned) - only if closeButtons is 'button' or 'both'
			var closeButton = '';
			if (settings.closeButtons === 'button' || settings.closeButtons === 'both') {
				closeButton = '<button class="sppopups-close-footer" type="button" aria-label="Close modal">Close →</button>';
			} else {
				closeButton = '<div></div>';
			}

			footer.innerHTML = `<div class="sppopups-gallery-footer">${navGroup}${caption}${closeButton}</div>`;

			// Attach event listeners to footer navigation buttons (never disabled)
			var prevFooterBtn  = footer.querySelector( '.sppopups-gallery-nav-footer--prev' );
			var nextFooterBtn  = footer.querySelector( '.sppopups-gallery-nav-footer--next' );
			var closeFooterBtn = footer.querySelector( '.sppopups-close-footer' );

			if (prevFooterBtn) {
				prevFooterBtn.addEventListener(
					'click',
					function () {
						navigateGallery( 'prev' );
					}
				);
			}

			if (nextFooterBtn) {
				nextFooterBtn.addEventListener(
					'click',
					function () {
						navigateGallery( 'next' );
					}
				);
			}

			// Attach event listener to close button
			if (closeFooterBtn) {
				closeFooterBtn.addEventListener(
					'click',
					function (e) {
						e.preventDefault();
						e.stopPropagation();
						dependencies.closeModal();
					}
				);
			}
		}

		// Images should already be preloaded, but ensure they're loading
		if (currentGalleryData && currentGalleryData.images) {
			preloadGalleryImages( currentGalleryData.images );
		}
	}

	/**
	 * Update displayed image and caption in gallery modal
	 *
	 * @param {number} index Image index to display
	 * @public
	 */
	function updateGalleryImage(index) {
		if ( ! currentGalleryData || ! Array.isArray( currentGalleryData.images )) {
			return;
		}

		if (index < 0 || index >= currentGalleryData.images.length) {
			return;
		}

		var image           = currentGalleryData.images[index];
		currentGalleryIndex = index;

		// Update modal title with image position
		var titleEl = dependencies.getTitleElement();
		if (titleEl) {
			titleEl.textContent = 'Image ' + (index + 1) + ' of ' + currentGalleryData.images.length;
		}

		// Get settings from gallery data (use cached settings if available)
		var settings = currentGalleryData.settings || getGallerySettings( currentGalleryData );

		// Check if we should use fade transition (check for reduced motion preference)
		var prefersReducedMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		var useTransition        = ! prefersReducedMotion;
		var transitionDuration   = 300; // 300ms for smooth transition

		var imageUrl = getImageUrl( image );

		// Check if image is already loaded in cache
		if (galleryImageCache.loadedImages[imageUrl]) {
			// Image is ready - instant crossfade!
			crossfadeToImage( index, imageUrl, useTransition, transitionDuration );
		} else if (galleryImageCache.preloadPromises[imageUrl]) {
			// Image is loading - wait for it, then crossfade
			galleryImageCache.preloadPromises[imageUrl].then(
				function () {
					crossfadeToImage( index, imageUrl, useTransition, transitionDuration );
				}
			).catch(
				function () {
					// Preload failed, fallback to direct load
					loadAndShowImage( index, imageUrl, useTransition, transitionDuration );
				}
			);
		} else {
			// Image not preloaded (shouldn't happen, but fallback)
			loadAndShowImage( index, imageUrl, useTransition, transitionDuration );
		}
	}

	/**
	 * Navigate to previous or next image in gallery
	 *
	 * @param {string} direction 'prev' or 'next'
	 * @public
	 */
	function navigateGallery(direction) {
		if ( ! currentGalleryData || ! Array.isArray( currentGalleryData.images )) {
			return;
		}

		var newIndex = currentGalleryIndex;
		if (direction === 'prev') {
			newIndex = currentGalleryIndex > 0 ? currentGalleryIndex - 1 : currentGalleryData.images.length - 1;
		} else if (direction === 'next') {
			newIndex = currentGalleryIndex < currentGalleryData.images.length - 1 ? currentGalleryIndex + 1 : 0;
		}

		if (newIndex !== currentGalleryIndex) {
			updateGalleryImage( newIndex );
		}
	}

	/**
	 * Open gallery modal with image navigation
	 *
	 * @param {object} galleryData Gallery data object with images array
	 * @param {number} initialIndex Initial image index to display
	 * @public
	 */
	function openGalleryModal(galleryData, initialIndex) {
		if ( ! galleryData || ! Array.isArray( galleryData.images ) || galleryData.images.length === 0) {
			console.error( 'Synced Pattern Popups: Invalid gallery data' );
			return;
		}

		if (isNaN( initialIndex ) || initialIndex < 0 || initialIndex >= galleryData.images.length) {
			initialIndex = 0;
		}

		// Store gallery state
		currentGalleryData  = galleryData;
		currentGalleryIndex = initialIndex;
		galleryMode         = true;

		// Extract and cache settings
		var settings                = getGallerySettings( galleryData );
		currentGalleryData.settings = settings;

		// Set modal size
		if (dependencies.container) {
			dependencies.container.style.maxWidth = settings.modalSize + 'px';
			if (dependencies.setCurrentMaxWidth) {
				dependencies.setCurrentMaxWidth( settings.modalSize );
			}
		}

		// Show/hide close buttons based on setting
		if (dependencies.closeBtn) {
			dependencies.closeBtn.style.display = (settings.closeButtons === 'icon' || settings.closeButtons === 'both') ? '' : 'none';
		}

		// Use common modal setup
		dependencies.setupModalState( settings.modalSize, '' );

		// Create container for gallery images
		var galleryContainer           = document.createElement( 'div' );
		galleryContainer.className     = 'sppopups-gallery-image-container';
		dependencies.content.innerHTML = '';
		dependencies.content.appendChild( galleryContainer );

		// Display initial image
		updateGalleryImage( initialIndex );

		// Focus modal container or close button for accessibility
		setTimeout(
			function () {
				if (dependencies.closeBtn) {
					dependencies.focusWithoutScroll( dependencies.closeBtn );
				} else {
					dependencies.focusWithoutScroll( dependencies.modal );
				}
			},
			0
		);

		// Images should already be preloaded from page load, but ensure this gallery's images are loading
		preloadGalleryImages( galleryData.images );
	}

	/**
	 * Preload all images for a gallery (called on page load)
	 *
	 * @param {array} images Array of image objects
	 * @private
	 */
	function preloadGalleryImages(images) {
		if ( ! images || ! Array.isArray( images )) {
			return;
		}

		images.forEach(
			function (image) {
				var imageUrl = getImageUrl( image );
				if ( ! imageUrl) {
					return;
				}

				// Skip if already loaded or loading
				if (galleryImageCache.loadedImages[imageUrl] ||
				galleryImageCache.loadingImages[imageUrl]) {
					return;
				}

				// Mark as loading
				galleryImageCache.loadingImages[imageUrl] = true;

				// Create promise for this image
				var preloadPromise = new Promise(
					function (resolve, reject) {
						var img = new Image();

						img.onload = function () {
							galleryImageCache.loadedImages[imageUrl]  = true;
							galleryImageCache.imageElements[imageUrl] = img;
							delete galleryImageCache.loadingImages[imageUrl];
							resolve( img );
						};

						img.onerror = function () {
							delete galleryImageCache.loadingImages[imageUrl];
							reject( new Error( 'Failed to load image: ' + imageUrl ) );
						};

						// Start loading (browser will handle priority)
						img.src = imageUrl;
					}
				);

				galleryImageCache.preloadPromises[imageUrl] = preloadPromise;
			}
		);
	}

	/**
	 * Scan page for galleries and preload their images
	 *
	 * @private
	 */
	function scanAndPreloadGalleries() {
		// Find all galleries on the page
		var galleries = document.querySelectorAll( '[data-sppopup-gallery="true"]' );

		galleries.forEach(
			function (gallery) {
				var galleryDataAttr = gallery.getAttribute( 'data-gallery-data' );
				if (galleryDataAttr) {
					try {
						var galleryData = JSON.parse( galleryDataAttr );
						if (galleryData && Array.isArray( galleryData.images )) {
							// Start preloading all images for this gallery
							preloadGalleryImages( galleryData.images );
						}
					} catch (err) {
						console.error( 'Synced Pattern Popups: Error parsing gallery data for preload:', err );
					}
				}
			}
		);
	}

	/**
	 * Reset gallery state
	 *
	 * @public
	 */
	function resetGalleryState() {
		currentGalleryData  = null;
		currentGalleryIndex = 0;
		galleryMode         = false;
	}

	/**
	 * Check if currently in gallery mode
	 *
	 * @return {boolean} True if in gallery mode
	 * @public
	 */
	function isGalleryMode() {
		return galleryMode;
	}

	/**
	 * Initialize gallery module with dependencies
	 *
	 * @param {object} deps Dependencies object
	 * @public
	 */
	function init(deps) {
		// Store dependencies
		dependencies = deps;

		// Set up event delegation for gallery navigation buttons (once at initialization)
		// This handles clicks on buttons in any layer (active or transitioning)
		if (dependencies.content) {
			dependencies.content.addEventListener(
				'click',
				function (e) {
					if ( ! galleryMode) {
						return;
					}

					var target = e.target;
					var navBtn = target.closest( '.sppopups-gallery-nav--prev, .sppopups-gallery-nav--next' );

					if (navBtn) {
						e.preventDefault();
						e.stopPropagation();

						if (navBtn.classList.contains( 'sppopups-gallery-nav--prev' )) {
							navigateGallery( 'prev' );
						} else if (navBtn.classList.contains( 'sppopups-gallery-nav--next' )) {
							navigateGallery( 'next' );
						}
					}
				}
			);
		}

		// Initialize gallery preloading on page load
		(function initGalleryPreloading() {
			// Wait for DOM to be ready
			if (document.readyState === 'loading') {
				document.addEventListener( 'DOMContentLoaded', scanAndPreloadGalleries );
			} else {
				// DOM already ready, scan immediately
				scanAndPreloadGalleries();
			}
		})();
	}

	// Public API
	window.sppopupsGallery = {
		init: init,
		openGalleryModal: openGalleryModal,
		navigateGallery: navigateGallery,
		resetGalleryState: resetGalleryState,
		isGalleryMode: isGalleryMode
	};
})();
