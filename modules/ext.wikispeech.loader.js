( function ( mw ) {

	/**
	 * A small helper script to trigger the loading of the Wikispeech modules.
	 *
	 * @class ext.wikispeech.loader
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
