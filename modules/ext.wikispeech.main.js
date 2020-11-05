( function ( mw ) {

	/**
	 * Main class for the Wikispeech extension.
	 *
	 * Handles setup of various components and initialization.
	 *
	 * @class ext.wikispeech.Main
	 * @constructor
	 */

	function Main() {
		var self;

		self = this;

		this.init = function () {
			var $toggleVisibility;

			if ( !self.enabledForNamespace() ) {
				// TODO: This is only required for tests to run
				// properly since namespace is checked at an earlier
				// stage for production code. See T267529.
				return;
			}

			mw.wikispeech.storage.loadUtterances();
			// Prepare the first utterance for playback.
			mw.wikispeech.ui.init();
			// Prepare action link.
			new mw.Api().loadMessagesIfMissing( [
				'wikispeech-listen',
				'wikispeech-dont-listen'
			] )
				.done( function () {
					$toggleVisibility = $( '.ext-wikispeech-listen a' );
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
				} );
		};

		/**
		 * Toggle the visibility of the control panel.
		 *
		 * @param {Event} event
		 */

		this.toggleVisibility = function ( event ) {
			var $toggleVisibility, toggleVisibilityMessage;

			mw.wikispeech.ui.toggleVisibility();
			if ( mw.wikispeech.ui.isShown() ) {
				toggleVisibilityMessage = 'wikispeech-dont-listen';
			} else {
				toggleVisibilityMessage = 'wikispeech-listen';
			}
			$toggleVisibility = event.data;
			// Messages that can be used here:
			// * wikispeech-listen
			// * wikispeech-dont-listen
			$toggleVisibility.text( mw.msg( toggleVisibilityMessage ) );
		};

		/**
		 * Check if Wikispeech is enabled for the current namespace.
		 *
		 * @return {boolean} true is the namespace of current page
		 *  should activate Wikispeech, else false.
		 */

		this.enabledForNamespace = function () {
			var validNamespaces, namespace;

			validNamespaces = mw.config.get( 'wgWikispeechNamespaces' );
			namespace = mw.config.get( 'wgNamespaceNumber' );
			return validNamespaces.indexOf( namespace ) >= 0;
		};

	}

	mw.loader.using( [ 'mediawiki.api', 'ext.wikispeech' ] ).done(
		function () {
			var main = new Main();
			main.init();
		}
	);
}( mediaWiki ) );
