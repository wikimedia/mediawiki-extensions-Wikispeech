/**
 * Loads wikispeech modules from producer
 *
 * @module ext.wikispeech.gadget
 */

let moduleUrl, api, main;

const {
	addUserOptions
} = require( './ext.wikispeech.sharedUserOptionSettings.js' );

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
 * Add consumer specific elements to the UI.
 *
 * Adds a popup dialog for changing user options and a button on
 * the player toolbar to open it.
 *
 * @param mainInstance
 */
function extendUi( mainInstance ) {
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
				const consumerUrl = window.location.origin + mw.config.get( 'wgScriptPath' );
				const editButtonItem = mainInstance.ui.createEditButton( scriptPath, consumerUrl );
				mainInstance.ui.addMenuItem( editButtonItem );

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
	api = new mw.Api();
	await addUserOptions( api, false );
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
		main.ui.isProducer = false;
		extendUi( main );
	} catch ( error ) {
		mw.log.error( '[Wikispeech] Failed to load Wikispeech module: ', error );
	}
} );
