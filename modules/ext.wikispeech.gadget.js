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

	function getUserOptionsOnConsumer() {
		var done, api;

		done = $.Deferred();
		if ( mw.user.isAnon() ) {
			// No user options set if not logged in.
			done.resolve( {} );
		} else {
			api = new mw.Api();
			api.get( {
				action: 'parse',
				page: 'User:' + mw.user.getName() + '/Wikispeech preferences',
				prop: 'wikitext'
			} )
				.done( function ( response ) {
					var content, options;

					content = response.parse.wikitext[ '*' ];
					try {
						mw.log.warn(
							'[Wikispeech] Failed to parse user preferences, ' +
								'using defaults.'
						);
						options = JSON.parse( content );
					} catch ( error ) {
						options = {};
					}
					done.resolve( options );
				} )
				.fail( function () {
					mw.log.warn(
						'[Wikispeech] Failed to load user preferences page, ' +
							'using defaults.'
					);
					done.resolve( {} );
				} );
		}
		return done;
	}

	function addUserOptions() {
		var defaultOptions, done;

		done = $.Deferred();
		defaultOptions = require( './default-user-options.json' );
		getUserOptionsOnConsumer().done( function ( options ) {
			Object.keys( defaultOptions ).forEach( function ( key ) {
				var value;

				// Take the option value from user page if it is set,
				// otherwise use default.
				if ( Object.keys( options ).indexOf( key ) >= 0 ) {
					value = options[ key ];
				} else {
					value = defaultOptions[ key ];
				}
				mw.user.options.set( key, value );
				done.resolve();
			} );
		} );
		return done;
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
	addConfig();
	addUserOptions().done( function () {
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
	} );
	mw.loader.using( 'ext.wikispeech' )
		.done( function () {
			var producerApiUrl = mw.config.get( 'wgWikispeechProducerUrl' ) + '/api.php';
			mw.wikispeech.storage.api = new mw.ForeignApi( producerApiUrl );
		} )
		.fail( function ( args ) {
			mw.log.error( 'Failed to load Wikispeech module:', args );
		} );
}() );
