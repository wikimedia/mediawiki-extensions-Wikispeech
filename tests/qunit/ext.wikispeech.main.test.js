( function ( mw ) {
	var main;

	QUnit.module( 'ext.wikispeech.main', {
		setup: function () {
			main = new mw.wikispeech.Main();
		}
	} );

	QUnit.test( 'enabledForNamespace()', function ( assert ) {
		assert.expect( 1 );

		mw.config.set( 'wgWikispeechNamespaces', [ 1, 2 ] );
		mw.config.set( 'wgNamespaceNumber', 1 );

		assert.strictEqual( main.enabledForNamespace(), true );
	} );

	QUnit.test( 'enabledForNamespace(): false if invalid namespace', function ( assert ) {
		assert.expect( 1 );

		mw.config.set( 'wgWikispeechNamespaces', [ 1 ] );
		mw.config.set( 'wgNamespaceNumber', 2 );

		assert.strictEqual( main.enabledForNamespace(), false );
	} );
}( mediaWiki ) );
