( function ( mw, $ ) {
	QUnit.module( 'ext.wikispeech.util', {} );

	QUnit.test( 'getNodeForItem()', function ( assert ) {
		var item, textNode, contentSelector;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml( 'Text node.' );
		item = { path: './text()' };
		contentSelector = mw.config.get( 'wgWikispeechContentSelector' );

		textNode = mw.wikispeech.util.getNodeForItem( item );

		assert.strictEqual(
			textNode,
			$( contentSelector ).contents().get( 0 )
		);
	} );
}( mediaWiki, jQuery ) );
