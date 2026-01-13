/**
 * Gallery Block Editor Extension
 * Adds "Open in Synced Pattern Popups" link option to Gallery block toolbar dropdown
 */
(function() {
	'use strict';

	if (typeof wp === 'undefined' || !wp.hooks || !wp.blocks) {
		return;
	}

	var addFilter = wp.hooks.addFilter;
	var __ = wp.i18n.__;
	var createElement = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var SelectControl = wp.components.SelectControl;

	/**
	 * Extend Gallery block attributes to include sppopup modal size
	 */
	addFilter(
		'blocks.registerBlockType',
		'sppopups/gallery-extend-attributes',
		function(settings, name) {
			if (name !== 'core/gallery') {
				return settings;
			}

			// Add sppopupModalSize attribute
			if (!settings.attributes) {
				settings.attributes = {};
			}

			settings.attributes.sppopupModalSize = {
				type: 'string',
				default: '600'
			};

			settings.attributes.sppopupCloseButtons = {
				type: 'string',
				default: 'both'
			};

			settings.attributes.sppopupImageNavigation = {
				type: 'string',
				default: 'both'
			};

			return settings;
		}
	);

	/**
	 * Filter block inspector controls to hide linkTarget when linkTo is 'sppopup'
	 * This mimics the behavior when linkTo is 'none'
	 * The Gallery block uses hasLinkTo = linkTo && linkTo !== "none" to show/hide linkTarget
	 */
	addFilter(
		'editor.BlockEdit',
		'sppopups/gallery-hide-linktarget',
		function(BlockEdit) {
			return function(props) {
				var element = wp.element.createElement(BlockEdit, props);

				// Only process Gallery blocks
				if (props.name !== 'core/gallery') {
					return element;
				}

				// Manage linkTarget control visibility based on linkTo value
				wp.element.useEffect(function() {
					var currentLinkTo = props.attributes && props.attributes.linkTo ? props.attributes.linkTo : null;
					var shouldHide = currentLinkTo === 'sppopup';
					
					// Cache inspector area and target element to reduce DOM queries
					var inspectorArea = null;
					var targetElement = null;
					var debounceTimer = null;
					
					// Function to show/hide the linkTarget toggle (debounced)
					var toggleLinkTarget = function() {
						// Debounce to prevent excessive calls
						if (debounceTimer) {
							clearTimeout(debounceTimer);
						}
						debounceTimer = setTimeout(function() {
							// Cache inspector area if not already cached
							if (!inspectorArea) {
								inspectorArea = document.querySelector('.block-editor-block-inspector');
							}
							if (!inspectorArea) {
								return;
							}

							// Only query if target element not cached or inspector changed
							if (!targetElement || !inspectorArea.contains(targetElement)) {
								// Find toggle control with "new tab" label (more targeted query)
								var toggles = inspectorArea.querySelectorAll('.components-toggle-control, .components-base-control');
								targetElement = null;
								
								for (var i = 0; i < toggles.length; i++) {
									var toggle = toggles[i];
									var label = toggle.querySelector('label, .components-base-control__label');
									if (label) {
										var labelText = (label.textContent || '').trim().toLowerCase();
										// Check for various translations of "Open images in new tab"
										if (labelText.indexOf('new tab') !== -1 ||
										    labelText.indexOf('open images') !== -1 ||
										    (labelText.indexOf('tab') !== -1 && labelText.indexOf('open') !== -1)) {
											// Find the control element to modify
											var control = toggle.closest('.components-base-control, .components-panel__body');
											targetElement = control || toggle;
											break;
										}
									}
								}
							}
							
							if (targetElement) {
								if (shouldHide) {
									// Hide when sppopup is active
									targetElement.style.display = 'none';
								} else {
									// Remove inline style to let Core control visibility
									targetElement.style.display = '';
								}
							}
						}, 100); // 100ms debounce
					};

					// Run immediately
					toggleLinkTarget();
					
					// Watch for inspector changes (when sidebar updates) - debounced
					var observer = new MutationObserver(function() {
						// Reset cache when DOM changes
						targetElement = null;
						toggleLinkTarget();
					});

					inspectorArea = document.querySelector('.block-editor-block-inspector');
					if (inspectorArea) {
						observer.observe(inspectorArea, {
							childList: true,
							subtree: true
						});
					}

					return function() {
						if (debounceTimer) {
							clearTimeout(debounceTimer);
						}
						if (observer) {
							observer.disconnect();
						}
					};
				}, [props.attributes.linkTo, props.isSelected]);

				return element;
			};
		}
	);

	/**
	 * Add Synced Pattern Popups settings panel to Gallery block inspector
	 */
	addFilter(
		'editor.BlockEdit',
		'sppopups/gallery-inspector-controls',
		function(BlockEdit) {
			return function(props) {
				var element = wp.element.createElement(BlockEdit, props);

				// Only process Gallery blocks
				if (props.name !== 'core/gallery') {
					return element;
				}

					// Only show settings when linkTo is 'sppopup'
					if (props.attributes && props.attributes.linkTo === 'sppopup') {
						var modalSize = props.attributes.sppopupModalSize || '600';
						var closeButtons = props.attributes.sppopupCloseButtons || 'both';
						var imageNavigation = props.attributes.sppopupImageNavigation || 'both';
						
						// Remove 'px' if present for the input value
						var modalSizeValue = modalSize.toString().replace('px', '').trim();
						
						var handleModalSizeChange = function(value) {
							// Remove any non-numeric characters except for the number
							var numericValue = value.toString().replace(/[^0-9]/g, '');
							if (numericValue && props.setAttributes) {
								props.setAttributes({ sppopupModalSize: numericValue });
							}
						};

						var handleCloseButtonsChange = function(value) {
							if (props.setAttributes) {
								props.setAttributes({ sppopupCloseButtons: value });
							}
						};

						var handleImageNavigationChange = function(value) {
							if (props.setAttributes) {
								props.setAttributes({ sppopupImageNavigation: value });
							}
						};

						// Create the inspector controls
						var inspectorControls = createElement(
							InspectorControls,
							{},
							createElement(
								PanelBody,
								{
									title: __('Synced Pattern Popups', 'synced-pattern-popups'),
									initialOpen: true
								},
								createElement(
									TextControl,
									{
										label: __('Modal Size', 'synced-pattern-popups'),
										value: modalSizeValue,
										onChange: handleModalSizeChange,
										type: 'number',
										min: 100,
										max: 2000,
										step: 10,
										help: __('Width in pixels', 'synced-pattern-popups')
									}
								),
							createElement(
								SelectControl
								, {
									label: __('Close Buttons', 'synced-pattern-popups'),
									value: closeButtons,
									onChange: handleCloseButtonsChange,
									options: [
										{ label: __('X icon', 'synced-pattern-popups'), value: 'icon' },
										{ label: __('Close Button', 'synced-pattern-popups'), value: 'button' },
										{ label: __('Both', 'synced-pattern-popups'), value: 'both' }
									]
								}
							),
							createElement(
								SelectControl
								, {
									label: __('Image Navigation', 'synced-pattern-popups'),
									value: imageNavigation,
									onChange: handleImageNavigationChange,
									options: [
										{ label: __('On Image', 'synced-pattern-popups'), value: 'image' },
										{ label: __('In Footer', 'synced-pattern-popups'), value: 'footer' },
										{ label: __('Both', 'synced-pattern-popups'), value: 'both' }
									]
								}
							)
						)
					);

					// Wrap the element with Fragment to include inspector controls
					return createElement(
						Fragment,
						{},
						element,
						inspectorControls
					);
				}

				return element;
			};
		}
	);

	/**
	 * Filter block edit to add linkTo option to toolbar dropdown
	 * The Gallery block's linkTo control is in the toolbar, not the sidebar
	 */
	addFilter(
		'editor.BlockEdit',
		'sppopups/gallery-link-option-edit',
		function(BlockEdit) {
			return function(props) {
				var element = wp.element.createElement(BlockEdit, props);

				// Only process Gallery blocks
				if (props.name !== 'core/gallery') {
					return element;
				}

				// Use useEffect to add linkTo option to toolbar dropdown
				wp.element.useEffect(function() {
					if (!props.isSelected) {
						return;
					}

					var observer = null;
					var documentObserver = null;
					var timeoutId = null;
					var debounceTimer = null;
					var addedToDropdown = false;
					var cachedPopovers = null;

					function addSppopupOptionToToolbar() {
						// Debounce to prevent excessive calls
						if (debounceTimer) {
							clearTimeout(debounceTimer);
						}
						debounceTimer = setTimeout(function() {
							// Cache visible popovers query
							if (!cachedPopovers) {
								cachedPopovers = document.querySelectorAll('.components-popover');
							}
							
							// Filter to only visible popovers
							var visiblePopovers = [];
							for (var i = 0; i < cachedPopovers.length; i++) {
								var popover = cachedPopovers[i];
								var style = window.getComputedStyle(popover);
								if (style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0') {
									visiblePopovers.push(popover);
								}
							}
							
							if (visiblePopovers.length === 0) {
								return;
							}
							
							visiblePopovers.forEach(function(popover) {
								// Look for menu items - more targeted selector
								var menuItems = popover.querySelectorAll('[role="menuitem"], .components-menu-item__button');
							
								if (menuItems.length === 0) {
									return;
								}

								// Check if this menu contains linkTo-related items
								// Based on screenshot: "Link images to attachment pages", "Link images to media files", "Enlarge on click", "None"
								var hasLinkToItems = false;
								var linkToMenuItems = [];
								var menuContainer = null;
								
								menuItems.forEach(function(item) {
									var text = (item.textContent || '').trim();
									// Check for Gallery linkTo menu items
									if (text.indexOf('Link images to') !== -1 || 
										text.indexOf('Link to') !== -1 ||
										text === 'None' ||
										text.indexOf('Enlarge on click') !== -1 ||
										text.indexOf('Scale images') !== -1 ||
										text.indexOf('lightbox') !== -1) {
										hasLinkToItems = true;
										linkToMenuItems.push(item);
										// Find the menu container (usually a ul or div with role="menu")
										if (!menuContainer) {
											var parent = item.parentNode;
											while (parent && parent !== popover) {
												if (parent.getAttribute('role') === 'menu' || 
													parent.classList.contains('components-menu-group') ||
													parent.classList.contains('components-dropdown-menu__menu')) {
													menuContainer = parent;
													break;
												}
												parent = parent.parentNode;
											}
											// Fallback to item's direct parent
											if (!menuContainer && item.parentNode) {
												menuContainer = item.parentNode;
											}
										}
									}
								});

								// If we found linkTo menu items, add our option
								if (hasLinkToItems && menuContainer) {
									// Find existing sppopup option if it exists
									var existingSppopupItem = null;
									menuItems.forEach(function(item) {
										var text = (item.textContent || '').trim();
										if (text.indexOf('Open in Synced Pattern Popups') !== -1 || 
											text.indexOf('Synced Pattern Popups') !== -1) {
											existingSppopupItem = item;
										}
									});

									// Check current linkTo value to determine if our option should be active
									var currentLinkTo = props.attributes && props.attributes.linkTo ? props.attributes.linkTo : null;
									var isSppopupActive = currentLinkTo === 'sppopup';

									if (!existingSppopupItem) {
										// Find the "None" menu item to insert before it
										var noneItem = null;
										linkToMenuItems.forEach(function(item) {
											var text = (item.textContent || '').trim();
											if (text === 'None') {
												noneItem = item;
											}
										});

										// Clone structure from existing menu item to match WordPress styling exactly
										var templateItem = linkToMenuItems.length > 0 ? linkToMenuItems[0] : menuItems[0];
										var newMenuItem = templateItem.cloneNode(false); // Clone without children to get structure
									
										// Get the template's inner structure to match it exactly
										var templateInnerHTML = templateItem.innerHTML;
										var templateChildren = templateItem.children;
										
										// aspectRatio icon SVG from @wordpress/icons
										// Exact SVG pulled from Gutenberg Storybook
										var aspectRatioIconSVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M18.5 5.5h-13c-1.1 0-2 .9-2 2v9c0 1.1.9 2 2 2h13c1.1 0 2-.9 2-2v-9c0-1.1-.9-2-2-2zm.5 11c0 .3-.2.5-.5.5h-13c-.3 0-.5-.2-.5-.5v-9c0-.3.2-.5.5-.5h13c.3 0 .5.2.5.5v9zM6.5 12H8v-2h2V8.5H6.5V12zm9.5 2h-2v1.5h3.5V12H16v2z"></path></svg>';
										
										// Check if template has icon structure (look for SVG or icon wrapper)
										var hasIconStructure = templateInnerHTML.indexOf('<svg') !== -1 || 
										                       templateItem.querySelector('svg') !== null ||
										                       (templateChildren.length > 0 && templateChildren[0].querySelector && templateChildren[0].querySelector('svg'));
										
										if (hasIconStructure) {
											// Template has icon + text structure - match it exactly
											// Look at first child to see structure
											var firstChild = templateItem.firstElementChild;
											if (firstChild && firstChild.classList.contains('components-menu-item__item')) {
												// Structure: <span class="components-menu-item__item"><svg>...</svg></span><span class="components-menu-item__item">Text</span>
												var iconWrapper = document.createElement('span');
												iconWrapper.className = 'components-menu-item__item';
												iconWrapper.innerHTML = aspectRatioIconSVG;
												
												var textWrapper = document.createElement('span');
												textWrapper.className = 'components-menu-item__item';
												textWrapper.textContent = __('Open in Synced Pattern Popups', 'synced-pattern-popups');
												
												newMenuItem.appendChild(iconWrapper);
												newMenuItem.appendChild(textWrapper);
											} else {
												// Different structure - try to match what's there
												newMenuItem.innerHTML = aspectRatioIconSVG + '<span class="components-menu-item__item">' + __('Open in Synced Pattern Popups', 'synced-pattern-popups') + '</span>';
											}
										} else {
											// No icon in template, but we want one - add it with proper structure
											var iconWrapper = document.createElement('span');
											iconWrapper.className = 'components-menu-item__item';
											iconWrapper.innerHTML = aspectRatioIconSVG;
											
											var textWrapper = document.createElement('span');
											textWrapper.className = 'components-menu-item__item';
											textWrapper.textContent = __('Open in Synced Pattern Popups', 'synced-pattern-popups');
											
											newMenuItem.appendChild(iconWrapper);
											newMenuItem.appendChild(textWrapper);
										}
										
										// Ensure proper classes and attributes match template
										newMenuItem.className = templateItem.className || 'components-menu-item__button';
										
										// Add is-active class if linkTo is 'sppopup' (this gives the icon a black background)
										if (isSppopupActive) {
											if (newMenuItem.classList) {
												newMenuItem.classList.add('is-active');
											} else {
												newMenuItem.className += ' is-active';
											}
										}
										
										if (!newMenuItem.getAttribute('role')) {
											newMenuItem.setAttribute('role', 'menuitem');
										}
										if (!newMenuItem.getAttribute('type')) {
											newMenuItem.setAttribute('type', 'button');
										}
										
										// Copy any other attributes from template
										if (templateItem.attributes) {
											for (var i = 0; i < templateItem.attributes.length; i++) {
												var attr = templateItem.attributes[i];
												if (attr.name !== 'class' && attr.name !== 'role' && attr.name !== 'type') {
													newMenuItem.setAttribute(attr.name, attr.value);
												}
											}
										}
										
										// Add click handler to set linkTo attribute
										newMenuItem.addEventListener('click', function(e) {
											e.preventDefault();
											e.stopPropagation();
											
											// Get WordPress block editor store
											var blockEditorStore = wp.data.select('core/block-editor');
											var blockEditorDispatch = wp.data.dispatch('core/block-editor');
											var noticesStore = wp.data.dispatch('core/notices');
											
											// Set the linkTo attribute to 'sppopup' at block level
											if (props.setAttributes) {
												props.setAttributes({ linkTo: 'sppopup' });
											}
											
											// Update all innerBlocks (image blocks) to clear lightbox and set linkDestination
											// This mimics how the Gallery block's setLinkTo function works
											if (props.clientId && blockEditorStore && blockEditorStore.getBlock) {
												var galleryBlock = blockEditorStore.getBlock(props.clientId);
												if (galleryBlock && galleryBlock.innerBlocks && galleryBlock.innerBlocks.length > 0) {
													galleryBlock.innerBlocks.forEach(function(imageBlock) {
														if (imageBlock && imageBlock.name === 'core/image') {
															// Clear lightbox settings by setting linkDestination to 'none'
															// This prevents lightbox from showing when our popup is active
															var imageAttributes = {
																linkDestination: 'none'
															};
															
															// Update the image block attributes
															if (blockEditorDispatch && blockEditorDispatch.updateBlockAttributes) {
																blockEditorDispatch.updateBlockAttributes(imageBlock.clientId, imageAttributes);
															}
														}
													});
												}
											}
											
											// Show status notice like other Gallery linkTo options
											// Format: "All gallery image links updated to: [option label]"
											if (noticesStore && noticesStore.createSuccessNotice) {
												var optionLabel = __('Open in Synced Pattern Popups', 'synced-pattern-popups');
												var noticeText = __('All gallery image links updated to: %s', 'default');
												
												// Replace %s with the option label
												if (noticeText.indexOf('%s') !== -1) {
													noticeText = noticeText.replace('%s', optionLabel);
												} else {
													// Fallback if translation doesn't have %s
													noticeText = __('All gallery image links updated to: ', 'default') + optionLabel;
												}
												
												noticesStore.createSuccessNotice(noticeText, {
													id: 'gallery-attributes-linkTo',
													type: 'snackbar'
												});
											}
											
											// Close the dropdown
											setTimeout(function() {
												// Try multiple methods to close
												var overlay = document.querySelector('.components-popover__overlay');
												if (overlay) {
													overlay.click();
												} else {
													// Try ESC key simulation
													var escEvent = new KeyboardEvent('keydown', {
														key: 'Escape',
														code: 'Escape',
														keyCode: 27,
														bubbles: true
													});
													document.dispatchEvent(escEvent);
												}
											}, 10);
										});

										// Insert before "None" if found, otherwise append
										if (noneItem && noneItem.parentNode === menuContainer) {
											menuContainer.insertBefore(newMenuItem, noneItem);
										} else if (menuContainer) {
											menuContainer.appendChild(newMenuItem);
										}

										addedToDropdown = true;
									} else if (existingSppopupItem) {
										// Update existing item's active state
										if (isSppopupActive) {
											if (existingSppopupItem.classList) {
												existingSppopupItem.classList.add('is-active');
											} else if (existingSppopupItem.className.indexOf('is-active') === -1) {
												existingSppopupItem.className += ' is-active';
											}
										} else {
											if (existingSppopupItem.classList) {
												existingSppopupItem.classList.remove('is-active');
											} else {
												existingSppopupItem.className = existingSppopupItem.className.replace(/\bis-active\b/g, '');
											}
										}
									}
								}
							});
						}, 150); // Debounce 150ms
					}

					// Try immediately
					timeoutId = setTimeout(addSppopupOptionToToolbar, 200);

					// Watch for toolbar changes (when dropdown opens/closes)
					var toolbarArea = document.querySelector('.block-editor-block-toolbar, .block-editor-block-contextual-toolbar');
					if (toolbarArea) {
						observer = new MutationObserver(function() {
							addedToDropdown = false; // Reset flag when DOM changes
							cachedPopovers = null; // Reset cache
							clearTimeout(timeoutId);
							timeoutId = setTimeout(addSppopupOptionToToolbar, 100);
						});

						observer.observe(toolbarArea, {
							childList: true,
							subtree: true
						});
					}

					// Watch document for popover visibility changes (debounced)
					documentObserver = new MutationObserver(function() {
						// Reset cache when DOM changes
						cachedPopovers = null;
						addedToDropdown = false;
						clearTimeout(timeoutId);
						timeoutId = setTimeout(addSppopupOptionToToolbar, 100);
					});

					documentObserver.observe(document.body, {
						childList: true,
						subtree: true,
						attributes: true,
						attributeFilter: ['style', 'class']
					});

					return function() {
						clearTimeout(timeoutId);
						if (debounceTimer) {
							clearTimeout(debounceTimer);
						}
						if (observer) {
							observer.disconnect();
						}
						if (documentObserver) {
							documentObserver.disconnect();
						}
					};
				}, [props.isSelected, props.clientId, props.attributes.linkTo]);

				return element;
			};
		}
	);

})();
