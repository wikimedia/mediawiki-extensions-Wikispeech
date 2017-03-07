( function ( mw, $ ) {
	var server;

	QUnit.module( 'ext.wikispeech', {
		setup: function () {
			var $utterances;

			mw.wikispeech.wikispeech = new mw.wikispeech.Wikispeech();
			// Mock highlighter for methods that are called as side
			// effects.
			mw.wikispeech.highlighter = {
				removeWrappers: sinon.spy(),
				highlightUtterance: function () {},
				highlightToken: function () {}
			};
			server = sinon.fakeServer.create();
			// overrideMimeType() isn't defined by default.
			server.xhr.prototype.overrideMimeType = function () {};
			$( '#qunit-fixture' ).append(
				$( '<div></div>' ).attr( 'id', 'content' )
			);
			$utterances = $( '#qunit-fixture' ).append(
				$( '<utterances></utterances>' )
			);
			$( '<utterance></utterance>' )
				.attr( {
					id: 'utterance-0',
					'start-offset': '0'
				} )
				.appendTo( $utterances );
			$( '<utterance></utterance>' )
				.attr( 'id', 'utterance-1' )
				.appendTo( $utterances );
			mw.config.set(
				'wgWikispeechKeyboardShortcuts', {
					playStop: {
						key: 32,
						modifiers: [ 'ctrl' ]
					},
					skipAheadSentence: {
						key: 39,
						modifiers: [ 'ctrl' ]
					},
					skipBackSentence: {
						key: 37,
						modifiers: [ 'ctrl' ]
					},
					skipAheadWord: {
						key: 40,
						modifiers: [ 'ctrl' ]
					},
					skipBackWord: {
						key: 38,
						modifiers: [ 'ctrl' ]
					}
				}
			);
			mw.config.set(
				'wgWikispeechSkipBackRewindsThreshold',
				3.0
			);
			mw.config.set(
				'wgWikispeechServerUrl',
				'http://server.url/wikispeech/'
			);
		},
		teardown: function () {
			server.restore();
		}
	} );

	QUnit.test( 'prepareUtterance()', function ( assert ) {
		assert.expect( 1 );
		sinon.spy( mw.wikispeech.wikispeech, 'loadAudio' );

		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );

		assert.strictEqual(
			mw.wikispeech.wikispeech.loadAudio.calledWith( $( '#utterance-0' ) ),
			true
		);
	} );

	// jscs:disable validateQuoteMarks
	QUnit.test( "prepareUtterance(): don't request if waiting for response", function ( assert ) {
		// jscs:enable validateQuoteMarks
		assert.expect( 1 );
		sinon.spy( mw.wikispeech.wikispeech, 'loadAudio' );
		$( '#utterance-0' ).prop( 'waitingForResponse', true );

		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );

		assert.strictEqual( mw.wikispeech.wikispeech.loadAudio.notCalled, true );
	} );

	// jscs:disable validateQuoteMarks
	QUnit.test( "prepareUtterance(): don't load audio if already loaded", function ( assert ) {
		// jscs:enable validateQuoteMarks
		assert.expect( 1 );
		$( '<content></content>' )
			.append( $( '<text></text>' ).text( 'An utterance.' ) )
			.appendTo( '#utterance-0' );
		$( '<audio></audio>' )
			.attr( 'src', 'http://server.url/audio' )
			.appendTo( '#utterance-0' );
		sinon.spy( mw.wikispeech.wikispeech, 'loadAudio' );

		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );

		assert.strictEqual( mw.wikispeech.wikispeech.loadAudio.notCalled, true );
	} );

	QUnit.test( 'prepareUtterance(): prepare next utterance when playing', function ( assert ) {
		var $nextUtterance = $( '#utterance-1' );
		assert.expect( 1 );
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );
		sinon.spy( mw.wikispeech.wikispeech, 'prepareUtterance' );

		$( '#utterance-0 audio' ).trigger( 'play' );

		assert.strictEqual(
			mw.wikispeech.wikispeech.prepareUtterance.calledWith(
				$nextUtterance
			),
			true
		);
	} );

	// jscs:disable validateQuoteMarks
	QUnit.test( "prepareUtterance(): don't prepare next audio if it doesn't exist", function ( assert ) {
		// jscs:enable validateQuoteMarks
		assert.expect( 1 );
		sinon.spy( mw.wikispeech.wikispeech, 'prepareUtterance' );
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-1' ) );

		$( '#utterance-1 audio' ).trigger( 'play' );

		assert.strictEqual(
			mw.wikispeech.wikispeech.prepareUtterance.calledWith(
				$( '#utterance-2' )
			),
			false
		);
	} );

	QUnit.test( 'prepareUtterance(): play next utterance when ended', function ( assert ) {
		var $nextAudio;

		assert.expect( 1 );
		// Assume that both utterances are prepared.
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-1' ) );
		$nextAudio = $( '#utterance-1' ).children( 'audio' ).get( 0 );
		sinon.spy( $nextAudio, 'play' );
		mw.wikispeech.wikispeech.playUtterance( $( '#utterance-0' ) );

		$( '#utterance-0 audio' ).trigger( 'ended' );

		assert.strictEqual( $nextAudio.play.called, true );
	} );

	QUnit.test( 'prepareUtterance(): stop when end of text is reached', function ( assert ) {
		var $lastUtterance = $( '#utterance-1' );
		assert.expect( 1 );
		sinon.spy( mw.wikispeech.wikispeech, 'stop' );
		mw.wikispeech.wikispeech.prepareUtterance( $lastUtterance );
		mw.wikispeech.wikispeech.playUtterance( $lastUtterance );

		$lastUtterance.children( 'audio' ).trigger( 'ended' );

		assert.strictEqual( mw.wikispeech.wikispeech.stop.called, true );
	} );

	QUnit.test( 'loadAudio()', function ( assert ) {
		assert.expect( 2 );
		$( '<content></content>' )
			.append( $( '<text></text>' ).text( 'An utterance.' ) )
			.appendTo( '#utterance-0' );
		sinon.spy( mw.wikispeech.wikispeech, 'requestTts' );

		mw.wikispeech.wikispeech.loadAudio( $( '#utterance-0' ) );

		assert.strictEqual( mw.wikispeech.wikispeech.requestTts.called, true );
		assert.strictEqual(
			server.requests[ 0 ].requestBody,
			'lang=en&input_type=text&input=An+utterance.'
		);
	} );

	QUnit.test( 'loadAudio(): request successful', function ( assert ) {
		assert.expect( 3 );
		$( '<content></content>' )
			.append( $( '<text></text>' ).text( 'An utterance.' ) )
			.appendTo( '#utterance-0' );
		$( '<audio></audio>' ).appendTo( '#utterance-0' );
		$( '#utterance-0' ).prop( 'waitingForResponse', true );
		server.respondWith(
			'{"audio": "http://server.url/audio", "tokens": [{"orth": "An"}, {"orth": "utterance"}, {"orth": "."}]}'
		);
		sinon.spy( mw.wikispeech.wikispeech, 'addTokenElements' );

		mw.wikispeech.wikispeech.loadAudio( $( '#utterance-0' ) );

		server.respond();

		assert.strictEqual(
			$( '#utterance-0 audio' ).attr( 'src' ),
			'http://server.url/audio'
		);
		assert.strictEqual(
			mw.wikispeech.wikispeech.addTokenElements.calledWith(
				$( '#utterance-0' ),
				[ { orth: 'An' }, { orth: 'utterance' }, { orth: '.' } ]
			),
			true
		);
		assert.strictEqual(
			$( '#utterance-0' ).prop( 'waitingForResponse' ),
			false
		);
	} );

	QUnit.test( 'loadAudio(): request failed', function ( assert ) {
		assert.expect( 3 );
		$( '<content></content>' )
			.append( $( '<text></text>' ).text( 'An utterance.' ) )
			.appendTo( '#utterance-0' );
		$( '<audio></audio>' ).appendTo( '#utterance-0' );
		$( '#utterance-0' ).prop( 'waitingForResponse', true );
		server.respondWith( [ 404, {}, '' ] );
		sinon.spy( mw.wikispeech.wikispeech, 'addTokenElements' );
		mw.wikispeech.wikispeech.loadAudio( $( '#utterance-0' ) );

		server.respond();

		assert.strictEqual( mw.wikispeech.wikispeech.addTokenElements.notCalled, true );
		assert.strictEqual(
			$( '#utterance-0' ).prop( 'waitingForResponse' ),
			false
		);
		assert.strictEqual(
			$( '#utterance-0 audio' ).attr( 'src' ),
			undefined
		);
	} );

	QUnit.test( 'addControlPanel()', function ( assert ) {
		assert.expect( 5 );

		mw.wikispeech.wikispeech.addControlPanel();

		assert.strictEqual(
			$( '#ext-wikispeech-control-panel #ext-wikispeech-play-stop-button' ).length,
			1
		);
		assert.strictEqual(
			$( '#ext-wikispeech-control-panel .ext-wikispeech-skip-ahead-sentence' ).length,
			1
		);
		assert.strictEqual(
			$( '#ext-wikispeech-control-panel .ext-wikispeech-skip-back-sentence' ).length,
			1
		);
		assert.strictEqual(
			$( '#ext-wikispeech-control-panel .ext-wikispeech-skip-ahead-word' ).length,
			1
		);
		assert.strictEqual(
			$( '#ext-wikispeech-control-panel .ext-wikispeech-skip-back-word' ).length,
			1
		);
	} );

	QUnit.test( 'addControlPanel(): add help button if page is set', function ( assert ) {
		assert.expect( 2 );
		mw.config.set( 'wgArticlePath', '/wiki/$1' );
		mw.config.set( 'wgWikispeechHelpPage', 'Help' );
		mw.wikispeech.wikispeech.addControlPanel();

		assert.strictEqual(
			$( '#ext-wikispeech-control-panel .ext-wikispeech-help' ).length,
			1
		);
		assert.strictEqual(
			$( '#ext-wikispeech-control-panel .ext-wikispeech-help' )
				.parent()
				.attr( 'href' ),
			'/wiki/Help'
		);
	} );

	QUnit.test( 'addControlPanel(): do not add help button if page is not set', function ( assert ) {
		assert.expect( 1 );
		mw.config.set(
			'wgWikispeechHelpPage',
			null
		);
		mw.wikispeech.wikispeech.addControlPanel();

		assert.strictEqual(
			$( '#ext-wikispeech-control-panel #ext-wikispeech-help' ).length,
			0
		);
	} );

	QUnit.test( 'addControlPanel(): add feedback button', function ( assert ) {
		assert.expect( 2 );
		mw.config.set( 'wgArticlePath', '/wiki/$1' );
		mw.config.set( 'wgWikispeechFeedbackPage', 'Feedback' );

		mw.wikispeech.wikispeech.addControlPanel();

		assert.strictEqual(
			$( '#ext-wikispeech-control-panel .ext-wikispeech-feedback' )
				.length,
			1
		);
		assert.strictEqual(
			$( '#ext-wikispeech-control-panel .ext-wikispeech-feedback' )
				.parent()
				.attr( 'href' ),
			'/wiki/Feedback'
		);
	} );

	QUnit.test( 'addControlPanel(): do not add feedback button if page is not set', function ( assert ) {
		assert.expect( 1 );
		mw.config.set( 'wgWikispeechFeedbackPage', null );

		mw.wikispeech.wikispeech.addControlPanel();

		assert.strictEqual(
			$( '#ext-wikispeech-control-panel #ext-wikispeech-feedback' )
				.length,
			0
		);
	} );

	QUnit.test( 'Clicking play/stop button', function ( assert ) {
		testClickButton(
			assert,
			'playOrStop',
			'#ext-wikispeech-play-stop-button'
		);
	} );

	/**
	 * Test that clicking a button calls the correct function.
	 *
	 * @param {QUnit.assert} assert
	 * @param {string} functionName Name of the function that should
	 *  be called.
	 * @param {string} buttonId Id of the button that is clicked.
	 */

	function testClickButton( assert, functionName, buttonSelector ) {
		assert.expect( 1 );
		sinon.stub( mw.wikispeech.wikispeech, functionName );
		mw.wikispeech.wikispeech.addControlPanel();

		$( buttonSelector ).click();

		assert.strictEqual( mw.wikispeech.wikispeech[ functionName ].called, true );
	}

	QUnit.test( 'Clicking skip ahead sentence button', function ( assert ) {
		testClickButton(
			assert,
			'skipAheadUtterance',
			'.ext-wikispeech-skip-ahead-sentence'
		);
	} );

	QUnit.test( 'Clicking skip back sentence button', function ( assert ) {
		testClickButton(
			assert,
			'skipBackUtterance',
			'.ext-wikispeech-skip-back-sentence'
		);
	} );

	QUnit.test( 'Clicking skip ahead word button', function ( assert ) {
		testClickButton(
			assert,
			'skipAheadToken',
			'.ext-wikispeech-skip-ahead-word'
		);
	} );

	QUnit.test( 'playOrStop(): play', function ( assert ) {
		assert.expect( 1 );
		sinon.stub( mw.wikispeech.wikispeech, 'play' );

		mw.wikispeech.wikispeech.playOrStop();

		assert.strictEqual( mw.wikispeech.wikispeech.play.called, true );
	} );

	QUnit.test( 'playOrStop(): stop', function ( assert ) {
		assert.expect( 1 );
		mw.wikispeech.wikispeech.$currentUtterance = $( '#utterance-0' );
		sinon.stub( mw.wikispeech.wikispeech, 'stop' );

		mw.wikispeech.wikispeech.playOrStop();

		assert.strictEqual( mw.wikispeech.wikispeech.stop.called, true );
	} );

	QUnit.test( 'Pressing keyboard shortcut for play/stop', function ( assert ) {
		testKeyboardShortcut( assert, 'playOrStop', 32, 'c' );
	} );

	/**
	 * Test that a keyboard event triggers the correct function.
	 *
	 * @param {QUnit.assert} assert
	 * @param {string} functionName Name of the function that should
	 *  be called.
	 * @param {number} keyCode The key code for the event.
	 * @param {string} modifiers A string that defines the
	 *  modifiers. The characters c, a and s triggers the modifiers
	 *  for ctrl, alt and shift, respectively.
	 */

	function testKeyboardShortcut( assert, functionName, keyCode, modifiers ) {
		assert.expect( 1 );
		mw.wikispeech.wikispeech.addKeyboardShortcuts();
		sinon.stub( mw.wikispeech.wikispeech, functionName );

		$( document ).trigger( createKeydownEvent( keyCode, modifiers ) );

		assert.strictEqual( mw.wikispeech.wikispeech[ functionName ].called, true );
	}

	/**
	 * Create a keydown event.
	 *
	 * @param {number} keyCode The key code for the event.
	 * @param {string} modifiers A string that defines the
	 *  modifiers. The characters c, a and s triggers the modifiers
	 *  for ctrl, alt and shift, respectively.
	 * @return {jQuery} The created keydown event.
	 */

	function createKeydownEvent( keyCode, modifiers ) {
		var event = $.Event( 'keydown' );
		event.which = keyCode;
		event.ctrlKey = modifiers.indexOf( 'c' ) >= 0;
		event.altKey = modifiers.indexOf( 'a' ) >= 0;
		event.shiftKey = modifiers.indexOf( 's' ) >= 0;
		return event;
	}

	QUnit.test( 'Pressing keyboard shortcut for skipping ahead sentence', function ( assert ) {
		testKeyboardShortcut( assert, 'skipAheadUtterance', 39, 'c' );
	} );

	QUnit.test( 'Pressing keyboard shortcut for skipping back sentence', function ( assert ) {
		testKeyboardShortcut( assert, 'skipBackUtterance', 37, 'c' );
	} );

	QUnit.test( 'Pressing keyboard shortcut for skipping ahead word', function ( assert ) {
		testKeyboardShortcut( assert, 'skipAheadToken', 40, 'c' );
	} );

	QUnit.test( 'Pressing keyboard shortcut for skipping back word', function ( assert ) {
		testKeyboardShortcut( assert, 'skipBackToken', 38, 'c' );
	} );

	QUnit.test( 'stop()', function ( assert ) {
		assert.expect( 4 );
		mw.wikispeech.wikispeech.addControlPanel();
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );
		mw.wikispeech.wikispeech.play();
		$( '#utterance-0 audio' ).prop( 'currentTime', 1.0 );

		mw.wikispeech.wikispeech.stop();

		assert.strictEqual( $( '#utterance-0 audio' ).prop( 'paused' ), true );
		assert.strictEqual(
			$( '#utterance-0 audio' ).prop( 'currentTime' ),
			0.0
		);
		assert.strictEqual(
			$( '#ext-wikispeech-play-stop-button' )
				.hasClass( 'ext-wikispeech-play' ),
			true
		);
		assert.strictEqual(
			$( '#ext-wikispeech-play-stop-button' )
				.hasClass( 'ext-wikispeech-stop' ),
			false
		);
	} );

	QUnit.test( 'play()', function ( assert ) {
		var $firstUtterance = $( '#utterance-0' );
		assert.expect( 3 );
		mw.wikispeech.wikispeech.addControlPanel();
		mw.wikispeech.wikispeech.prepareUtterance( $firstUtterance );

		mw.wikispeech.wikispeech.play();

		assert.strictEqual(
			$firstUtterance.children( 'audio' ).prop( 'paused' ),
			false
		);
		assert.strictEqual(
			$( '#ext-wikispeech-play-stop-button' )
				.hasClass( 'ext-wikispeech-stop' ),
			true
		);
		assert.strictEqual(
			$( '#ext-wikispeech-play-stop-button' )
				.hasClass( 'ext-wikispeech-play' ),
			false
		);
	} );

	QUnit.test( 'skipAheadUtterance()', function ( assert ) {
		assert.expect( 2 );
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-1' ) );
		mw.wikispeech.wikispeech.play();

		mw.wikispeech.wikispeech.skipAheadUtterance();

		assert.strictEqual( $( '#utterance-0 audio' ).prop( 'paused' ), true );
		assert.strictEqual(
			$( '#utterance-1 audio' ).prop( 'paused' ),
			false
		);
	} );

	QUnit.test( 'skipAheadUtterance(): stop if no next utterance', function ( assert ) {
		assert.expect( 1 );
		sinon.spy( mw.wikispeech.wikispeech, 'stop' );
		mw.wikispeech.wikispeech.$currentUtterance = $( '#utterance-1' );

		mw.wikispeech.wikispeech.skipAheadUtterance();

		assert.strictEqual( mw.wikispeech.wikispeech.stop.called, true );
	} );

	QUnit.test( 'skipBackUtterance()', function ( assert ) {
		assert.expect( 2 );
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-1' ) );
		mw.wikispeech.wikispeech.playUtterance( $( '#utterance-1' ) );

		mw.wikispeech.wikispeech.skipBackUtterance();

		assert.strictEqual( $( '#utterance-1 audio' ).prop( 'paused' ), true );
		assert.strictEqual(
			$( '#utterance-0 audio' ).prop( 'paused' ),
			false
		);
	} );

	QUnit.test( 'skipBackUtterance(): restart if first utterance', function ( assert ) {
		assert.expect( 2 );
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );
		mw.wikispeech.wikispeech.playUtterance( $( '#utterance-0' ) );
		$( '#utterance-0 audio' ).prop( 'currentTime', 1.0 );

		mw.wikispeech.wikispeech.skipBackUtterance();

		assert.strictEqual(
			$( '#utterance-0 audio' ).prop( 'paused' ),
			false
		);
		assert.strictEqual(
			$( '#utterance-0 audio' ).prop( 'currentTime' ),
			0.0
		);
	} );

	QUnit.test( 'skipBackUtterance(): restart if played long enough', function ( assert ) {
		assert.expect( 3 );
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-1' ) );
		mw.wikispeech.wikispeech.playUtterance( $( '#utterance-1' ) );
		$( '#utterance-1 audio' ).prop( 'currentTime', 3.1 );

		mw.wikispeech.wikispeech.skipBackUtterance();

		assert.strictEqual(
			$( '#utterance-1 audio' ).prop( 'paused' ),
			false
		);
		assert.strictEqual(
			$( '#utterance-1 audio' ).prop( 'currentTime' ),
			0.0
		);
		assert.strictEqual(
			$( '#utterance-0 audio' ).prop( 'paused' ),
			true
		);
	} );

	QUnit.test( 'getNextUtterance()', function ( assert ) {
		var $nextUtterance;

		assert.expect( 1 );

		$nextUtterance = mw.wikispeech.wikispeech.getNextUtterance( $( '#utterance-0' ) );

		assert.strictEqual(
			$nextUtterance.get( 0 ),
			$( '#utterance-1' ).get( 0 )
		);
	} );

	QUnit.test( 'getNextUtterance(): return the empty object if no current utterance', function ( assert ) {
		var $nextUtterance;

		assert.expect( 1 );

		$nextUtterance = mw.wikispeech.wikispeech.getNextUtterance( $() );

		assert.strictEqual( $nextUtterance.length, 0 );
	} );

	QUnit.test( 'addTokenElements()', function ( assert ) {
		var tokens, $tokensElement, $content, textElement;

		assert.expect( 9 );
		addContentText( 'An utterance.' );
		$content = $( '<content></content>' )
			.appendTo( '#utterance-0' );
		textElement = $( '<text></text>' )
			.text( 'An utterance.' )
			.appendTo( $content )
			.get( 0 );
		tokens = [
			{
				orth: 'An',
				endtime: 1.0
			},
			{
				orth: 'utterance',
				endtime: 2.0
			},
			{
				orth: '.',
				endtime: 3.0
			}
		];

		mw.wikispeech.wikispeech.addTokenElements(
			$( '#utterance-0' ),
			tokens
		);

		$tokensElement = $( '#utterance-0' ).children( 'tokens' );
		assert.deepEqual(
			$tokensElement.children().get( 0 ).textElements,
			[ textElement ]
		);
		assert.strictEqual(
			$tokensElement.children().get( 0 ).startOffset,
			0
		);
		assert.strictEqual(
			$tokensElement.children().get( 0 ).endOffset,
			1
		);
		assert.deepEqual(
			$tokensElement.children().get( 1 ).textElements,
			[ textElement ]
		);
		assert.strictEqual(
			$tokensElement.children().get( 1 ).startOffset,
			3
		);
		assert.strictEqual(
			$tokensElement.children().get( 1 ).endOffset,
			11
		);
		assert.deepEqual(
			$tokensElement.children().get( 2 ).textElements,
			[ textElement ]
		);
		assert.strictEqual(
			$tokensElement.children().get( 2 ).startOffset,
			12
		);
		assert.strictEqual(
			$tokensElement.children().get( 2 ).endOffset,
			12
		);
	} );

	/**
	 * Add a mw-content-text div element to the QUnit fixture.
	 *
	 * @param {string} html The HTML added to the div element.
	 */

	function addContentText( html ) {
		$( '#qunit-fixture' ).append(
			$( '<div></div>' )
				.attr( 'id', 'mw-content-text' )
				.html( html )
		);
	}

	QUnit.test( 'addTokenElements(): handle tag', function ( assert ) {
		var tokens, $tokensElement, $content, textElement1,
			textElement2, textElement3;

		assert.expect( 12 );
		addContentText( 'Utterance with <b>tag</b>.' );
		$content = $( '<content></content>' )
			.appendTo( '#utterance-0' );
		textElement1 = $( '<text></text>' )
			.text( 'Utterance with ' )
			.appendTo( $content )
			.get( 0 );
		textElement2 = $( '<text></text>' )
			.text( 'tag' )
			.appendTo( $content )
			.get( 0 );
		textElement3 = $( '<text></text>' )
			.text( '.' )
			.appendTo( $content )
			.get( 0 );
		tokens = [
			{
				orth: 'Utterance',
				endtime: 1.0
			},
			{
				orth: 'with',
				endtime: 2.0
			},
			{
				orth: 'tag',
				endtime: 3.0
			},
			{
				orth: '.',
				endtime: 4.0
			}
		];

		mw.wikispeech.wikispeech.addTokenElements( $( '#utterance-0' ), tokens );

		$tokensElement = $( '#utterance-0' ).children( 'tokens' );
		assert.deepEqual(
			$tokensElement.children().get( 0 ).textElements,
			[ textElement1 ]
		);
		assert.strictEqual(
			$tokensElement.children().get( 0 ).startOffset,
			0
		);
		assert.strictEqual(
			$tokensElement.children().get( 0 ).endOffset,
			8
		);
		assert.deepEqual(
			$tokensElement.children().get( 1 ).textElements,
			[ textElement1 ]
		);
		assert.strictEqual(
			$tokensElement.children().get( 1 ).startOffset,
			10
		);
		assert.strictEqual(
			$tokensElement.children().get( 1 ).endOffset,
			13
		);
		assert.deepEqual(
			$tokensElement.children().get( 2 ).textElements,
			[ textElement2 ]
		);
		assert.strictEqual(
			$tokensElement.children().get( 2 ).startOffset,
			0
		);
		assert.strictEqual(
			$tokensElement.children().get( 2 ).endOffset,
			2
		);
		assert.deepEqual(
			$tokensElement.children().get( 3 ).textElements,
			[ textElement3 ]
		);
		assert.strictEqual(
			$tokensElement.children().get( 3 ).startOffset,
			0
		);
		assert.strictEqual(
			$tokensElement.children().get( 3 ).endOffset,
			0
		);
	} );

	QUnit.test( 'addTokenElements(): handle removed element', function ( assert ) {
		var tokens, $tokensElement, $content, textElement;

		assert.expect( 3 );
		addContentText( 'Utterance with <del>removed tag</del>.' );
		$content = $( '<content></content>' )
			.append( $( '<text></text>' ).text( 'Utterance with ' ) )
			.appendTo( '#utterance-0' );
		textElement = $( '<text></text>' )
			.text( '.' )
			.appendTo( $content )
			.get( 0 );
		tokens = [
			{
				orth: 'Utterance',
				endtime: 1.0
			},
			{
				orth: 'with',
				endtime: 2.0
			},
			{
				orth: '.',
				endtime: 3.0
			}
		];

		mw.wikispeech.wikispeech.addTokenElements( $( '#utterance-0' ), tokens );

		$tokensElement = $( '#utterance-0 tokens' );
		assert.deepEqual(
			$tokensElement.children().get( 2 ).textElements,
			[ textElement ]
		);
		assert.strictEqual(
			$tokensElement.children().get( 2 ).startOffset,
			0
		);
		assert.strictEqual(
			$tokensElement.children().get( 2 ).endOffset,
			0
		);
	} );

	QUnit.test( 'addTokenElements(): divided tokens', function ( assert ) {
		var tokens, $tokensElement, $content, textElement1,
			textElement2, textElement3;

		assert.expect( 3 );
		addContentText( 'Utterance with divided to<b>k</b>en.' );
		$content = $( '<content></content>' )
			.appendTo( '#utterance-0' );
		textElement1 = $( '<text></text>' )
			.text( 'Utterance with divided to' )
			.appendTo( $content )
			.get( 0 );
		textElement2 = $( '<text></text>' )
			.text( 'k' )
			.appendTo( $content )
			.get( 0 );
		textElement3 = $( '<text></text>' )
			.text( 'en.' )
			.appendTo( $content )
			.get( 0 );
		$( '#utterance-0' ).append( $content );
		tokens = [
			{ orth: 'Utterance' },
			{ orth: 'with' },
			{ orth: 'divided' },
			{ orth: 'token' },
			{ orth: '.' }
		];

		mw.wikispeech.wikispeech.addTokenElements( $( '#utterance-0' ), tokens );

		$tokensElement = $( '#utterance-0' ).children( 'tokens' );
		assert.deepEqual(
			$tokensElement.children().get( 3 ).textElements,
			[ textElement1, textElement2, textElement3 ]
		);
		assert.strictEqual(
			$tokensElement.children().get( 3 ).startOffset,
			23
		);
		assert.strictEqual(
			$tokensElement.children().get( 3 ).endOffset,
			1
		);
	} );

	QUnit.test( 'addTokenElements(): ambiguous tokens', function ( assert ) {
		var tokens, $tokensElement;

		assert.expect( 4 );
		addContentText( 'A word and the same word.' );
		$( '<content></content>' )
			.append(
				$( '<text></text>' )
					.text( 'A word and the same word.' )
			)
			.appendTo( '#utterance-0' );
		tokens = [
			{ orth: 'A' },
			{ orth: 'word' },
			{ orth: 'and' },
			{ orth: 'the' },
			{ orth: 'same' },
			{ orth: 'word' },
			{ orth: '.' }
		];

		mw.wikispeech.wikispeech.addTokenElements( $( '#utterance-0' ), tokens );

		$tokensElement = $( '#utterance-0' ).children( 'tokens' );
		assert.strictEqual(
			$tokensElement.children().get( 1 ).startOffset,
			2
		);
		assert.strictEqual(
			$tokensElement.children().get( 1 ).endOffset,
			5
		);
		assert.strictEqual(
			$tokensElement.children().get( 5 ).startOffset,
			20
		);
		assert.strictEqual(
			$tokensElement.children().get( 5 ).endOffset,
			23
		);
	} );

	QUnit.test( 'addTokenElements(): ambiguous tokens in tag', function ( assert ) {
		var tokens, $tokensElement;

		assert.expect( 2 );
		addContentText( 'Utterance with <b>word and word</b>.' );
		$( '<content></content>' )
			.append( $( '<text></text>' ).text( 'Utterance with ' ) )
			.append( $( '<text></text>' ).text( 'word and word' ) )
			.append( $( '<text></text>' ).text( '.' ) )
			.appendTo( '#utterance-0' );
		tokens = [
			{ orth: 'Utterance' },
			{ orth: 'with' },
			{ orth: 'word' },
			{ orth: 'and' },
			{ orth: 'word' },
			{ orth: '.' }
		];

		mw.wikispeech.wikispeech.addTokenElements( $( '#utterance-0' ), tokens );

		$tokensElement = $( '#utterance-0' ).children( 'tokens' );
		assert.strictEqual(
			$tokensElement.children().get( 4 ).startOffset,
			9
		);
		assert.strictEqual(
			$tokensElement.children().get( 4 ).endOffset,
			12
		);
	} );

	QUnit.test( 'addTokenElements(): multiple utterances', function ( assert ) {
		var tokens, $tokensElement;

		assert.expect( 6 );
		addContentText( 'An utterance. Another utterance.' );
		$( '#utterance-1' ).attr( 'start-offset', '14' );
		$( '<content></content>' )
			.append(
				$( '<text></text>' )
					.text( 'An utterance.' )
			)
			.appendTo( '#utterance-0' );
		$( '<content></content>' )
			.append(
				$( '<text></text>' )
					.text( 'Another utterance.' )
			)
			.appendTo( '#utterance-1' );
		tokens = [
			{ orth: 'Another' },
			{ orth: 'utterance' },
			{ orth: '.' }
		];

		mw.wikispeech.wikispeech.addTokenElements(
			$( '#utterance-1' ),
			tokens
		);

		$tokensElement = $( '#utterance-1' ).children( 'tokens' );
		assert.strictEqual(
			$tokensElement.children().get( 0 ).startOffset,
			14
		);
		assert.strictEqual(
			$tokensElement.children().get( 0 ).endOffset,
			20
		);
		assert.strictEqual(
			$tokensElement.children().get( 1 ).startOffset,
			22
		);
		assert.strictEqual(
			$tokensElement.children().get( 1 ).endOffset,
			30
		);
		assert.strictEqual(
			$tokensElement.children().get( 2 ).startOffset,
			31
		);
		assert.strictEqual(
			$tokensElement.children().get( 2 ).endOffset,
			31
		);
	} );

	QUnit.test( 'addTokenElements(): ambiguous, one character long tokens', function ( assert ) {
		var tokens, $tokensElement;

		assert.expect( 2 );
		addContentText( 'a a a.' );
		$( '<content></content>' )
			.append(
				$( '<text></text>' )
					.text( 'a a a.' )
			)
			.appendTo( '#utterance-0' );
		tokens = [
			{ orth: 'a' },
			{ orth: 'a' },
			{ orth: 'a' },
			{ orth: '.' }
		];

		mw.wikispeech.wikispeech.addTokenElements( $( '#utterance-0' ), tokens );

		$tokensElement = $( '#utterance-0' ).children( 'tokens' );
		assert.strictEqual(
			$tokensElement.children().get( 2 ).startOffset,
			4
		);
		assert.strictEqual(
			$tokensElement.children().get( 2 ).endOffset,
			4
		);
	} );

	QUnit.test( 'getCurrentToken()', function ( assert ) {
		var $tokens, token, expectedToken;

		assert.expect( 1 );
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( '#utterance-0' );
		addToken( $tokens, '', 0.0, 1.0 );
		expectedToken = addToken( $tokens, '', 1.0, 2.0 );
		addToken( $tokens, '', 2.0, 3.0 );
		$( '#utterance-0 audio' ).prop( 'currentTime', 1.1 );
		mw.wikispeech.wikispeech.play();

		token = mw.wikispeech.wikispeech.getCurrentToken().get( 0 );

		assert.strictEqual( token, expectedToken );
	} );

	/**
	 * Add a token element.
	 *
	 * @param {jQuery} $parent The jQuery to add the element to.
	 * @param {string} string The token string.
	 * @param {number} startTime The start time of the token.
	 * @param {number} endTime The end time of the token.
	 * @return {HTMLElement} The added token element.
	 */

	function addToken(
		$parent,
		string,
		startTime,
		endTime,
		textNodes,
		startOffset,
		endOffset
	) {
		var $token = $( '<token></token>' )
			.text( string )
			.prop( {
				startTime: startTime,
				endTime: endTime,
				textNodes: textNodes,
				startOffset: startOffset,
				endOffset: endOffset
			} );
		$parent.append( $token );
		return $token.get( 0 );
	}

	QUnit.test( 'getCurrentToken(): get first token', function ( assert ) {
		var $tokens, token, expectedToken;

		assert.expect( 1 );
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( '#utterance-0' );
		expectedToken = addToken( $tokens, '', 0.0, 1.0 );
		addToken( $tokens, '', 1.0, 2.0 );
		$( '#utterance-0 audio' ).prop( 'currentTime', 0.1 );
		mw.wikispeech.wikispeech.play();

		token = mw.wikispeech.wikispeech.getCurrentToken().get( 0 );

		assert.strictEqual( token, expectedToken );
	} );

	QUnit.test( 'getCurrentToken(): get the last token', function ( assert ) {
		var token, $tokens, expectedToken;

		assert.expect( 1 );
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( '#utterance-0' );
		addToken( $tokens, '', 0.0, 1.0 );
		addToken( $tokens, '', 1.0, 2.0 );
		expectedToken = addToken( $tokens, '', 2.0, 3.0 );
		$( '#utterance-0 audio' ).prop( 'currentTime', 2.1 );
		mw.wikispeech.wikispeech.play();

		token = mw.wikispeech.wikispeech.getCurrentToken().get( 0 );

		assert.strictEqual( token, expectedToken );
	} );

	QUnit.test( 'getCurrentToken(): get the last token when current time is equal to last tokens end time', function ( assert ) {
		var token, $tokens, expectedToken;

		assert.expect( 1 );
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( $( '#utterance-0' ) );
		addToken( $tokens, '', 0.0, 1.0 );
		expectedToken = addToken( $tokens, '', 1.0, 2.0 );
		$( '#utterance-0 audio' ).prop( 'currentTime', 2.0 );
		mw.wikispeech.wikispeech.play();

		token = mw.wikispeech.wikispeech.getCurrentToken().get( 0 );

		assert.strictEqual( token, expectedToken );
	} );

	QUnit.test( 'getCurrentToken(): ignore tokens with no duration', function ( assert ) {
		var token, $tokens, expectedToken;

		assert.expect( 1 );
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( '#utterance-0' );
		addToken( $tokens, '', 0.0, 1.0 );
		addToken( $tokens, '', 1.0, 1.0 );
		expectedToken = addToken( $tokens, '', 1.0, 2.0 );
		$( '#utterance-0 audio' ).prop( 'currentTime', 1.1 );
		mw.wikispeech.wikispeech.play();

		token = mw.wikispeech.wikispeech.getCurrentToken().get( 0 );

		assert.strictEqual( token, expectedToken );
	} );

	QUnit.test( 'getCurrentToken(): give correct token if there are tokens with no duration', function ( assert ) {
		var token, $tokens, expectedToken;

		assert.expect( 1 );
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( '#utterance-0' );
		addToken( $tokens, '', 0.0, 1.0 );
		addToken( $tokens, '', 1.0, 1.0 );
		expectedToken = addToken( $tokens, '', 1.0, 2.0 );
		$( '#utterance-0 audio' ).prop( 'currentTime', 1.1 );
		mw.wikispeech.wikispeech.play();

		token = mw.wikispeech.wikispeech.getCurrentToken().get( 0 );

		assert.strictEqual( token, expectedToken );
	} );

	QUnit.test( 'skipAheadToken()', function ( assert ) {
		var $tokens;

		assert.expect( 2 );
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( $( '#utterance-0' ) );
		addToken( $tokens, 'one', 0.0, 1.0 );
		addToken( $tokens, 'two', 1.0, 2.0 );
		mw.wikispeech.wikispeech.play();

		mw.wikispeech.wikispeech.skipAheadToken();

		assert.strictEqual(
			$( '#utterance-0 audio' ).prop( 'currentTime' ),
			1.0
		);
		assert.strictEqual(
			mw.wikispeech.highlighter.removeWrappers.calledWith(
				'.ext-wikispeech-highlight-word'
			),
			true
		);
	} );

	QUnit.test( 'skipAheadToken(): skip ahead utterance when last token', function ( assert ) {
		var $tokens;

		assert.expect( 1 );
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( $( '#utterance-0' ) );
		addToken( $tokens, 'first', 0.0, 1.0 );
		addToken( $tokens, 'last', 1.0, 2.0 );
		mw.wikispeech.wikispeech.play();
		$( '#utterance-0 audio' ).prop( 'currentTime', 1.1 );
		sinon.stub( mw.wikispeech.wikispeech, 'skipAheadUtterance' );

		mw.wikispeech.wikispeech.skipAheadToken();

		assert.strictEqual(
			mw.wikispeech.wikispeech.skipAheadUtterance.called,
			true
		);
	} );

	QUnit.test( 'skipAheadToken(): ignore silent tokens', function ( assert ) {
		var $tokens;

		assert.expect( 1 );
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( '#utterance-0' );
		addToken( $tokens, 'starting word', 0.0, 1.0 );
		addToken( $tokens, 'no duration', 1.0, 1.0 );
		addToken( $tokens, '', 1.0, 2.0 );
		addToken( $tokens, 'goal', 2.0, 3.0 );
		mw.wikispeech.wikispeech.play();

		mw.wikispeech.wikispeech.skipAheadToken();

		assert.strictEqual(
			$( '#utterance-0 audio' ).prop( 'currentTime' ),
			2.0
		);
	} );

	QUnit.test( 'skipBackToken()', function ( assert ) {
		var $tokens;

		assert.expect( 2 );
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( '#utterance-0' );
		addToken( $tokens, 'one', 0.0, 1.0 );
		addToken( $tokens, 'two', 1.0, 2.0 );
		mw.wikispeech.wikispeech.play();
		$( '#utterance-0 audio' ).prop( 'currentTime', 1.1 );

		mw.wikispeech.wikispeech.skipBackToken();

		assert.strictEqual(
			$( '#utterance-0 audio' ).prop( 'currentTime' ),
			0.0
		);
		assert.strictEqual(
			mw.wikispeech.highlighter.removeWrappers.calledWith(
				'.ext-wikispeech-highlight-word'
			),
			true
		);
	} );

	QUnit.test( 'skipBackToken(): skip to last token in previous utterance if first token', function ( assert ) {
		var $tokens, $tokens2;

		assert.expect( 3 );
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-1' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( '#utterance-0' );
		addToken( $tokens, 'one', 0.0, 1.0 );
		addToken( $tokens, 'two', 1.0, 2.0 );
		$tokens2 = $( '<tokens></tokens>' ).appendTo( '#utterance-1' );
		addToken( $tokens2, 'three', 0.0, 1.0 );
		mw.wikispeech.wikispeech.playUtterance( $( '#utterance-1' ) );

		mw.wikispeech.wikispeech.skipBackToken();

		assert.strictEqual(
			$( '#utterance-0 audio' ).prop( 'paused' ),
			false
		);
		assert.strictEqual(
			$( '#utterance-0 audio' ).prop( 'currentTime' ),
			1.0
		);
		assert.strictEqual(
			$( '#utterance-1 audio' ).prop( 'paused' ),
			true
		);
	} );

	QUnit.test( 'skipBackToken(): ignore silent tokens', function ( assert ) {
		var $tokens;

		assert.expect( 1 );
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( '#utterance-0' );
		addToken( $tokens, 'goal', 0.0, 1.0 );
		addToken( $tokens, 'no duration', 1.0, 1.0 );
		addToken( $tokens, '', 1.0, 2.0 );
		addToken( $tokens, 'starting word', 2.0, 3.0 );
		mw.wikispeech.wikispeech.playUtterance( $( '#utterance-0' ) );
		$( '#utterance-0 audio' ).prop( 'currentTime', 2.1 );

		mw.wikispeech.wikispeech.skipBackToken();

		assert.strictEqual(
			$( '#utterance-0 audio' ).prop( 'currentTime' ),
			0.0
		);
	} );
} )( mediaWiki, jQuery );
