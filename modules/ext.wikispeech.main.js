/**
 * Main class for the Wikispeech extension.
 *
 * Handles setup of various components and initialization.
 *
 * @class ext.wikispeech.Main
 * @constructor
 */
mw.wikispeech = mw.wikispeech || {};
const Ui = require( './ext.wikispeech.ui.js' );
const Storage = require( './ext.wikispeech.storage.js' );
const Player = require( './ext.wikispeech.player.js' );
const SelectionPlayer = require( './ext.wikispeech.selectionPlayer.js' );
const Highlighter = require( './ext.wikispeech.highlighter.js' );

class Main {
	constructor() {
		this.storage = new Storage();
		this.selectionPlayer = new SelectionPlayer();
		this.ui = new Ui();
		this.player = new Player();
		this.highlighter = new Highlighter();

		this.highlighter.storage = this.storage;
		this.storage.player = this.player;
		this.storage.highlighter = this.highlighter;
		this.player.ui = this.ui;
		this.player.storage = this.storage;
		this.player.highlighter = this.highlighter;
		this.player.selectionPlayer = this.selectionPlayer;
		this.selectionPlayer.storage = this.storage;
		this.selectionPlayer.player = this.player;
		this.selectionPlayer.ui = this.ui;
		this.ui.player = this.player;
		this.ui.storage = this.storage;
		this.ui.selectionPlayer = this.selectionPlayer;
	}

	init() {
		if ( !this.enabledForNamespace() ) {
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

		this.storage.loadUtterances( window );
		// Prepare the first utterance for playback.

		this.ui.init();
		// Prepare action link.
		// eslint-disable-next-line no-jquery/no-global-selector
		const $toggleVisibility = $( '.ext-wikispeech-listen a' );
		// Set label to hide message since the player is
		// visible when loaded.
		$toggleVisibility.text(
			mw.msg( 'wikispeech-dont-listen' )
		);
		$toggleVisibility.on( 'click', this.toggleVisibility );
	}

	/**
	 * Toggle the visibility of the control panel.
	 *
	 * @method
	 * @memberof ext.wikispeech.Main
	 * @param {Event} event
	 */

	toggleVisibility( event ) {
		this.ui.toggleVisibility();

		let toggleVisibilityMessage;
		if ( this.ui.isShown() ) {
			toggleVisibilityMessage = 'wikispeech-dont-listen';
		} else {
			toggleVisibilityMessage = 'wikispeech-listen';
		}
		const $toggleVisibility = event.data;
		// Messages that can be used here:
		// * wikispeech-listen
		// * wikispeech-dont-listen
		$toggleVisibility.text( mw.msg( toggleVisibilityMessage ) );
	}

	/**
	 * Check if Wikispeech is enabled for the current namespace.
	 *
	 * @method
	 * @memberof ext.wikispeech.Main
	 * @return {boolean} true is the namespace of current page
	 *  should activate Wikispeech, else false.
	 */

	enabledForNamespace() {
		const validNamespaces = mw.config.get( 'wgWikispeechNamespaces' );
		const namespace = mw.config.get( 'wgNamespaceNumber' );
		return validNamespaces.includes( namespace );
	}

}

module.exports = Main;

mw.loader.using( [ 'mediawiki.api', 'ext.wikispeech' ] ).done(
	() => {
		const main = new Main();
		main.init();
	}
);
