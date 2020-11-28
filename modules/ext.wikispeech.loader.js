( function () {

	/**
	 * A small helper script to trigger the loading of the Wikispeech modules.
	 *
	 * @class ext.wikispeech.loader
	 */

	// eslint-disable-next-line no-jquery/no-global-selector
	$( '.ext-wikispeech-listen a' ).one( 'click', function () {
		mw.log( '[Wikispeech] Loading Wikispeech...' );
		mw.loader.using( 'ext.wikispeech' ).done( function () {
			mw.log( '[Wikispeech] Loaded Wikispeech.' );
		} );
	} );

}() );
