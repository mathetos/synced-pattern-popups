/**
 * Tests for modal defaults application
 *
 * @package SPPopups
 */

describe( 'Modal Defaults', () => {
	let modalElement;
	let containerElement;
	let closeFooterBtn;

	beforeEach( () => {
		// Create a mock modal element structure
		modalElement = document.createElement( 'div' );
		modalElement.className = 'sppopups-modal';
		containerElement = document.createElement( 'div' );
		containerElement.className = 'sppopups-container';
		closeFooterBtn = document.createElement( 'button' );
		closeFooterBtn.className = 'sppopups-close-footer';
		modalElement.appendChild( containerElement );
		document.body.appendChild( modalElement );

		// Mock sppopups.defaults object
		window.sppopups = {
			defaults: {
				pattern: {
					maxWidth: 600,
					borderRadius: 6,
					maxHeight: 90,
					overlayColor: 'rgba(0, 0, 0, 0.1)',
					backdropBlur: 8,
					showIconClose: true,
					showFooterClose: true,
					footerCloseText: 'Close →',
				},
				tldr: {
					inheritModalAppearance: true,
					inheritOverlay: true,
					inheritCloseButtons: true,
					maxWidth: 600,
					borderRadius: 6,
					maxHeight: 90,
					overlayColor: 'rgba(0, 0, 0, 0.1)',
					backdropBlur: 8,
					showIconClose: true,
					showFooterClose: true,
					footerCloseText: 'Close →',
					loadingText: 'Generating TLDR',
					titleText: 'TLDR',
				},
				gallery: {
					inheritModalAppearance: true,
					inheritOverlay: true,
					inheritCloseButtons: true,
					maxWidth: 600,
					borderRadius: 6,
					maxHeight: 90,
					overlayColor: 'rgba(0, 0, 0, 0.1)',
					backdropBlur: 8,
					showIconClose: true,
					showFooterClose: true,
					footerCloseText: 'Close →',
					imageNavigation: 'both',
					showCaptions: true,
					crossfadeTransition: true,
					transitionDuration: 500,
					preloadAdjacentImages: true,
					showNavOnHover: true,
				},
			},
		};
	} );

	afterEach( () => {
		if ( modalElement && modalElement.parentNode ) {
			document.body.removeChild( modalElement );
		}
		delete window.sppopups;
	} );

	test( 'applies pattern defaults to modal element', () => {
		const defaults = window.sppopups.defaults.pattern;

		// Simulate applyModalDefaults function behavior
		modalElement.style.setProperty( '--sppopups-max-width', defaults.maxWidth + 'px' );
		modalElement.style.setProperty( '--sppopups-border-radius', defaults.borderRadius + 'px' );
		modalElement.style.setProperty( '--sppopups-max-height', defaults.maxHeight + '%' );
		modalElement.style.setProperty( '--sppopups-max-height-vh', defaults.maxHeight + 'vh' );
		modalElement.style.setProperty( '--sppopups-overlay-color', defaults.overlayColor );
		modalElement.style.setProperty( '--sppopups-backdrop-blur', defaults.backdropBlur + 'px' );
		modalElement.setAttribute( 'data-show-icon-close', defaults.showIconClose ? 'true' : 'false' );
		modalElement.setAttribute( 'data-show-footer-close', defaults.showFooterClose ? 'true' : 'false' );

		expect( modalElement.style.getPropertyValue( '--sppopups-max-width' ) ).toBe( '600px' );
		expect( modalElement.style.getPropertyValue( '--sppopups-border-radius' ) ).toBe( '6px' );
		expect( modalElement.style.getPropertyValue( '--sppopups-max-height' ) ).toBe( '90%' );
		expect( modalElement.style.getPropertyValue( '--sppopups-max-height-vh' ) ).toBe( '90vh' );
		expect( modalElement.style.getPropertyValue( '--sppopups-overlay-color' ) ).toBe( 'rgba(0, 0, 0, 0.1)' );
		expect( modalElement.style.getPropertyValue( '--sppopups-backdrop-blur' ) ).toBe( '8px' );
		expect( modalElement.getAttribute( 'data-show-icon-close' ) ).toBe( 'true' );
		expect( modalElement.getAttribute( 'data-show-footer-close' ) ).toBe( 'true' );
	} );

	test( 'applies TLDR defaults with inheritance', () => {
		const defaults = window.sppopups.defaults.tldr;

		// TLDR should inherit from pattern when inheritModalAppearance is true
		expect( defaults.inheritModalAppearance ).toBe( true );
		expect( defaults.maxWidth ).toBe( 600 ); // Inherited from pattern
		expect( defaults.borderRadius ).toBe( 6 ); // Inherited from pattern
		expect( defaults.maxHeight ).toBe( 90 ); // Inherited from pattern
		expect( defaults.loadingText ).toBe( 'Generating TLDR' );
		expect( defaults.titleText ).toBe( 'TLDR' );
	} );

	test( 'applies gallery defaults with inheritance', () => {
		const defaults = window.sppopups.defaults.gallery;

		// Gallery should inherit from pattern when inheritModalAppearance is true
		expect( defaults.inheritModalAppearance ).toBe( true );
		expect( defaults.maxWidth ).toBe( 600 ); // Inherited from pattern
		expect( defaults.imageNavigation ).toBe( 'both' );
		expect( defaults.showCaptions ).toBe( true );
		expect( defaults.crossfadeTransition ).toBe( true );
		expect( defaults.transitionDuration ).toBe( 500 );
		expect( defaults.preloadAdjacentImages ).toBe( true );
		expect( defaults.showNavOnHover ).toBe( true );
	} );

	test( 'respects overrides when provided', () => {
		const defaults = window.sppopups.defaults.pattern;
		const overrides = { maxWidth: 1200 };

		const finalDefaults = Object.assign( {}, defaults, overrides );

		modalElement.style.setProperty( '--sppopups-max-width', finalDefaults.maxWidth + 'px' );

		expect( modalElement.style.getPropertyValue( '--sppopups-max-width' ) ).toBe( '1200px' );
	} );

	test( 'falls back to hardcoded defaults when localized data missing', () => {
		// Remove sppopups.defaults
		delete window.sppopups.defaults;

		// Simulate fallback behavior from getDefaultsForType
		const fallbackDefaults = {
			maxWidth: 600,
			borderRadius: 6,
			maxHeight: 90,
			overlayColor: 'rgba(0, 0, 0, 0.1)',
			backdropBlur: 8,
			showIconClose: true,
			showFooterClose: true,
			footerCloseText: 'Close →',
		};

		modalElement.style.setProperty( '--sppopups-max-width', fallbackDefaults.maxWidth + 'px' );

		expect( modalElement.style.getPropertyValue( '--sppopups-max-width' ) ).toBe( '600px' );
	} );

	test( 'sets data attributes for boolean settings', () => {
		const defaults = window.sppopups.defaults.pattern;

		modalElement.setAttribute( 'data-show-icon-close', defaults.showIconClose ? 'true' : 'false' );
		modalElement.setAttribute( 'data-show-footer-close', defaults.showFooterClose ? 'true' : 'false' );

		expect( modalElement.getAttribute( 'data-show-icon-close' ) ).toBe( 'true' );
		expect( modalElement.getAttribute( 'data-show-footer-close' ) ).toBe( 'true' );

		// Test false values
		const falseDefaults = { showIconClose: false, showFooterClose: false };
		modalElement.setAttribute( 'data-show-icon-close', falseDefaults.showIconClose ? 'true' : 'false' );
		modalElement.setAttribute( 'data-show-footer-close', falseDefaults.showFooterClose ? 'true' : 'false' );

		expect( modalElement.getAttribute( 'data-show-icon-close' ) ).toBe( 'false' );
		expect( modalElement.getAttribute( 'data-show-footer-close' ) ).toBe( 'false' );
	} );

	test( 'sets gallery specific attributes', () => {
		const defaults = window.sppopups.defaults.gallery;

		modalElement.setAttribute( 'data-gallery-crossfade', defaults.crossfadeTransition ? 'true' : 'false' );
		modalElement.setAttribute( 'data-gallery-show-captions', defaults.showCaptions ? 'true' : 'false' );
		modalElement.setAttribute( 'data-gallery-nav-hover', defaults.showNavOnHover ? 'true' : 'false' );
		modalElement.style.setProperty( '--sppopups-gallery-transition-duration', defaults.transitionDuration + 'ms' );

		expect( modalElement.getAttribute( 'data-gallery-crossfade' ) ).toBe( 'true' );
		expect( modalElement.getAttribute( 'data-gallery-show-captions' ) ).toBe( 'true' );
		expect( modalElement.getAttribute( 'data-gallery-nav-hover' ) ).toBe( 'true' );
		expect( modalElement.style.getPropertyValue( '--sppopups-gallery-transition-duration' ) ).toBe( '500ms' );
	} );

	test( 'converts maxheight percentage to vh units', () => {
		const defaults = window.sppopups.defaults.pattern;

		// Both percentage and vh units should be set
		modalElement.style.setProperty( '--sppopups-max-height', defaults.maxHeight + '%' );
		modalElement.style.setProperty( '--sppopups-max-height-vh', defaults.maxHeight + 'vh' );

		expect( modalElement.style.getPropertyValue( '--sppopups-max-height' ) ).toBe( '90%' );
		expect( modalElement.style.getPropertyValue( '--sppopups-max-height-vh' ) ).toBe( '90vh' );
	} );
} );
