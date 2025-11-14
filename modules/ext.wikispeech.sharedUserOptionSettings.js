/**
 * @module ext.wikispeech.sharedUserOptionSettings
 */

const defaultOptions = require( './default-user-options.json' );

function computeOptionsPage() {
	const namespace = mw.config.get( 'wgNamespaceIds' ).user;
	const userPage = mw.Title.makeTitle( namespace, mw.user.getName() ).getPrefixedText();
	return userPage + '/Wikispeech_preferences';
}

/**
 * Read user options from the consumer wiki.
 *
 * User options are stored in a subpage to the user page called
 * "Wikispeech_preferences".
 *
 * @param api
 * @return {jQuery.Deferred} Resolves with an object containing the
 *  user options. Resolves with the empty object if the options
 *  could not be read.
 */
function getUserOptionsOnConsumer( api ) {
	const optionsPage = computeOptionsPage();
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
 * @param api
 * @param isProducer
 * @return {jQuery.Deferred} Resolves when user options have been
 *  read.
 */
function addUserOptions( api, isProducer ) {
	const done = $.Deferred();
	if ( isProducer ) {
		Object.keys( defaultOptions ).forEach( ( key ) => {
			const value = mw.user.options.get( key );
			let finalValue;
			if ( value !== undefined ) {
				finalValue = value;
			} else {
				finalValue = defaultOptions[ key ];
			}
			mw.user.options.set( key, finalValue );
		} );
		done.resolve();
	} else {
		getUserOptionsOnConsumer( api ).done( ( options ) => {
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
			} );
			done.resolve();
		} );
	}

	return done;
}

/**
 * Write user options to Special:Preferences, and a subpage to the user page
 * on the gadget solution if on producer mode.
 *
 * User options are read from the preferences popup dialog and
 * stored as JSON on the user page and as values in user preferences.
 *
 * @param api
 * @param {ext.wikispeech.UserOptionsDialog} dialog
 * @param isProducer
 */
function writeUserOptionsPreferences( api, dialog, isProducer ) {
	const optionsPage = computeOptionsPage();
	const options = Object.assign( {}, defaultOptions );
	const voice = dialog.getVoice();
	options[ voice.variable ] = voice.voice;
	options.wikispeechSpeechRate = dialog.getSpeechRate();
	options.wikispeechPartOfContent = dialog.getPartOfContent() ? '1' : '0';

	api.saveOption( voice.variable, voice.voice );
	api.saveOption( 'wikispeechSpeechRate', String( options.wikispeechSpeechRate ) );
	api.saveOption( 'wikispeechPartOfContent', options.wikispeechPartOfContent );

	if ( !isProducer ) {
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
}

module.exports = {
	computeOptionsPage,
	getUserOptionsOnConsumer,
	addUserOptions,
	writeUserOptionsPreferences
};
