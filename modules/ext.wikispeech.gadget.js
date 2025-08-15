/**
 * Loads wikispeech modules from producer
 *
 * @module ext.wikispeech.gadget
 */

let moduleUrl, api, optionsPage, main;

/**
 * Add config variables from the producer's config.
 *
 * The config values are specified in extension.json under
 * ResourceModules -> ext.wikispeech.gadget.
 */
function addConfig() {
	const config = require( './config.json' );
	Object.keys( config ).forEach( ( key ) => {
		const value = config[ key ];
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
	const done = $.Deferred();
	if ( mw.user.isAnon() ) {
		// No user options set if not logged in.
		done.resolve( {} );
	} else {
		api.get( {
			action: 'parse',
			page: optionsPage,
			prop: 'wikitext',
			formatversion: 2
		} )
			.done( ( response ) => {
				const content = response.parse.wikitext;
				let options;
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
			.fail( ( error ) => {
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
	const done = $.Deferred();
	const defaultOptions = require( './default-user-options.json' );
	getUserOptionsOnConsumer().done( ( options ) => {
		Object.keys( defaultOptions ).forEach( ( key ) => {
			let value;

			// Take the option value from user page if it is set,
			// otherwise use default.
			if ( Object.keys( options ).includes( key ) ) {
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
 * @param {ext.wikispeech.UserOptionsDialog} dialog
 */
function writeUserOptionsToWikiPage( dialog ) {
	const options = require( './default-user-options.json' );
	const voice = dialog.getVoice();
	options[ voice.variable ] = voice.voice;
	options.wikispeechSpeechRate = dialog.getSpeechRate();
	const optionsJson = JSON.stringify( options, null, 4 );
	api.postWithEditToken( {
		action: 'edit',
		title: optionsPage,
		text: optionsJson,
		formatversion: 2
	} )
		.done( () => {
			mw.log( '[Wikispeech] Wrote user preferences to "' + optionsPage + '".' );
		} )
		.fail( ( error ) => {
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
	const UserOptionsDialog = require( './ext.wikispeech.userOptionsDialog.js' );
	const dialog = new UserOptionsDialog();
	main.ui.addWindow( dialog );
	const gadgetGroup = main.ui.addToolbarGroup();
	main.ui.addButton( gadgetGroup, 'settings', () => {
		main.ui.openWindow( dialog ).done(
			( data ) => {
				if ( data && data.action === 'save' ) {
					writeUserOptionsToWikiPage( dialog );
				}
			}
		);
	}, mw.msg( 'wikispeech-settings' ) );
	if ( mw.config.get( 'wgWikispeechAllowConsumerEdits' ) ) {
		const producerUrl = mw.config.get( 'wgWikispeechProducerUrl' );
		const producerApi = new mw.ForeignApi( `${ producerUrl }/api.php` );
		producerApi.get( {
			action: 'query',
			format: 'json',
			meta: 'siteinfo',
			siprop: 'general'
		} )
			.done( ( response ) => {
				const producerInfo = response.query.general,
					scriptPath = producerInfo.server + producerInfo.script;
				main.ui.addEditButton( scriptPath );
			} );
	}
}

mw.loader.using( [
	'mediawiki.api',
	'mediawiki.user',
	'mediawiki.ForeignApi',
	'oojs-ui',
	'oojs-ui-core',
	'oojs-ui-toolbars',
	'oojs-ui-windows',
	'oojs-ui.styles.icons-media',
	'oojs-ui.styles.icons-movement',
	'oojs-ui.styles.icons-interactions',
	'oojs-ui.styles.icons-editing-core'
] ).then( async () => {
	const producerUrl = mw.config.get( 'wgWikispeechProducerUrl' );
	if ( !producerUrl ) {
		mw.log.error( '[Wikispeech] No producer URL given. Set it with the config variable "wgWikispeechProducerUrl".' );
		return;
	}

	addConfig();
	const namespace = mw.config.get( 'wgNamespaceIds' ).user;
	const userPage = mw.Title.makeTitle( namespace, mw.user.getName() )
		.getPrefixedText();
	optionsPage = userPage + '/Wikispeech_preferences';
	api = new mw.Api();
	await addUserOptions();
	const parametersString = $.param( {
		lang: mw.config.get( 'wgUserLanguage' ),
		skin: mw.config.get( 'skin' ),
		raw: 1,
		safemode: 1,
		modules: 'ext.wikispeech'
	} );
	moduleUrl = `${ producerUrl }/load.php?${ parametersString }`;
	mw.log( `[Wikispeech] Loading wikispeech module from ${ moduleUrl }` );
	try {
		await mw.loader.getScript( moduleUrl );
		await mw.loader.using( 'ext.wikispeech' );
		main = require( 'ext.wikispeech' );
		await main.ui.ready;
		extendUi( main );
	} catch ( error ) {
		mw.log.error( '[Wikispeech] Failed to load Wikispeech module: ', error );
	}
} );
