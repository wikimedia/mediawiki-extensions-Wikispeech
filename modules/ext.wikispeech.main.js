/**
 * Main class for the Wikispeech extension.
 *
 * Handles setup of various components and initialization.
 *
 * @class ext.wikispeech.Main
 * @constructor
 */

require( './ext.wikispeech.util.js' );
require( './ext.wikispeech.ui.js' );
require( './ext.wikispeech.storage.js' );
require( './ext.wikispeech.player.js' );
require( './ext.wikispeech.selectionPlayer.js' );
require( './ext.wikispeech.highlighter.js' );

function Main() {
	const self = this;

	this.init = function () {
		if ( !self.enabledForNamespace() ) {
			// TODO: This is only required for tests to run
			// properly since namespace is checked at an earlier
			// stage for production code. See T267529.
			return;
		}

		if ( mw.config.get( 'wgMFMode' ) ) {
			// Do not load Wikispeech if MobileFrontend is
			// enabled since it does not support its mobile
			// view. See T169059.
			return;
		}

		mw.wikispeech.storage.loadUtterances( window );
		// Prepare the first utterance for playback.
		mw.wikispeech.ui.init();
		// Prepare action link.
		// eslint-disable-next-line no-jquery/no-global-selector
		const $toggleVisibility = $( '.ext-wikispeech-listen a' );
		// Set label to hide message since the player is
		// visible when loaded.
		$toggleVisibility.text(
			mw.msg( 'wikispeech-dont-listen' )
		);
		$toggleVisibility.on(
			'click',
			$toggleVisibility,
			self.toggleVisibility
		);
	};

	/**
	 * Toggle the visibility of the control panel.
	 *
	 * @method
	 * @memberof ext.wikispeech.Main
	 * @param {Event} event
	 */

	this.toggleVisibility = function ( event ) {
		mw.wikispeech.ui.toggleVisibility();

		let toggleVisibilityMessage;
		if ( mw.wikispeech.ui.isShown() ) {
			toggleVisibilityMessage = 'wikispeech-dont-listen';
		} else {
			toggleVisibilityMessage = 'wikispeech-listen';
		}
		const $toggleVisibility = event.data;
		// Messages that can be used here:
		// * wikispeech-listen
		// * wikispeech-dont-listen
		$toggleVisibility.text( mw.msg( toggleVisibilityMessage ) );
	};

	/**
	 * Check if Wikispeech is enabled for the current namespace.
	 *
	 * @method
	 * @memberof ext.wikispeech.Main
	 * @return {boolean} true is the namespace of current page
	 *  should activate Wikispeech, else false.
	 */

	this.enabledForNamespace = function () {
		const validNamespaces = mw.config.get( 'wgWikispeechNamespaces' );
		const namespace = mw.config.get( 'wgNamespaceNumber' );
		return validNamespaces.includes( namespace );
	};

}

mw.loader.using( [ 'mediawiki.api', 'ext.wikispeech' ] ).done(
	() => {
		const main = new Main();
		main.init();
	}
);
