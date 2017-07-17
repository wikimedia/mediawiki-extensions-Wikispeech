( function ( mw, $ ) {
	var contentSelector, utterances;

	QUnit.module( 'ext.wikispeech.highlighter', {
		setup: function () {
			mw.wikispeech.highlighter = new mw.wikispeech.Highlighter();
			// Mock wikispeech for methods that are called as side
			// effects.
			mw.wikispeech.wikispeech = {
				getNextToken: function () {}
			};
			contentSelector = '#mw-content-text';
			$( '#qunit-fixture' )
				.append(
					$( '<div></div>' )
						.attr( 'id', 'mw-content-text' )
						.text( 'Utterance zero.' )
				);
			utterances = [
				{
					startOffset: 0,
					endOffset: 14,
					content: [ { path: './text()' } ]
				}
			];
		}
	} );

	QUnit.test( 'highlightUtterance()', function ( assert ) {
		assert.expect( 2 );

		mw.wikispeech.highlighter.highlightUtterance( utterances[ 0 ] );

		assert.strictEqual(
			$( contentSelector ).html(),
			'<span class="ext-wikispeech-highlight-sentence">Utterance zero.</span>'
		);
		assert.strictEqual(
			$( '.ext-wikispeech-highlight-sentence' ).prop( 'textPath' ),
			'./text()'
		);
	} );

	QUnit.test( 'highlightUtterance(): multiple utterances', function ( assert ) {
		assert.expect( 1 );
		$( contentSelector )
			.text( 'Utterance zero. Utterance one. Utterance two.' );
		utterances[ 1 ] = {
			startOffset: 16,
			endOffset: 29,
			content: [ { path: './text()' } ]
		};

		mw.wikispeech.highlighter.highlightUtterance( utterances[ 1 ] );

		assert.strictEqual(
			$( contentSelector ).html(),
			'Utterance zero. <span class="ext-wikispeech-highlight-sentence">Utterance one.</span> Utterance two.'
		);
	} );

	QUnit.test( 'highlightUtterance(): with tags', function ( assert ) {
		assert.expect( 1 );
		$( contentSelector )
			.html( '<p>Utterance with <b>a</b> tag.</p>' );
		utterances[ 0 ] = {
			startOffset: 0,
			endOffset: 4,
			content: [
				{ path: './p/text()[1]' },
				{ path: './p/b/text()' },
				{ path: './p/text()[2]' }
			]
		};

		mw.wikispeech.highlighter.highlightUtterance( utterances[ 0 ] );

		assert.strictEqual(
			$( contentSelector ).html(),
			'<p><span class="ext-wikispeech-highlight-sentence">Utterance with </span><b><span class="ext-wikispeech-highlight-sentence">a</span></b><span class="ext-wikispeech-highlight-sentence"> tag.</span></p>'
		);
	} );

	QUnit.test( 'highlightUtterance(): wrap middle text nodes properly', function ( assert ) {
		assert.expect( 1 );
		$( contentSelector )
			.html( 'First<br />middle<br />last. Next utterance.' );
		utterances[ 0 ] = {
			startOffset: 0,
			endOffset: 4,
			content: [
				{ path: './text()[1]' },
				{ path: './text()[2]' },
				{ path: './text()[3]' }
			]
		};

		mw.wikispeech.highlighter.highlightUtterance( utterances[ 0 ] );

		assert.strictEqual(
			$( contentSelector ).html(),
			'<span class="ext-wikispeech-highlight-sentence">First</span><br><span class="ext-wikispeech-highlight-sentence">middle</span><br><span class="ext-wikispeech-highlight-sentence">last.</span> Next utterance.'
		);
	} );

	QUnit.test( 'removeWrappers()', function ( assert ) {
		assert.expect( 2 );
		$( contentSelector )
			.html( '<span class="wrapper">Utterance zero.</span>' );

		mw.wikispeech.highlighter.removeWrappers( '.wrapper' );

		assert.strictEqual(
			$( contentSelector ).html(),
			'Utterance zero.'
		);
		assert.strictEqual( $( '.wrapper' ).contents().length, 0 );
	} );

	QUnit.test( 'removeWrappers(): restore text nodes as one', function ( assert ) {
		assert.expect( 3 );
		$( contentSelector )
			.html( 'prefix <span class="wrapper">Utterance zero.</span> suffix' );

		mw.wikispeech.highlighter.removeWrappers( '.wrapper' );

		assert.strictEqual( $( contentSelector ).html(),
			'prefix Utterance zero. suffix'
		);
		assert.strictEqual( $( '.wrapper' ).contents().length, 0 );
		assert.strictEqual( $( contentSelector ).contents().length, 1 );
	} );

	QUnit.test( 'removeWrappers(): restore text nodes as one with inner wrapper', function ( assert ) {
		assert.expect( 2 );
		$( contentSelector )
			.html( '<span class="outer-wrapper">Utterance <span class="inner-wrapper">zero</span>.</span>' );

		mw.wikispeech.highlighter.removeWrappers( '.outer-wrapper' );

		assert.strictEqual(
			$( contentSelector ).html(),
			'Utterance <span class="inner-wrapper">zero</span>.'
		);
		assert.strictEqual( $( '.outer-wrapper' ).contents().length, 0 );
	} );

	QUnit.test( 'removeWrappers(): multiple wrappers', function ( assert ) {
		assert.expect( 3 );
		$( contentSelector )
			.html( '<span class="wrapper">Utterance</span> <span class="wrapper">zero.</span>' );

		mw.wikispeech.highlighter.removeWrappers( '.wrapper' );

		assert.strictEqual(
			$( contentSelector ).html(),
			'Utterance zero.'
		);
		assert.strictEqual( $( contentSelector ).contents().length, 1 );
		assert.strictEqual( $( '.wrapper' ).contents().length, 0 );
	} );

	QUnit.test( 'highlightToken()', function ( assert ) {
		var highlightedToken;

		assert.expect( 1 );
		highlightedToken = {
			utterance: utterances[ 0 ],
			startOffset: 0,
			endOffset: 8,
			items: [ utterances[ 0 ].content[ 0 ] ]
		};

		mw.wikispeech.highlighter.highlightToken( highlightedToken );

		assert.strictEqual(
			$( contentSelector ).html(),
			'<span class="ext-wikispeech-highlight-word">Utterance</span> zero.'
		);
	} );

	QUnit.test( 'highlightToken(): multiple utterances', function ( assert ) {
		var highlightedToken;

		assert.expect( 1 );
		$( contentSelector )
			.html( 'Utterance zero. Utterance one.' );
		utterances[ 1 ] = {
			startOffset: 16,
			content: [ { path: './text()' } ]
		};
		highlightedToken = {
			utterance: utterances[ 1 ],
			startOffset: 16,
			endOffset: 24,
			items: [ utterances[ 1 ].content[ 0 ] ]
		};
		utterances[ 1 ].tokens = [ highlightedToken ];

		mw.wikispeech.highlighter.highlightToken( highlightedToken );

		assert.strictEqual(
			$( contentSelector ).html(),
			'Utterance zero. <span class="ext-wikispeech-highlight-word">Utterance</span> one.'
		);
	} );

	QUnit.test( 'highlightToken(): with utterance highlighting', function ( assert ) {
		var highlightedToken;

		assert.expect( 1 );
		$( contentSelector )
			.html( '<span class="ext-wikispeech-highlight-sentence">Utterance with token.</span>' );
		$( '.ext-wikispeech-highlight-sentence' )
			.prop( 'textPath', './text()' );
		highlightedToken = {
			utterance: utterances[ 0 ],
			startOffset: 15,
			endOffset: 19,
			items: [ utterances[ 0 ].content[ 0 ] ]
		};
		utterances[ 0 ].tokens = [ highlightedToken ];

		mw.wikispeech.highlighter.highlightToken( highlightedToken );

		assert.strictEqual(
			$( contentSelector ).html(),
			'<span class="ext-wikispeech-highlight-sentence">Utterance with <span class="ext-wikispeech-highlight-word">token</span>.</span>'
		);
	} );

	QUnit.test( 'highlightToken(): with utterance highlighting and multiple utterances', function ( assert ) {
		var highlightedToken;

		assert.expect( 1 );
		$( contentSelector )
			.html(
				'Utterance zero. <span class="ext-wikispeech-highlight-sentence">Utterance one.</span>'
			);
		$( '.ext-wikispeech-highlight-sentence' )
			.prop( 'textPath', './text()' );
		utterances[ 1 ] = {
			startOffset: 16,
			content: [ { path: './text()' } ]
		};
		highlightedToken = {
			utterance: utterances[ 1 ],
			startOffset: 16,
			endOffset: 24,
			items: [ utterances[ 1 ].content[ 0 ] ]
		};
		utterances[ 1 ].tokens = [ highlightedToken ];

		mw.wikispeech.highlighter.highlightToken( highlightedToken );

		assert.strictEqual(
			$( contentSelector ).html(),
			'Utterance zero. <span class="ext-wikispeech-highlight-sentence"><span class="ext-wikispeech-highlight-word">Utterance</span> one.</span>'
		);
	} );

	QUnit.test( 'highlightToken(): with utterance highlighting and other spans', function ( assert ) {
		var highlightedToken;

		assert.expect( 1 );
		$( contentSelector )
			.html( '<span><span class="ext-wikispeech-highlight-sentence">Utterance with token.</span></span>' );
		$( '.ext-wikispeech-highlight-sentence' )
			.prop( 'textPath', './span/text()' );
		utterances[ 0 ].content[ 0 ] = { path: './span/text()' };
		highlightedToken = {
			utterance: utterances[ 0 ],
			startOffset: 15,
			endOffset: 19,
			items: [ utterances[ 0 ].content[ 0 ] ]
		};
		utterances[ 0 ].tokens = [ highlightedToken ];

		mw.wikispeech.highlighter.highlightToken( highlightedToken );

		assert.strictEqual(
			$( contentSelector ).html(),
			'<span><span class="ext-wikispeech-highlight-sentence">Utterance with <span class="ext-wikispeech-highlight-word">token</span>.</span></span>'
		);
	} );

	QUnit.test( 'highlightToken(): with tags', function ( assert ) {
		var highlightedToken;

		assert.expect( 1 );
		$( contentSelector ).html( 'Utterance with <br>token.' );
		utterances[ 0 ].content[ 0 ] = { path: './text()[2]' };
		highlightedToken = {
			utterance: utterances[ 0 ],
			startOffset: 0,
			endOffset: 4,
			items: [ utterances[ 0 ].content[ 0 ] ]
		};
		utterances[ 0 ].tokens = [ highlightedToken ];

		mw.wikispeech.highlighter.highlightToken( highlightedToken );

		assert.strictEqual(
			$( contentSelector ).html(),
			'Utterance with <br><span class="ext-wikispeech-highlight-word">token</span>.'
		);
	} );

	QUnit.test( 'highlightToken(): with multiple utterance highlightings', function ( assert ) {
		var highlightedToken;

		assert.expect( 1 );
		$( contentSelector ).html( '<span class="ext-wikispeech-highlight-sentence">Phrase </span><b><span class="ext-wikispeech-highlight-sentence">one</span></b><span class="ext-wikispeech-highlight-sentence">, phrase two.</span>' );
		$( '.ext-wikispeech-highlight-sentence' )
			.get( 2 ).textPath = './text()[2]';
		utterances[ 0 ].content[ 0 ] = { path: './text()[2]' };
		highlightedToken = {
			utterance: utterances[ 0 ],
			startOffset: 2,
			endOffset: 7,
			items: [ utterances[ 0 ].content[ 0 ] ]
		};
		utterances[ 0 ].tokens = [ highlightedToken ];

		mw.wikispeech.highlighter.highlightToken( highlightedToken );

		assert.strictEqual(
			$( contentSelector ).html(),
			'<span class="ext-wikispeech-highlight-sentence">Phrase </span><b><span class="ext-wikispeech-highlight-sentence">one</span></b><span class="ext-wikispeech-highlight-sentence">, <span class="ext-wikispeech-highlight-word">phrase</span> two.</span>'
		);
	} );

	QUnit.test( 'highlightToken(): with multiple utterance highlightings and text nodes', function ( assert ) {
		var highlightedToken;

		assert.expect( 1 );
		$( contentSelector ).html( 'Utterance <b>zero</b>. <span class="ext-wikispeech-highlight-sentence">Utterance one.</span>' );
		$( '.ext-wikispeech-highlight-sentence' )
			.prop( 'textPath', './text()[2]' );
		utterances[ 1 ] = {
			startOffset: 2,
			content: [ { path: './text()[2]' } ]
		};
		highlightedToken = {
			utterance: utterances[ 1 ],
			startOffset: 2,
			endOffset: 10,
			items: [ utterances[ 1 ].content[ 0 ] ]
		};
		utterances[ 1 ].tokens = [ highlightedToken ];

		mw.wikispeech.highlighter.highlightToken( highlightedToken );

		assert.strictEqual(
			$( contentSelector ).html(),
			'Utterance <b>zero</b>. <span class="ext-wikispeech-highlight-sentence"><span class="ext-wikispeech-highlight-word">Utterance</span> one.</span>'
		);
	} );
} )( mediaWiki, jQuery );
