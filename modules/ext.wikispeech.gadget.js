( function () {

	/**
	 * Loads wikispeech modules from producer
	 */

	var moduleUrl, parametersString, api, namespace, optionsPage;

	/**
	 * Add config variables from the producer's config.
	 */

	function addConfig() {
		var config = require( './config.json' );
		Object.keys( config ).forEach( function ( key ) {
			var value = config[ key ];
			mw.config.set( 'wg' + key, value );
		} );
	}

	/**
	 * Read user options from the consumer wiki.
	 *
	 * User options are stored in a subpage to the user page called
	 * "Wikispeech_preferences".
	 *
	 * @return {jQuery.Deferred} Resolves with an object containing the
	 *  user options. Resolves with the empty object if the options
	 *  could not be read.
	 */

	function getUserOptionsOnConsumer() {
		var done;

		done = $.Deferred();
		if ( mw.user.isAnon() ) {
			// No user options set if not logged in.
			done.resolve( {} );
		} else {
			api = new mw.Api();
			api.get( {
				action: 'parse',
				page: optionsPage,
				prop: 'wikitext',
				formatversion: 2
			} )
				.done( function ( response ) {
					var content, options;

					content = response.parse.wikitext;
					try {
						options = JSON.parse( content );
					} catch ( error ) {
						mw.log.warn(
							'[Wikispeech] Failed to parse user preferences, ' +
								'using defaults: ' + error
						);
						options = {};
					}
					done.resolve( options );
				} )
				.fail( function ( error ) {
					mw.log.warn(
						'[Wikispeech] Failed to load user preferences page, ' +
							'using defaults: ' + error
					);
					done.resolve( {} );
				} );
		}
		return done;
	}

	/**
	 * Add user options for Wikispeech.
	 *
	 * Reads user options from the consumer wiki and add those. Any
	 * option that is not present on the consumer wiki will be set to the
	 * default from the producer wiki.
	 *
	 * @return {jQuery.Deferred} Resolves when user options have been
	 *  read.
	 */

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

	/**
	 * Write user options to a subpage to the user page.
	 *
	 * User options are read from the preferences popup dialog and
	 * stored as JSON.
	 *
	 * @param dialog {ext.wikispeech.UserOptionsDialog}
	 */

	function writeUserOptionsToWikiPage( dialog ) {
		var voice, optionsJson, options;

		options = require( './default-user-options.json' );
		voice = dialog.getVoice();
		options[ voice.variable ] = voice.voice;
		options.wikispeechSpeechRate = dialog.getSpeechRate();
		optionsJson = JSON.stringify( options, null, 4 );
		api.postWithEditToken( {
			action: 'edit',
			title: optionsPage,
			text: optionsJson,
			formatversion: 2
		} )
			.done( function () {
				mw.log( '[Wikispeech] Wrote user preferences to "' + optionsPage + '".' );
			} )
			.fail( function ( error ) {
				mw.log.warn(
					'[Wikispeech] Failed to write user preferences to "' +
						optionsPage + '": ' + error
				);
			} );
	}

	/**
	 * Add consumer specific elements to the UI.
	 *
	 * Adds a popup dialog for changing user options and a button on
	 * the player toolbar to open it.
	 */

	function extendUi() {
		var UserOptionsDialog, dialog, gadgetGroup;

		UserOptionsDialog = require( './ext.wikispeech.userOptionsDialog.js' );
		dialog = new UserOptionsDialog();
		mw.wikispeech.ui.addWindow( dialog );
		gadgetGroup = mw.wikispeech.ui.addToolbarGroup();
		mw.wikispeech.ui.addButton( gadgetGroup, 'settings', function () {
			mw.wikispeech.ui.openWindow( dialog ).done(
				function ( data ) {
					if ( data && data.action === 'save' ) {
						writeUserOptionsToWikiPage( dialog );
					}
				}
			);
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
			'oojs-ui',
			'oojs-ui-core',
			'oojs-ui-toolbars',
			'oojs-ui-windows',
			'oojs-ui.styles.icons-media',
			'oojs-ui.styles.icons-movement',
			'oojs-ui.styles.icons-interactions',
			'oojs-ui.styles.icons-editing-core'
		],
		null,
		'anotherwiki'
	);
	addConfig();
	api = new mw.Api();
	namespace = mw.config.get( 'wgNamespaceIds' ).user;
	optionsPage = mw.Title.makeTitle( namespace, mw.user.getName() ).getPrefixedText() + '/Wikispeech_preferences';
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
		mw.log( '[Wikispeech] Loading wikispeech module from ' + moduleUrl );
		mw.loader.load( moduleUrl );
	} );
	mw.loader.using( 'ext.wikispeech' )
		.done( function () {
			var producerApiUrl = mw.config.get( 'wgWikispeechProducerUrl' ) + '/api.php';
			mw.wikispeech.storage.api = new mw.ForeignApi( producerApiUrl );

			mw.wikispeech.ui.ready.done( function () {
				extendUi();
			} );
		} )
		.fail( function ( error ) {
			mw.log.error( '[Wikispeech] Failed to load Wikispeech module: ' + error );
		} );
}() );
