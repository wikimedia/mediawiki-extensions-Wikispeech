( function ( mw ) {

	/**
	 * Add an option to load Wikispeech modules.
	 */

	$( '.ext-wikispeech-listen a' ).click( function () {
		mw.log( '[Wikispeech] Loading Wikispeech...' );
		mw.loader.using( 'ext.wikispeech' ).done(
			function () {
				mw.log( '[Wikispeech] Loaded Wikispeech.' );
			}
		);
	} );

}( mediaWiki ) );
