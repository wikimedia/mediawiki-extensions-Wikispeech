( function ( mw, $ ) {
	QUnit.module( 'ext.wikispeech.highlighter', {
		setup: function () {
			mw.wikispeech.highlighter = new mw.wikispeech.Highlighter();
			// Mock wikispeech for methods that are called as side
			// effects.
			mw.wikispeech.wikispeech = {
				getNextToken: function () {}
			};
			$( '#qunit-fixture' )
				.append(
					$( '<div></div>' )
						.attr( 'id', 'mw-content-text' )
						.text( 'Utterance zero.' )
				)
				.append( $( '<utterances></utterances>' ) );
			$( '<utterance></utterance>' )
				.attr( {
					id: 'utterance-0',
					'start-offset': '0',
					'end-offset': '14'
				} )
				.append(
					$( '<content></content>' )
						.append(
							$( '<text></text>' )
								.attr( 'path', './text()' )
						)
				)
				.append(
					$( '<tokens></tokens>' )
				)
				.appendTo( $( 'utterances' ) );
		}
	} );

	QUnit.test( 'highlightUtterance()', function ( assert ) {
		assert.expect( 2 );

		mw.wikispeech.highlighter.highlightUtterance( $( '#utterance-0' ) );

		assert.strictEqual(
			$( '#mw-content-text' ).html(),
			'<span class="ext-wikispeech-highlight-sentence">Utterance zero.</span>'
		);
		assert.strictEqual(
			$( '.ext-wikispeech-highlight-sentence' ).prop( 'textPath' ),
			'./text()'
		);
	} );

	QUnit.test( 'highlightUtterance(): multiple utterances', function ( assert ) {
		assert.expect( 1 );
		$( '#mw-content-text' )
			.text( 'Utterance zero. Utterance one. Utterance two.' );
		$( '<utterance></utterance>' )
			.attr( {
				id: 'utterance-1',
				'start-offset': '16',
				'end-offset': '29'
			} )
			.append(
				$( '<content></content>' )
					.append(
						$( '<text></text>' )
							.attr( 'path', './text()' )
					)
			)
			.appendTo( $( 'utterances' ) );

		mw.wikispeech.highlighter.highlightUtterance( $( '#utterance-1' ) );

		assert.strictEqual(
			$( '#mw-content-text' ).html(),
			'Utterance zero. <span class="ext-wikispeech-highlight-sentence">Utterance one.</span> Utterance two.'
		);
	} );

	QUnit.test( 'highlightUtterance(): with tags', function ( assert ) {
		assert.expect( 1 );
		$( '#mw-content-text' )
			.html( '<p>Utterance with <b>a</b> tag.</p>' );
		$( '#utterance-0 content' )
			.empty()
			.append(
				$( '<text></text>' )
					.attr( 'path', './p/text()[1]' )
			)
			.append(
				$( '<text></text>' )
					.attr( 'path', './p/b/text()' )
			)
			.append(
				$( '<text></text>' )
					.attr( 'path', './p/text()[2]' )
			);
		$( '#utterance-0' ).attr( {
			'start-offset': '0',
			'end-offset': '4'
		} );

		mw.wikispeech.highlighter.highlightUtterance( $( '#utterance-0' ) );

		assert.strictEqual(
			$( '#mw-content-text' ).html(),
			'<p><span class="ext-wikispeech-highlight-sentence">Utterance with </span><b><span class="ext-wikispeech-highlight-sentence">a</span></b><span class="ext-wikispeech-highlight-sentence"> tag.</span></p>'
		);
	} );

	QUnit.test( 'highlightUtterance(): wrap middle text nodes properly', function ( assert ) {
		assert.expect( 1 );
		$( '#mw-content-text' )
			.html( 'First<br />middle<br />last. Next utterance.' );
		$( '#utterance-0 content' )
			.empty()
			.append(
				$( '<text></text>' )
					.attr( 'path', './text()[1]' )
			)
			.append(
				$( '<text></text>' )
					.attr( 'path', './text()[2]' )
			)
			.append(
				$( '<text></text>' )
					.attr( 'path', './text()[3]' )
			);
		$( '#utterance-0' ).attr( {
			'start-offset': '0',
			'end-offset': '4'
		} );

		mw.wikispeech.highlighter.highlightUtterance( $( '#utterance-0' ) );

		assert.strictEqual(
			$( '#mw-content-text' ).html(),
			'<span class="ext-wikispeech-highlight-sentence">First</span><br><span class="ext-wikispeech-highlight-sentence">middle</span><br><span class="ext-wikispeech-highlight-sentence">last.</span> Next utterance.'
		);
	} );

	QUnit.test( 'removeWrappers()', function ( assert ) {
		assert.expect( 2 );
		$( '#mw-content-text' )
			.html( '<span class="wrapper">Utterance zero.</span>' );

		mw.wikispeech.highlighter.removeWrappers( '.wrapper' );

		assert.strictEqual(
			$( '#mw-content-text' ).html(),
			'Utterance zero.'
		);
		assert.strictEqual( $( '.wrapper' ).contents().length, 0 );
	} );

	QUnit.test( 'removeWrappers(): restore text nodes as one', function ( assert ) {
		assert.expect( 3 );
		$( '#mw-content-text' )
			.html( 'prefix <span class="wrapper">Utterance zero.</span> suffix' );
		$( '#utterance-0 content text' )
			.attr( {
				path: './span/text()',
				'start-offset': '7',
				'end-offset': '19'
			} );

		mw.wikispeech.highlighter.removeWrappers( '.wrapper' );

		assert.strictEqual( $( '#mw-content-text' ).html(),
			'prefix Utterance zero. suffix'
		);
		assert.strictEqual( $( '.wrapper' ).contents().length, 0 );
		assert.strictEqual( $( '#mw-content-text' ).contents().length, 1 );
	} );

	QUnit.test( 'removeWrappers(): restore text nodes as one with inner wrapper', function ( assert ) {
		assert.expect( 2 );
		$( '#mw-content-text' )
			.html( '<span class="outer-wrapper">Utterance <span class="inner-wrapper">zero</span>.</span>' );
		$( '#utterance-0' ).attr( {
			'start-offset': '7',
			'end-offset': '19'
		} );

		mw.wikispeech.highlighter.removeWrappers( '.outer-wrapper' );

		assert.strictEqual(
			$( '#mw-content-text' ).html(),
			'Utterance <span class="inner-wrapper">zero</span>.'
		);
		assert.strictEqual( $( '.outer-wrapper' ).contents().length, 0 );
	} );

	QUnit.test( 'removeWrappers(): multiple wrappers', function ( assert ) {
		assert.expect( 3 );
		$( '#mw-content-text' )
			.html( '<span class="wrapper">Utterance</span> <span class="wrapper">zero.</span>' );

		mw.wikispeech.highlighter.removeWrappers( '.wrapper' );

		assert.strictEqual(
			$( '#mw-content-text' ).html(),
			'Utterance zero.'
		);
		assert.strictEqual( $( '#mw-content-text' ).contents().length, 1 );
		assert.strictEqual( $( '.wrapper' ).contents().length, 0 );
	} );

	QUnit.test( 'highlightToken()', function ( assert ) {
		var textElement, highlightedToken;

		assert.expect( 1 );
		textElement = $( '#utterance-0 content text' ).get( 0 );
		highlightedToken = $( '<token></token>' )
			.prop( {
				textElements: [ textElement ],
				startOffset: 0,
				endOffset: 8
			} )
			.appendTo( 'tokens' )
			.get( 0 );

		mw.wikispeech.highlighter.highlightToken( highlightedToken );

		assert.strictEqual(
			$( '#mw-content-text' ).html(),
			'<span class="ext-wikispeech-highlight-word">Utterance</span> zero.'
		);
	} );

	QUnit.test( 'highlightToken(): multiple utterances', function ( assert ) {
		var textElement, highlightedToken;

		assert.expect( 1 );
		$( '#mw-content-text' )
			.html( 'Utterance zero. Utterance one.' );
		$( '<utterance></utterance>' )
			.attr( {
				id: 'utterance-1',
				'start-offset': '16'
			} )
			.append( '<content></content>' )
			.append( '<tokens></tokens>' )
			.appendTo( 'utterances' );
		textElement = $( '<text></text>' )
			.attr( 'path', './text()' )
			.appendTo( '#utterance-1 content' )
			.get( 0 );
		highlightedToken = $( '<token></token>' )
			.prop( {
				textElements: [ textElement ],
				startOffset: 16,
				endOffset: 24
			} )
			.appendTo( '#utterance-1 tokens' )
			.get( 0 );

		mw.wikispeech.highlighter.highlightToken( highlightedToken );

		assert.strictEqual(
			$( '#mw-content-text' ).html(),
			'Utterance zero. <span class="ext-wikispeech-highlight-word">Utterance</span> one.'
		);
	} );

	QUnit.test( 'highlightToken(): with utterance highlighting', function ( assert ) {
		var textElement, highlightedToken;

		assert.expect( 1 );
		$( '#mw-content-text' )
			.html( '<span class="ext-wikispeech-highlight-sentence">Utterance with token.</span>' );
		$( '.ext-wikispeech-highlight-sentence' )
			.prop( 'textPath', './text()' );
		textElement = $( '<text></text>' )
			.attr( 'path', './text()' )
			.appendTo( 'content' )
			.get( 0 );
		highlightedToken = $( '<token></token>' )
			.prop( {
				textElements: [ textElement ],
				startOffset: 15,
				endOffset: 19
			} )
			.appendTo( 'tokens' )
			.get( 0 );

		mw.wikispeech.highlighter.highlightToken( highlightedToken );

		assert.strictEqual(
			$( '#mw-content-text' ).html(),
			'<span class="ext-wikispeech-highlight-sentence">Utterance with <span class="ext-wikispeech-highlight-word">token</span>.</span>'
		);
	} );

	QUnit.test( 'highlightToken(): with utterance highlighting and multiple utterances', function ( assert ) {
		var textElement, highlightedToken;

		assert.expect( 1 );
		$( '#mw-content-text' )
			.html(
				'Utterance zero. <span class="ext-wikispeech-highlight-sentence">Utterance one.</span>'
			);
		$( '.ext-wikispeech-highlight-sentence' )
			.prop( 'textPath', './text()' );
		$( '<utterance></utterance>' )
			.attr( {
				id: 'utterance-1',
				'start-offset': 16
			} )
			.append( '<content></content>' )
			.append( '<tokens></tokens>' )
			.appendTo( 'utterances' );
		textElement = $( '<text></text>' )
			.attr( 'path', './text()' )
			.appendTo( '#utterance-1 content' )
			.get( 0 );
		highlightedToken = $( '<token></token>' )
			.prop( {
				textElements: [ textElement ],
				startOffset: 16,
				endOffset: 24
			} )
			.appendTo( '#utterance-1 tokens' )
			.get( 0 );

		mw.wikispeech.highlighter.highlightToken( highlightedToken );

		assert.strictEqual(
			$( '#mw-content-text' ).html(),
			'Utterance zero. <span class="ext-wikispeech-highlight-sentence"><span class="ext-wikispeech-highlight-word">Utterance</span> one.</span>'
		);
	} );

	QUnit.test( 'highlightToken(): with utterance highlighting and other spans', function ( assert ) {
		var textElement, highlightedToken;

		assert.expect( 1 );
		$( '#mw-content-text' )
			.html( '<span><span class="ext-wikispeech-highlight-sentence">Utterance with token.</span></span>' );
		$( '.ext-wikispeech-highlight-sentence' )
			.prop( 'textPath', './span/text()' );
		textElement = $( '<text></text>' )
			.attr( 'path', './span/text()' )
			.appendTo( 'content' )
			.get( 0 );
		highlightedToken = $( '<token></token>' )
			.prop( {
				textElements: [ textElement ],
				startOffset: 15,
				endOffset: 19
			} )
			.appendTo( 'tokens' )
			.get( 0 );

		mw.wikispeech.highlighter.highlightToken( highlightedToken );

		assert.strictEqual(
			$( '#mw-content-text' ).html(),
			'<span><span class="ext-wikispeech-highlight-sentence">Utterance with <span class="ext-wikispeech-highlight-word">token</span>.</span></span>'
		);
	} );

	QUnit.test( 'highlightToken(): with tags', function ( assert ) {
		var textElement, highlightedToken;

		assert.expect( 1 );
		$( '#mw-content-text' ).html( 'Utterance with <br>token.' );
		$( '#utterance-0 content' ).empty();
		textElement = $( '<text></text>' )
			.attr( 'path', './text()[2]' )
			.appendTo( 'content' )
			.get( 0 );
		highlightedToken = $( '<token></token>' )
			.prop( {
				textElements: [ textElement ],
				startOffset: 0,
				endOffset: 4
			} )
			.appendTo( 'tokens' )
			.get( 0 );

		mw.wikispeech.highlighter.highlightToken( highlightedToken );

		assert.strictEqual(
			$( '#mw-content-text' ).html(),
			'Utterance with <br><span class="ext-wikispeech-highlight-word">token</span>.'
		);
	} );

	QUnit.test( 'highlightToken(): with multiple utterance highlightings', function ( assert ) {
		var textElement, highlightedToken;

		assert.expect( 1 );
		$( '#mw-content-text' ).html( '<span class="ext-wikispeech-highlight-sentence">Phrase </span><b><span class="ext-wikispeech-highlight-sentence">one</span></b><span class="ext-wikispeech-highlight-sentence">, phrase two.</span>' );
		$( '.ext-wikispeech-highlight-sentence' )
			.get( 2 ).textPath = './text()[2]';
		textElement = $( '<text></text>' )
			.attr( 'path', './text()[2]' )
			.appendTo( 'content' )
			.get( 0 );
		highlightedToken = $( '<token></token>' )
			.prop( {
				textElements: [ textElement ],
				startOffset: 2,
				endOffset: 7
			} )
			.appendTo( 'tokens' )
			.get( 0 );

		mw.wikispeech.highlighter.highlightToken( highlightedToken );

		assert.strictEqual(
			$( '#mw-content-text' ).html(),
			'<span class="ext-wikispeech-highlight-sentence">Phrase </span><b><span class="ext-wikispeech-highlight-sentence">one</span></b><span class="ext-wikispeech-highlight-sentence">, <span class="ext-wikispeech-highlight-word">phrase</span> two.</span>'
		);
	} );

	QUnit.test( 'highlightToken(): with multiple utterance highlightings and text nodes', function ( assert ) {
		var textElement, highlightedToken;

		assert.expect( 1 );
		$( '#mw-content-text' ).html( 'Utterance <b>zero</b>. <span class="ext-wikispeech-highlight-sentence">Utterance one.</span>' );
		$( '<utterance></utterance>' )
			.attr( {
				id: 'utterance-1',
				'start-offset': '2'
			} )
			.append( $( '<content></content>' ) )
			.append( $( '<tokens></tokens>' ) )
			.appendTo( 'utterances' );
		$( '.ext-wikispeech-highlight-sentence' )
			.prop( 'textPath', './text()[2]' );
		textElement = $( '<text></text>' )
			.attr( 'path', './text()[2]' )
			.appendTo( '#utterance-1 content' )
			.get( 0 );
		highlightedToken = $( '<token></token>' )
			.prop( {
				textElements: [ textElement ],
				startOffset: 2,
				endOffset: 10
			} )
			.appendTo( '#utterance-1 tokens' )
			.get( 0 );

		mw.wikispeech.highlighter.highlightToken( highlightedToken );

		assert.strictEqual(
			$( '#mw-content-text' ).html(),
			'Utterance <b>zero</b>. <span class="ext-wikispeech-highlight-sentence"><span class="ext-wikispeech-highlight-word">Utterance</span> one.</span>'
		);
	} );
} )( mediaWiki, jQuery );
