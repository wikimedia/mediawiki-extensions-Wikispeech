( function () {

	/**
	 * Loads wikispeech modules from producer
	 */

	var moduleUrl, parametersString;

	function addConfig() {
		var config = require( './config.json' );
		Object.keys( config ).forEach( function ( key ) {
			var value = config[ key ];
			mw.config.set( 'wg' + key, value );
		} );
	}

	function addDefaultUserOptions() {
		var options = require( './default-user-options.json' );
		Object.keys( options ).forEach( function ( key ) {
			var value = options[ key ];
			mw.user.options.set( key, value );
		} );
	}

	mw.wikispeech = mw.wikispeech || {};
	mw.wikispeech.consumerMode = true;

	// Register module to be loaded from the producer. This is
	// required for loader.using() below, since the module is not
	// registered on the consumer.
	mw.loader.register(
		'ext.wikispeech',
		'',
		[
			'mediawiki.ForeignApi',
			'oojs-ui-core',
			'oojs-ui-toolbars',
			'oojs-ui.styles.icons-media',
			'oojs-ui.styles.icons-movement',
			'oojs-ui.styles.icons-interactions',
			'oojs-ui.styles.icons-editing-core'
		],
		null,
		'anotherwiki'
	);
	parametersString = $.param( {
		lang: mw.config.get( 'wgUserLanguage' ),
		skin: mw.config.get( 'skin' ),
		raw: 1,
		safemode: 1,
		modules: 'ext.wikispeech'
	} );
	moduleUrl = mw.config.get( 'wgWikispeechProducerUrl' ) + '/load.php?' +
		parametersString;
	mw.log( 'Loading wikispeech module from ' + moduleUrl );
	mw.loader.load( moduleUrl );
	addConfig();
	addDefaultUserOptions();
	mw.loader.using( 'ext.wikispeech' )
		.done( function () {
			var producerApiUrl = mw.config.get( 'wgWikispeechProducerUrl' ) + '/api.php';
			mw.wikispeech.storage.api = new mw.ForeignApi( producerApiUrl );
		} )
		.fail( function ( args ) {
			mw.log.error( 'Failed to load Wikispeech module:', args );
		} );
}() );
