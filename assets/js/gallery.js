/**
 * Synced Pattern Popups Gallery Module
 * 
 * Handles all gallery modal functionality including image navigation, preloading,
 * crossfade transitions, and gallery state management. This module is initialized
 * by modal.js and uses dependency injection to access modal functions and DOM elements.
 * 
 * @module sppopupsGallery
 * @since 1.1.3
 * @requires modal.js (for dependency injection)
 */
(function() {
	'use strict';

	/**
	 * Current gallery data object containing images array and settings
	 * @type {Object|null}
	 * @private
	 */
	var currentGalleryData = null;

	/**
	 * Current image index being displayed in the gallery modal
	 * @type {number}
	 * @private
	 */
	var currentGalleryIndex = 0;

	/**
	 * Whether gallery mode is currently active
	 * @type {boolean}
	 * @private
	 */
	var galleryMode = false;

	/**
	 * Global gallery image cache (shared across all galleries on page)
	 * Tracks loaded images, loading state, image elements, and preload promises
	 * @type {Object}
	 * @property {Object<string, boolean>} loadedImages - Map of image URLs to loaded status
	 * @property {Object<string, boolean>} loadingImages - Map of image URLs to loading status
	 * @property {Object<string, HTMLImageElement>} imageElements - Map of image URLs to img elements
	 * @property {Object<string, Promise>} preloadPromises - Map of image URLs to preload promises
	 * @private
	 */
	var galleryImageCache = {
		loadedImages: {}, // Track loaded images by URL
		loadingImages: {}, // Track images currently loading
		imageElements: {}, // Store pre-created img elements by URL
		preloadPromises: {} // Track preload promises
	};

	/**
	 * Dependencies injected from modal.js
	 * @type {Object}
	 * @property {HTMLElement} modal - Modal container element
	 * @property {HTMLElement} content - Modal content area
	 * @property {HTMLElement} container - Modal container with max-width
	 * @property {HTMLElement} closeBtn - Close button element
	 * @property {HTMLElement} body - Document body element
	 * @property {Function} setupModalState - Function to set up modal state
	 * @property {Function} getTitleElement - Function to get/cache title element
	 * @property {Function} closeModal - Function to close the modal
	 * @property {Function} focusWithoutScroll - Function to focus without scrolling
	 * @property {Function} getImageUrl - Function to extract image URL from image object
	 * @property {Function} setMaxWidth - Function to set currentMaxWidth in modal.js
	 * @property {HTMLElement|null} modalContainer - Cached modal container reference
	 * @private
	 */
	var dependencies = {
		modal: null,
		content: null,
		container: null,
		closeBtn: null,
		body: null,
		setupModalState: null,
		getTitleElement: null,
		closeModal: null,
		focusWithoutScroll: null,
		getImageUrl: null,
		setMaxWidth: null, // Function to set currentMaxWidth
		modalContainer: null // Cached modal container reference
	};

	/**
	 * Initialize gallery module with dependencies from modal.js
	 * 
	 * Sets up event delegation for gallery navigation buttons and initializes
	 * gallery image preloading on page load.
	 *
	 * @param {Object} deps - Dependencies object from modal.js
	 * @param {HTMLElement} deps.modal - Modal container element
	 * @param {HTMLElement} deps.content - Modal content area
	 * @param {HTMLElement} deps.container - Modal container with max-width
	 * @param {HTMLElement} deps.closeBtn - Close button element
	 * @param {HTMLElement} deps.body - Document body element
	 * @param {Function} deps.setupModalState - Function to set up modal state
	 * @param {Function} deps.getTitleElement - Function to get/cache title element
	 * @param {Function} deps.closeModal - Function to close the modal
	 * @param {Function} deps.focusWithoutScroll - Function to focus without scrolling
	 * @param {Function} deps.getImageUrl - Function to extract image URL
	 * @param {Function} deps.setMaxWidth - Function to set currentMaxWidth
	 * @param {HTMLElement|null} [deps.modalContainer=null] - Cached modal container reference
	 * @return {void}
	 * @public
	 */
	function init(deps) {
		dependencies = deps;
		
		// Set up event delegation for gallery navigation buttons (once at initialization)
		// This handles clicks on buttons in any layer (active or transitioning)
		if (dependencies.content) {
			dependencies.content.addEventListener('click', function(e) {
				if (!galleryMode) return;
				
				var target = e.target;
				var navBtn = target.closest('.sppopups-gallery-nav--prev, .sppopups-gallery-nav--next');
				
				if (navBtn) {
					e.preventDefault();
					e.stopPropagation();
					
					if (navBtn.classList.contains('sppopups-gallery-nav--prev')) {
						navigateGallery('prev');
					} else if (navBtn.classList.contains('sppopups-gallery-nav--next')) {
						navigateGallery('next');
					}
				}
			});
		}

		// Initialize gallery preloading on page load
		(function initGalleryPreloading() {
			// Wait for DOM to be ready
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', scanAndPreloadGalleries);
			} else {
				// DOM already ready, scan immediately
				scanAndPreloadGalleries();
			}
		})();
	}

	/**
	 * Get gallery settings with defaults and validation
	 * 
	 * Normalizes gallery settings from gallery data, applying defaults and validating
	 * values. Ensures modal size is within acceptable bounds (100px minimum).
	 *
	 * @param {Object} [galleryData] - Gallery data object
	 * @param {string} [galleryData.closeButtons='both'] - Close button setting: 'icon', 'button', or 'both'
	 * @param {string} [galleryData.imageNavigation='both'] - Image navigation setting: 'image', 'footer', or 'both'
	 * @param {number} [galleryData.modalSize=600] - Modal width in pixels (minimum 100px)
	 * @return {Object} Normalized settings object
	 * @return {string} return.closeButtons - Close button setting
	 * @return {string} return.imageNavigation - Image navigation setting
	 * @return {number} return.modalSize - Validated modal size in pixels
	 * @private
	 */
	function getGallerySettings(galleryData) {
		if (!galleryData) {
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
		if (isNaN(settings.modalSize) || settings.modalSize < 100) {
			settings.modalSize = 600;
		}

		return settings;
	}

	/**
	 * Open gallery modal with image navigation
	 * 
	 * Opens the modal in gallery mode, displays the initial image, sets up
	 * modal size and close button visibility based on settings, and initiates
	 * image preloading if not already done.
	 *
	 * @param {Object} galleryData - Gallery data object with images array
	 * @param {Array<Object>} galleryData.images - Array of image objects
	 * @param {number} galleryData.images[].id - Image attachment ID
	 * @param {string} galleryData.images[].fullUrl - Full-size image URL
	 * @param {string} [galleryData.images[].url] - Fallback image URL
	 * @param {string} [galleryData.images[].caption] - Image caption (may contain HTML)
	 * @param {string} [galleryData.images[].alt] - Image alt text
	 * @param {string} [galleryData.closeButtons='both'] - Close button setting
	 * @param {string} [galleryData.imageNavigation='both'] - Image navigation setting
	 * @param {number} [galleryData.modalSize=600] - Modal width in pixels
	 * @param {number} initialIndex - Initial image index to display (0-based)
	 * @return {void}
	 * @throws {Error} Logs error to console if gallery data is invalid
	 * @public
	 */
	function openGalleryModal(galleryData, initialIndex) {
		if (!galleryData || !Array.isArray(galleryData.images) || galleryData.images.length === 0) {
			console.error('Synced Pattern Popups: Invalid gallery data');
			return;
		}

		if (isNaN(initialIndex) || initialIndex < 0 || initialIndex >= galleryData.images.length) {
			initialIndex = 0;
		}

		// Store gallery state
		currentGalleryData = galleryData;
		currentGalleryIndex = initialIndex;
		galleryMode = true;

		// Extract and cache settings
		var settings = getGallerySettings(galleryData);
		currentGalleryData.settings = settings;

		// Set modal size
		if (dependencies.container) {
			dependencies.container.style.maxWidth = settings.modalSize + 'px';
			if (dependencies.setMaxWidth) {
				dependencies.setMaxWidth(settings.modalSize);
			}
		}

		// Show/hide close buttons based on setting
		if (dependencies.closeBtn) {
			dependencies.closeBtn.style.display = (settings.closeButtons === 'icon' || settings.closeButtons === 'both') ? '' : 'none';
		}

		// Use common modal setup
		if (dependencies.setupModalState) {
			dependencies.setupModalState(settings.modalSize, '');
		}

		// Create container for gallery images
		var galleryContainer = document.createElement('div');
		galleryContainer.className = 'sppopups-gallery-image-container';
		if (dependencies.content) {
			dependencies.content.innerHTML = '';
			dependencies.content.appendChild(galleryContainer);
		}

		// Display initial image
		updateGalleryImage(initialIndex);

		// Focus modal container or close button for accessibility
		setTimeout(function() {
			if (dependencies.closeBtn && dependencies.focusWithoutScroll) {
				dependencies.focusWithoutScroll(dependencies.closeBtn);
			} else if (dependencies.modal && dependencies.focusWithoutScroll) {
				dependencies.focusWithoutScroll(dependencies.modal);
			}
		}, 0);

		// Images should already be preloaded from page load, but ensure this gallery's images are loading
		preloadGalleryImages(galleryData.images);
	}

	/**
	 * Navigate to previous or next image in gallery
	 * 
	 * Cycles through gallery images in the specified direction. Wraps around
	 * at the beginning/end (last image -> first image, first image -> last image).
	 *
	 * @param {string} direction - Navigation direction: 'prev' or 'next'
	 * @return {void}
	 * @public
	 */
	function navigateGallery(direction) {
		if (!currentGalleryData || !Array.isArray(currentGalleryData.images)) {
			return;
		}

		var newIndex = currentGalleryIndex;
		if (direction === 'prev') {
			newIndex = currentGalleryIndex > 0 ? currentGalleryIndex - 1 : currentGalleryData.images.length - 1;
		} else if (direction === 'next') {
			newIndex = currentGalleryIndex < currentGalleryData.images.length - 1 ? currentGalleryIndex + 1 : 0;
		}

		if (newIndex !== currentGalleryIndex) {
			updateGalleryImage(newIndex);
		}
	}

	/**
	 * Update displayed image and caption in gallery modal
	 * 
	 * Updates the modal to display the image at the specified index, including
	 * updating the modal title, handling image loading/caching, and performing
	 * smooth crossfade transitions. Respects user's reduced motion preferences.
	 *
	 * @param {number} index - Image index to display (0-based)
	 * @return {void}
	 * @private
	 */
	function updateGalleryImage(index) {
		if (!currentGalleryData || !Array.isArray(currentGalleryData.images)) {
			return;
		}

		if (index < 0 || index >= currentGalleryData.images.length) {
			return;
		}

		var image = currentGalleryData.images[index];
		currentGalleryIndex = index;

		// Update modal title with image position
		if (dependencies.getTitleElement) {
			var titleEl = dependencies.getTitleElement();
			if (titleEl) {
				titleEl.textContent = 'Image ' + (index + 1) + ' of ' + currentGalleryData.images.length;
			}
		}

		// Get settings from gallery data (use cached settings if available)
		var settings = currentGalleryData.settings || getGallerySettings(currentGalleryData);

		// Check if we should use fade transition (check for reduced motion preference)
		var prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var useTransition = !prefersReducedMotion;
		var transitionDuration = 300; // 300ms for smooth transition
		
		var imageUrl = dependencies.getImageUrl ? dependencies.getImageUrl(image) : (image.fullUrl || image.url || '');

		// Check if image is already loaded in cache
		if (galleryImageCache.loadedImages[imageUrl]) {
			// Image is ready - instant crossfade!
			crossfadeToImage(index, imageUrl, useTransition, transitionDuration, settings);
		} else if (galleryImageCache.preloadPromises[imageUrl]) {
			// Image is loading - wait for it, then crossfade
			galleryImageCache.preloadPromises[imageUrl].then(function() {
				crossfadeToImage(index, imageUrl, useTransition, transitionDuration, settings);
			}).catch(function() {
				// Preload failed, fallback to direct load
				loadAndShowImage(index, imageUrl, useTransition, transitionDuration, settings);
			});
		} else {
			// Image not preloaded (shouldn't happen, but fallback)
			loadAndShowImage(index, imageUrl, useTransition, transitionDuration, settings);
		}

		/**
		 * Crossfade from current image to new image
		 * 
		 * Creates a smooth crossfade transition between images by layering the
		 * new image on top of the old one and fading both simultaneously.
		 *
		 * @param {number} index - Image index to display
		 * @param {string} imageUrl - URL of the image to display
		 * @param {boolean} useTransition - Whether to use fade transition
		 * @param {number} transitionDuration - Transition duration in milliseconds
		 * @param {Object} settings - Gallery settings object
		 * @return {void}
		 * @private
		 */
		function crossfadeToImage(index, imageUrl, useTransition, transitionDuration, settings) {
			// Ensure gallery container exists
			var galleryContainer = dependencies.content ? dependencies.content.querySelector('.sppopups-gallery-image-container') : null;
			if (!galleryContainer) {
				galleryContainer = document.createElement('div');
				galleryContainer.className = 'sppopups-gallery-image-container';
				if (dependencies.content) {
					dependencies.content.innerHTML = '';
					dependencies.content.appendChild(galleryContainer);
				}
			}
			
			var existingWrapper = galleryContainer.querySelector('.sppopups-gallery-image-wrapper.active');
			
			if (useTransition && existingWrapper) {
				// Mark old layer as fading out
				existingWrapper.classList.remove('active');
				existingWrapper.classList.add('fading-out');
				
				// Create new layer and add it
				createImageLayer(index, imageUrl, galleryContainer, settings, function(newWrapper) {
					// New layer is created, now fade both simultaneously
					// Force reflow to ensure initial state
					newWrapper.offsetHeight;
					
					// Start fade-in animation
					newWrapper.classList.add('active');
					
					// Attach event listeners to new layer
					attachEventListeners(settings);
					
					// Clean up old layer after transition
					setTimeout(function() {
						if (existingWrapper && existingWrapper.parentNode) {
							existingWrapper.parentNode.removeChild(existingWrapper);
						}
						// Remove fading-in class after transition
						if (newWrapper) {
							newWrapper.classList.remove('fading-in');
						}
					}, transitionDuration + 50);
				});
			} else {
				// No transition, update immediately
				if (existingWrapper && existingWrapper.parentNode) {
					existingWrapper.parentNode.removeChild(existingWrapper);
				}
				updateImageContent(index, imageUrl, useTransition, transitionDuration, galleryContainer, settings);
			}
		}

		/**
		 * Load image on demand and display it
		 * 
		 * Fallback function to load an image if it wasn't preloaded. Caches
		 * the loaded image for future use, then triggers crossfade.
		 *
		 * @param {number} index - Image index to display
		 * @param {string} imageUrl - URL of the image to load
		 * @param {boolean} useTransition - Whether to use fade transition
		 * @param {number} transitionDuration - Transition duration in milliseconds
		 * @param {Object} settings - Gallery settings object
		 * @return {void}
		 * @private
		 */
		function loadAndShowImage(index, imageUrl, useTransition, transitionDuration, settings) {
			var galleryContainer = dependencies.content ? dependencies.content.querySelector('.sppopups-gallery-image-container') : null;
			var img = new Image();
			
			img.onload = function() {
				// Cache the loaded image
				galleryImageCache.loadedImages[imageUrl] = true;
				galleryImageCache.imageElements[imageUrl] = img;
				
				// Now crossfade
				crossfadeToImage(index, imageUrl, useTransition, transitionDuration, settings);
			};
			
			img.onerror = function() {
				// Even if image fails to load, still update (will show broken image)
				updateImageContent(index, imageUrl, useTransition, transitionDuration, galleryContainer, settings);
			};
			
			img.src = imageUrl;
		}

		/**
		 * Create a new image layer for crossfade transition
		 * 
		 * Creates a new image wrapper element with the image and optional
		 * navigation buttons. Updates container height when image loads and
		 * calls the callback when ready.
		 *
		 * @param {number} index - Image index
		 * @param {string} imageUrl - URL of the image
		 * @param {HTMLElement} galleryContainer - Gallery container element
		 * @param {Object} settings - Gallery settings object
		 * @param {Function} callback - Callback function called with new wrapper element
		 * @return {void}
		 * @private
		 */
		function createImageLayer(index, imageUrl, galleryContainer, settings, callback) {
			var image = currentGalleryData.images[index];
			
			// Create new wrapper layer
			var newWrapper = document.createElement('div');
			newWrapper.className = 'sppopups-gallery-image-wrapper fading-in';
			
			// Create image element
			var img = document.createElement('img');
			img.src = imageUrl;
			img.alt = image.alt || '';
			img.className = 'sppopups-gallery-image';
			
			// Wait for image to load to get dimensions
			img.onload = function() {
				// Update container min-height based on image dimensions
				updateContainerHeight(galleryContainer, img);
				
				// Add navigation buttons if needed
				if (settings.imageNavigation === 'image' || settings.imageNavigation === 'both') {
					// Previous button
					var prevBtn = document.createElement('button');
					prevBtn.className = 'sppopups-gallery-nav sppopups-gallery-nav--prev';
					prevBtn.setAttribute('aria-label', 'Previous image');
					prevBtn.setAttribute('type', 'button');
					prevBtn.setAttribute('title', 'Previous image (Left Arrow)');
					prevBtn.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
					
					// Next button
					var nextBtn = document.createElement('button');
					nextBtn.className = 'sppopups-gallery-nav sppopups-gallery-nav--next';
					nextBtn.setAttribute('aria-label', 'Next image');
					nextBtn.setAttribute('type', 'button');
					nextBtn.setAttribute('title', 'Next image (Right Arrow)');
					nextBtn.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
					
					newWrapper.appendChild(prevBtn);
					newWrapper.appendChild(nextBtn);
				}
				
				newWrapper.insertBefore(img, newWrapper.firstChild);
				galleryContainer.appendChild(newWrapper);
				
				// Callback with new wrapper
				if (callback) {
					callback(newWrapper);
				}
			};
			
			img.onerror = function() {
				// Even if image fails, still add it
				newWrapper.insertBefore(img, newWrapper.firstChild);
				galleryContainer.appendChild(newWrapper);
				if (callback) {
					callback(newWrapper);
				}
			};
			
			// If image is already cached, onload might not fire
			if (galleryImageCache.loadedImages[imageUrl] && img.complete) {
				img.onload();
			}
		}

		/**
		 * Update gallery container height to match image aspect ratio
		 * 
		 * Calculates the container height based on the image's natural aspect
		 * ratio and the modal's width, ensuring the image fits exactly with
		 * no whitespace. Respects max-height of 80vh.
		 *
		 * @param {HTMLElement} galleryContainer - Gallery container element
		 * @param {HTMLImageElement} img - Image element with loaded dimensions
		 * @return {void}
		 * @private
		 */
		function updateContainerHeight(galleryContainer, img) {
			if (!galleryContainer || !img) return;
			
			// Cache modal container reference
			if (!dependencies.modalContainer || !dependencies.modal || !dependencies.modal.contains(dependencies.modalContainer)) {
				dependencies.modalContainer = galleryContainer.closest('.sppopups-container');
			}
			
			// Use requestAnimationFrame for better timing than setTimeout
			requestAnimationFrame(function() {
				// Use natural dimensions to calculate exact container size
				var imgHeight = img.naturalHeight || img.height || 0;
				var imgWidth = img.naturalWidth || img.width || 0;
				
				if (imgHeight > 0 && imgWidth > 0) {
					// Get the modal container width (the actual max-width setting)
					var containerWidth = dependencies.modalContainer ? (dependencies.modalContainer.offsetWidth || dependencies.modalContainer.clientWidth) : 600;
					
					// Calculate aspect ratio
					var aspectRatio = imgHeight / imgWidth;
					
					// Calculate height based on container width and image aspect ratio
					var calculatedHeight = containerWidth * aspectRatio;
					var maxHeight = window.innerHeight * 0.8; // 80vh max
					
					// Use the smaller of calculated height or max height
					var finalHeight = Math.min(calculatedHeight, maxHeight);
					
					// Set height exactly to match image aspect ratio (ensure minimum 200px)
					galleryContainer.style.height = Math.max(finalHeight, 200) + 'px';
					galleryContainer.style.minHeight = Math.max(finalHeight, 200) + 'px';
				}
			});
		}

		/**
		 * Update image content without transition (immediate update)
		 * 
		 * Updates the gallery to show a new image immediately without
		 * crossfade transition. Removes all existing layers and creates
		 * a new active layer.
		 *
		 * @param {number} index - Image index to display
		 * @param {string} imageUrl - URL of the image
		 * @param {boolean} useTransition - Whether to use transition (unused in this function)
		 * @param {number} transitionDuration - Transition duration (unused in this function)
		 * @param {HTMLElement} galleryContainer - Gallery container element
		 * @param {Object} settings - Gallery settings object
		 * @return {void}
		 * @private
		 */
		function updateImageContent(index, imageUrl, useTransition, transitionDuration, galleryContainer, settings) {
			var image = currentGalleryData.images[index];
			
			// Check if gallery container exists, create if not
			if (!galleryContainer) {
				galleryContainer = dependencies.content ? dependencies.content.querySelector('.sppopups-gallery-image-container') : null;
			}
			
			if (!galleryContainer) {
				galleryContainer = document.createElement('div');
				galleryContainer.className = 'sppopups-gallery-image-container';
				if (dependencies.content) {
					dependencies.content.innerHTML = '';
					dependencies.content.appendChild(galleryContainer);
				}
			} else {
				// Remove all existing layers
				var existingLayers = galleryContainer.querySelectorAll('.sppopups-gallery-image-wrapper');
				existingLayers.forEach(function(layer) {
					layer.parentNode.removeChild(layer);
				});
			}
			
			// Create new layer (non-transition version)
			createImageLayer(index, imageUrl, galleryContainer, settings, function(newWrapper) {
				newWrapper.classList.add('active');
				attachEventListeners(settings);
			});
		}

		/**
		 * Attach event listeners to gallery footer elements
		 * 
		 * Updates the modal footer with navigation buttons, caption, and close
		 * button based on settings. Attaches click handlers to footer navigation
		 * buttons and close button.
		 *
		 * @param {Object} settings - Gallery settings object
		 * @param {string} settings.imageNavigation - Navigation location: 'image', 'footer', or 'both'
		 * @param {string} settings.closeButtons - Close button setting: 'icon', 'button', or 'both'
		 * @return {void}
		 * @private
		 */
		function attachEventListeners(settings) {
			// Update footer with navigation and caption
			var footer = dependencies.modal ? dependencies.modal.querySelector('.sppopups-footer') : null;
			if (footer) {
				var image = currentGalleryData.images[currentGalleryIndex];
				
				// Use template literals for cleaner HTML building
				var navGroup = '';
				if (settings.imageNavigation === 'footer' || settings.imageNavigation === 'both') {
					navGroup = `<div class="sppopups-gallery-nav-group">
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
				var prevFooterBtn = footer.querySelector('.sppopups-gallery-nav-footer--prev');
				var nextFooterBtn = footer.querySelector('.sppopups-gallery-nav-footer--next');
				var closeFooterBtn = footer.querySelector('.sppopups-close-footer');
				
				if (prevFooterBtn) {
					prevFooterBtn.addEventListener('click', function() {
						navigateGallery('prev');
					});
				}
				
				if (nextFooterBtn) {
					nextFooterBtn.addEventListener('click', function() {
						navigateGallery('next');
					});
				}
				
				// Attach event listener to close button
				if (closeFooterBtn && dependencies.closeModal) {
					closeFooterBtn.addEventListener('click', function(e) {
						e.preventDefault();
						e.stopPropagation();
						dependencies.closeModal();
					});
				}
			}

			// Navigation buttons on images are handled by event delegation
			// (set up in init), so no need to attach listeners here

			// Images should already be preloaded, but ensure they're loading
			if (currentGalleryData && currentGalleryData.images) {
				preloadGalleryImages(currentGalleryData.images);
			}
		}
	}

	/**
	 * Preload all images for a gallery (called on page load)
	 * 
	 * Preloads all images in a gallery in the background to enable instant
	 * crossfade transitions when navigating. Images are cached for reuse
	 * across multiple gallery opens. Skips images that are already loaded
	 * or currently loading.
	 *
	 * @param {Array<Object>} images - Array of image objects
	 * @param {string} images[].fullUrl - Full-size image URL
	 * @param {string} [images[].url] - Fallback image URL
	 * @return {void}
	 * @private
	 */
	function preloadGalleryImages(images) {
		if (!images || !Array.isArray(images)) {
			return;
		}

		images.forEach(function(image) {
			var imageUrl = dependencies.getImageUrl ? dependencies.getImageUrl(image) : (image.fullUrl || image.url || '');
			if (!imageUrl) {
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
			var preloadPromise = new Promise(function(resolve, reject) {
				var img = new Image();

				img.onload = function() {
					galleryImageCache.loadedImages[imageUrl] = true;
					galleryImageCache.imageElements[imageUrl] = img;
					delete galleryImageCache.loadingImages[imageUrl];
					resolve(img);
				};

				img.onerror = function() {
					delete galleryImageCache.loadingImages[imageUrl];
					reject(new Error('Failed to load image: ' + imageUrl));
				};

				// Start loading (browser will handle priority)
				img.src = imageUrl;
			});

			galleryImageCache.preloadPromises[imageUrl] = preloadPromise;
		});
	}

	/**
	 * Scan page for galleries and preload their images
	 * 
	 * Scans the page for all gallery elements with `data-sppopup-gallery="true"`
	 * and preloads all images for each gallery found. This runs on page load
	 * to improve gallery navigation performance.
	 *
	 * @return {void}
	 * @private
	 */
	function scanAndPreloadGalleries() {
		// Find all galleries on the page
		var galleries = document.querySelectorAll('[data-sppopup-gallery="true"]');

		galleries.forEach(function(gallery) {
			var galleryDataAttr = gallery.getAttribute('data-gallery-data');
			if (galleryDataAttr) {
				try {
					var galleryData = JSON.parse(galleryDataAttr);
					if (galleryData && Array.isArray(galleryData.images)) {
						// Start preloading all images for this gallery
						preloadGalleryImages(galleryData.images);
					}
				} catch (err) {
					console.error('Synced Pattern Popups: Error parsing gallery data for preload:', err);
				}
			}
		});
	}

	/**
	 * Reset gallery state (called when modal closes)
	 * 
	 * Clears all gallery state variables, resetting the module to its
	 * initial state. Called by modal.js when the modal is closed.
	 *
	 * @return {void}
	 * @public
	 */
	function resetGalleryState() {
		currentGalleryData = null;
		currentGalleryIndex = 0;
		galleryMode = false;
	}

	/**
	 * Check if gallery mode is currently active
	 * 
	 * Returns whether the modal is currently displaying a gallery.
	 * Used by modal.js to determine if keyboard navigation should
	 * be handled by the gallery module.
	 *
	 * @return {boolean} True if gallery mode is active, false otherwise
	 * @public
	 */
	function isGalleryMode() {
		return galleryMode;
	}

	/**
	 * Public API exported to window.sppopupsGallery
	 * 
	 * @namespace sppopupsGallery
	 * @property {Function} init - Initialize gallery module with dependencies
	 * @property {Function} openGalleryModal - Open gallery modal with image navigation
	 * @property {Function} navigateGallery - Navigate to previous or next image
	 * @property {Function} resetGalleryState - Reset gallery state on modal close
	 * @property {Function} isGalleryMode - Check if gallery mode is active
	 */
	window.sppopupsGallery = {
		init: init,
		openGalleryModal: openGalleryModal,
		navigateGallery: navigateGallery,
		resetGalleryState: resetGalleryState,
		isGalleryMode: isGalleryMode
	};
})();
