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
			if ( self.enabledForNamespace() ) {
				mw.wikispeech.storage.loadUtterances();
				// Prepare the first utterance for playback.
				mw.wikispeech.ui.init();
			}
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

	mw.wikispeech = mw.wikispeech || {};
	mw.wikispeech.Main = Main;

	mw.loader.using( [ 'mediawiki.api', 'ext.wikispeech' ] ).done(
		function () {
			var main = new Main();
			main.init();
		}
	);
}( mediaWiki ) );
