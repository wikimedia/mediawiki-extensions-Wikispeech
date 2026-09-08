const sharedUserOptionSettings = require(
	'ext.wikispeech/ext.wikispeech.sharedUserOptionSettings.js'
);

QUnit.module( 'ext.wikispeech.sharedUserOptionSettings', QUnit.newMwEnvironment() );

QUnit.test( 'addUserOptions(): producer stores booleans as strings', ( assert ) => {
	mw.user.options.set( 'wikispeechPartOfContent', '1' );

	sharedUserOptionSettings.addUserOptions( null, true );

	assert.strictEqual(
		mw.user.options.get( 'wikispeechPartOfContent' ),
		true
	);
} );

QUnit.test( 'addUserOptions(): producer without the option set', ( assert ) => {
	mw.user.options.set( 'wikispeechPartOfContent', undefined );

	sharedUserOptionSettings.addUserOptions( null, true );

	assert.strictEqual(
		mw.user.options.get( 'wikispeechPartOfContent' ),
		false
	);
} );

QUnit.test( 'addUserOptions(): consumer stores booleans as booleans', async ( assert ) => {
	const api = {
		get: () => $.Deferred().resolve( {
			parse: { wikitext: '{ "wikispeechPartOfContent": true }' }
		} )
	};
	sinon.stub( mw.user, 'isAnon' ).returns( false );

	await sharedUserOptionSettings.addUserOptions( api, false );

	assert.strictEqual(
		mw.user.options.get( 'wikispeechPartOfContent' ),
		true
	);
} );

QUnit.test( 'addUserOptions(): a value that is not a boolean is left alone', ( assert ) => {
	mw.user.options.set( 'wikispeechVoiceSv', 'stts_sv_nst-hsmm' );

	sharedUserOptionSettings.addUserOptions( null, true );

	assert.strictEqual(
		mw.user.options.get( 'wikispeechVoiceSv' ),
		'stts_sv_nst-hsmm'
	);
} );
