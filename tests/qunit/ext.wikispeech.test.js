( function ( mw, $ ) {
	var wikispeech, server;

	QUnit.module( 'ext.wikispeech', {
		setup: function () {
			var $utterances;

			wikispeech = new mw.wikispeech.Wikispeech();
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
				.attr( 'id', 'utterance-0' )
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
		sinon.spy( wikispeech, 'loadAudio' );

		wikispeech.prepareUtterance( $( '#utterance-0' ) );

		assert.strictEqual(
			wikispeech.loadAudio.calledWith( $( '#utterance-0' ) ),
			true
		);
	} );

	// jscs:disable validateQuoteMarks
	QUnit.test( "prepareUtterance(): don't request if waiting for response", function ( assert ) {
		// jscs:enable validateQuoteMarks
		assert.expect( 1 );
		sinon.spy( wikispeech, 'loadAudio' );
		$( '#utterance-0' ).prop( 'waitingForResponse', true );

		wikispeech.prepareUtterance( $( '#utterance-0' ) );

		assert.strictEqual( wikispeech.loadAudio.notCalled, true );
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
		sinon.spy( wikispeech, 'loadAudio' );

		wikispeech.prepareUtterance( $( '#utterance-0' ) );

		assert.strictEqual( wikispeech.loadAudio.notCalled, true );
	} );

	QUnit.test( 'prepareUtterance(): prepare next utterance when playing', function ( assert ) {
		var $nextUtterance = $( '#utterance-1' );
		assert.expect( 1 );
		wikispeech.prepareUtterance( $( '#utterance-0' ) );
		sinon.spy( wikispeech, 'prepareUtterance' );

		$( '#utterance-0 audio' ).trigger( 'play' );

		assert.strictEqual(
			wikispeech.prepareUtterance.calledWith( $nextUtterance ),
			true
		);
	} );

	// jscs:disable validateQuoteMarks
	QUnit.test( "prepareUtterance(): don't prepare next audio if it doesn't exist", function ( assert ) {
		// jscs:enable validateQuoteMarks
		assert.expect( 1 );
		sinon.spy( wikispeech, 'prepareUtterance' );
		wikispeech.prepareUtterance( $( '#utterance-1' ) );

		$( '#utterance-1 audio' ).trigger( 'play' );

		assert.strictEqual( wikispeech.prepareUtterance.calledWith(
			$( '#utterance-2' ) ), false );
	} );

	QUnit.test( 'prepareUtterance(): play next utterance when ended', function ( assert ) {
		var $nextAudio;

		assert.expect( 1 );
		// Assume that both utterances are prepared.
		wikispeech.prepareUtterance( $( '#utterance-0' ) );
		wikispeech.prepareUtterance( $( '#utterance-1' ) );
		$nextAudio = $( '#utterance-1' ).children( 'audio' ).get( 0 );
		sinon.spy( $nextAudio, 'play' );
		wikispeech.playUtterance( $( '#utterance-0' ) );

		$( '#utterance-0 audio' ).trigger( 'ended' );

		assert.strictEqual( $nextAudio.play.called, true );
	} );

	QUnit.test( 'prepareUtterance(): stop when end of text is reached', function ( assert ) {
		var $lastUtterance = $( '#utterance-1' );
		assert.expect( 1 );
		sinon.spy( wikispeech, 'stop' );
		wikispeech.prepareUtterance( $lastUtterance );
		wikispeech.playUtterance( $lastUtterance );

		$lastUtterance.children( 'audio' ).trigger( 'ended' );

		assert.strictEqual( wikispeech.stop.called, true );
	} );

	QUnit.test( 'loadAudio()', function ( assert ) {
		assert.expect( 2 );
		$( '<content></content>' )
			.append( $( '<text></text>' ).text( 'An utterance.' ) )
			.appendTo( '#utterance-0' );
		sinon.spy( wikispeech, 'requestTts' );

		wikispeech.loadAudio( $( '#utterance-0' ) );

		assert.strictEqual( wikispeech.requestTts.called, true );
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
		sinon.spy( wikispeech, 'addTokenElements' );
		wikispeech.loadAudio( $( '#utterance-0' ) );

		server.respond();

		assert.strictEqual(
			$( '#utterance-0 audio' ).attr( 'src' ),
			'http://server.url/audio'
		);
		assert.strictEqual(
			wikispeech.addTokenElements.calledWith(
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
		sinon.spy( wikispeech, 'addTokenElements' );
		wikispeech.loadAudio( $( '#utterance-0' ) );

		server.respond();

		assert.strictEqual( wikispeech.addTokenElements.notCalled, true );
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

		wikispeech.addControlPanel();

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
		assert.expect( 1 );
		mw.config.set(
			'wgWikispeechHelpPage',
			'Help'
		);
		wikispeech.addControlPanel();

		assert.strictEqual(
			$( '#ext-wikispeech-control-panel .ext-wikispeech-help' ).length,
			1
		);
	} );

	QUnit.test( 'addControlPanel(): do not add help button if page is not set', function ( assert ) {
		assert.expect( 1 );
		mw.config.set(
			'wgWikispeechHelpPage',
			null
		);
		wikispeech.addControlPanel();

		assert.strictEqual(
			$( '#ext-wikispeech-control-panel #ext-wikispeech-help' ).length,
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
		sinon.stub( wikispeech, functionName );
		wikispeech.addControlPanel();

		$( buttonSelector ).click();

		assert.strictEqual( wikispeech[ functionName ].called, true );
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
		sinon.spy( wikispeech, 'play' );

		wikispeech.playOrStop();

		assert.strictEqual( wikispeech.play.called, true );
	} );

	QUnit.test( 'playOrStop(): stop', function ( assert ) {
		assert.expect( 1 );
		wikispeech.play();
		sinon.spy( wikispeech, 'stop' );

		wikispeech.playOrStop();

		assert.strictEqual( wikispeech.stop.called, true );
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
		wikispeech.addKeyboardShortcuts();
		sinon.spy( wikispeech, functionName );

		$( document ).trigger( createKeydownEvent( keyCode, modifiers ) );

		assert.strictEqual( wikispeech[ functionName ].called, true );
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
		wikispeech.addControlPanel();
		wikispeech.play();
		wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$( '#utterance-0 audio' ).prop( 'currentTime', 1.0 );

		wikispeech.stop();

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
		wikispeech.addControlPanel();
		wikispeech.prepareUtterance( $firstUtterance );

		wikispeech.play();

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
		wikispeech.prepareUtterance( $( '#utterance-0' ) );
		wikispeech.prepareUtterance( $( '#utterance-1' ) );
		wikispeech.play();

		wikispeech.skipAheadUtterance();

		assert.strictEqual( $( '#utterance-0 audio' ).prop( 'paused' ), true );
		assert.strictEqual(
			$( '#utterance-1 audio' ).prop( 'paused' ),
			false
		);
	} );

	QUnit.test( 'skipAheadUtterance(): stop if no next utterance', function ( assert ) {
		assert.expect( 1 );
		sinon.spy( wikispeech, 'stop' );
		wikispeech.playUtterance( $( '#utterance-1' ) );

		wikispeech.skipAheadUtterance();

		assert.strictEqual( wikispeech.stop.called, true );
	} );

	QUnit.test( 'skipBackUtterance()', function ( assert ) {
		assert.expect( 2 );
		wikispeech.prepareUtterance( $( '#utterance-0' ) );
		wikispeech.prepareUtterance( $( '#utterance-1' ) );
		wikispeech.playUtterance( $( '#utterance-1' ) );

		wikispeech.skipBackUtterance();

		assert.strictEqual( $( '#utterance-1 audio' ).prop( 'paused' ), true );
		assert.strictEqual(
			$( '#utterance-0 audio' ).prop( 'paused' ),
			false
		);
	} );

	QUnit.test( 'skipBackUtterance(): restart if first utterance', function ( assert ) {
		assert.expect( 2 );
		wikispeech.prepareUtterance( $( '#utterance-0' ) );
		wikispeech.playUtterance( $( '#utterance-0' ) );
		$( '#utterance-0 audio' ).prop( 'currentTime', 1.0 );

		wikispeech.skipBackUtterance();

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
		wikispeech.prepareUtterance( $( '#utterance-0' ) );
		wikispeech.prepareUtterance( $( '#utterance-1' ) );
		wikispeech.playUtterance( $( '#utterance-1' ) );
		$( '#utterance-1 audio' ).prop( 'currentTime', 3.1 );

		wikispeech.skipBackUtterance();

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

		$nextUtterance = wikispeech.getNextUtterance( $( '#utterance-0' ) );

		assert.strictEqual(
			$nextUtterance.get( 0 ),
			$( '#utterance-1' ).get( 0 )
		);
	} );

	QUnit.test( 'getNextUtterance(): return the empty object if no current utterance', function ( assert ) {
		var $nextUtterance;

		assert.expect( 1 );

		$nextUtterance = wikispeech.getNextUtterance( $() );

		assert.strictEqual( $nextUtterance.length, 0 );
	} );

	QUnit.test( 'addTokenElements()', function ( assert ) {
		var tokens, $tokensElement, $expectedTokensElement;

		assert.expect( 1 );
		$( '<content></content>' )
			.append( $( '<text></text>' ).text( 'An utterance.' ) )
			.appendTo( '#utterance-0' );
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

		wikispeech.addTokenElements( $( '#utterance-0' ), tokens );

		$tokensElement = $( '#utterance-0' ).children( 'tokens' );
		$expectedTokensElement = $( '<tokens></tokens>' );
		addToken( $expectedTokensElement, 'An', 0.0, 1.0 );
		addToken( $expectedTokensElement, 'utterance', 1.0, 2.0 );
		addToken( $expectedTokensElement, '.', 2.0, 3.0 );
		assert.strictEqual(
			$tokensElement.prop( 'outerHTML' ),
			$expectedTokensElement.prop( 'outerHTML' )
		);
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

	function addToken( $parent, string, startTime, endTime ) {
		var $token = $( '<token></token>' )
			.text( string )
			.attr( {
				'start-time': startTime,
				'end-time': endTime
			} );
		$parent.append( $token );
		return $token.get( 0 );
	}

	QUnit.test( 'addTokenElements(): handle tag', function ( assert ) {
		var tokens, $tokensElement, $expectedTokensElement;

		assert.expect( 1 );
		$( '<content></content>' ).html(
			'<text>Utterance with </text><text>tag</text><text>.</text>'
		)
			.appendTo( $( '#utterance-0' ) );
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

		wikispeech.addTokenElements( $( '#utterance-0' ), tokens );

		$tokensElement = $( '#utterance-0' ).children( 'tokens' );
		$expectedTokensElement = $( '<tokens></tokens>' );
		addToken( $expectedTokensElement, 'Utterance', 0.0, 1.0 );
		addToken( $expectedTokensElement, 'with', 1.0, 2.0 );
		addToken( $expectedTokensElement, 'tag', 2.0, 3.0 );
		addToken( $expectedTokensElement, '.', 3.0, 4.0 );
		assert.strictEqual(
			$tokensElement.prop( 'outerHTML' ),
			$expectedTokensElement.prop( 'outerHTML' )
		);
	} );

	QUnit.test( 'addTokenElements(): handle removed element', function ( assert ) {
		var tokens, $tokensElement, $expectedTokensElement;

		assert.expect( 1 );
		$( '<content></content>' ).html(
			'<text>Utterance with </text><text>removed tag</text><text>.</text>'
		)
			.appendTo( $( '#utterance-0' ) );
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

		wikispeech.addTokenElements( $( '#utterance-0' ), tokens );

		$tokensElement = $( '#utterance-0' ).children( 'tokens' );
		$expectedTokensElement = $( '<tokens></tokens>' );
		addToken( $expectedTokensElement, 'Utterance', 0.0, 1.0 );
		addToken( $expectedTokensElement, 'with', 1.0, 2.0 );
		addToken( $expectedTokensElement, '.', 2.0, 3.0 );
		assert.strictEqual(
			$tokensElement.prop( 'outerHTML' ),
			$expectedTokensElement.prop( 'outerHTML' )
		);
	} );

	QUnit.test( 'getCurrentToken()', function ( assert ) {
		var $tokens, token, expectedToken;

		assert.expect( 1 );
		wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( $( '#utterance-0' ) );
		addToken( $tokens, '', 0.0, 1.0 );
		expectedToken = addToken( $tokens, '', 1.0, 2.0 );
		addToken( $tokens, '', 2.0, 3.0 );
		$( '#utterance-0 audio' ).prop( 'currentTime', 1.1 );
		wikispeech.play();

		token = wikispeech.getCurrentToken().get( 0 );

		assert.strictEqual( token, expectedToken );
	} );

	QUnit.test( 'getCurrentToken(): get first token', function ( assert ) {
		var $tokens, token, expectedToken;

		assert.expect( 1 );
		wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( $( '#utterance-0' ) );
		expectedToken = addToken( $tokens, '', 0.0, 1.0 );
		addToken( $tokens, '', 1.0, 2.0 );
		$( '#utterance-0 audio' ).prop( 'currentTime', 0.1 );
		wikispeech.play();

		token = wikispeech.getCurrentToken().get( 0 );

		assert.strictEqual( token, expectedToken );
	} );

	QUnit.test( 'getCurrentToken(): get the last token', function ( assert ) {
		var token, $tokens, expectedToken;

		assert.expect( 1 );
		wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( $( '#utterance-0' ) );
		addToken( $tokens, '', 0.0, 1.0 );
		addToken( $tokens, '', 1.0, 2.0 );
		expectedToken = addToken( $tokens, '', 2.0, 3.0 );
		$( '#utterance-0 audio' ).prop( 'currentTime', 2.1 );
		wikispeech.play();

		token = wikispeech.getCurrentToken().get( 0 );

		assert.strictEqual( token, expectedToken );
	} );

	QUnit.test( 'getCurrentToken(): get the last token when current time is equal to last tokens end time', function ( assert ) {
		var token, $tokens, expectedToken;

		assert.expect( 1 );
		wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( $( '#utterance-0' ) );
		addToken( $tokens, '', 0.0, 1.0 );
		expectedToken = addToken( $tokens, '', 1.0, 2.0 );
		$( '#utterance-0 audio' ).prop( 'currentTime', 2.0 );
		wikispeech.play();

		token = wikispeech.getCurrentToken().get( 0 );

		assert.strictEqual( token, expectedToken );
	} );

	QUnit.test( 'getCurrentToken(): ignore tokens with no duration', function ( assert ) {
		var token, $tokens, expectedToken;

		assert.expect( 1 );
		wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( $( '#utterance-0' ) );
		addToken( $tokens, '', 0.0, 1.0 );
		addToken( $tokens, '', 1.0, 1.0 );
		expectedToken = addToken( $tokens, '', 1.0, 2.0 );
		$( '#utterance-0 audio' ).prop( 'currentTime', 1.1 );
		wikispeech.play();

		token = wikispeech.getCurrentToken().get( 0 );

		assert.strictEqual( token, expectedToken );
	} );

	QUnit.test( 'getCurrentToken(): give correct token if there are tokens with no duration', function ( assert ) {
		var token, $tokens, expectedToken;

		assert.expect( 1 );
		wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( $( '#utterance-0' ) );
		addToken( $tokens, '', 0.0, 1.0 );
		addToken( $tokens, '', 1.0, 1.0 );
		expectedToken = addToken( $tokens, '', 1.0, 2.0 );
		$( '#utterance-0 audio' ).prop( 'currentTime', 1.1 );
		wikispeech.play();

		token = wikispeech.getCurrentToken().get( 0 );

		assert.strictEqual( token, expectedToken );
	} );

	QUnit.test( 'skipAheadToken()', function ( assert ) {
		var $tokens;

		assert.expect( 1 );
		wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( $( '#utterance-0' ) );
		addToken( $tokens, 'one', 0.0, 1.0 );
		addToken( $tokens, 'two', 1.0, 2.0 );
		wikispeech.play();

		wikispeech.skipAheadToken();

		assert.strictEqual(
			$( '#utterance-0 audio' ).prop( 'currentTime' ),
			1.0
		);
	} );

	QUnit.test( 'skipAheadToken(): skip ahead utterance when last token', function ( assert ) {
		var $tokens;

		assert.expect( 1 );
		wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( $( '#utterance-0' ) );
		addToken( $tokens, 'first', 0.0, 1.0 );
		addToken( $tokens, 'last', 1.0, 2.0 );
		wikispeech.play();
		$( '#utterance-0 audio' ).prop( 'currentTime', 1.1 );
		sinon.spy( wikispeech, 'skipAheadUtterance' );

		wikispeech.skipAheadToken();

		assert.strictEqual( wikispeech.skipAheadUtterance.called, true );
	} );

	QUnit.test( 'skipAheadToken(): ignore silent tokens', function ( assert ) {
		var $tokens;

		assert.expect( 1 );
		wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( $( '#utterance-0' ) );
		addToken( $tokens, 'starting word', 0.0, 1.0 );
		addToken( $tokens, 'no duration', 1.0, 1.0 );
		addToken( $tokens, '', 1.0, 2.0 );
		addToken( $tokens, 'goal', 2.0, 3.0 );
		wikispeech.play();

		wikispeech.skipAheadToken();

		assert.strictEqual(
			$( '#utterance-0 audio' ).prop( 'currentTime' ),
			2.0
		);
	} );

	QUnit.test( 'skipBackToken()', function ( assert ) {
		var $tokens;

		assert.expect( 1 );
		wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( $( '#utterance-0' ) );
		addToken( $tokens, 'one', 0.0, 1.0 );
		addToken( $tokens, 'two', 1.0, 2.0 );
		wikispeech.play();
		$( '#utterance-0 audio' ).prop( 'currentTime', 1.1 );

		wikispeech.skipBackToken();

		assert.strictEqual(
			$( '#utterance-0 audio' ).prop( 'currentTime' ),
			0.0
		);
	} );

	QUnit.test( 'skipBackToken(): skip to last token in previous utterance if first token', function ( assert ) {
		var $tokens, $tokens2;

		assert.expect( 3 );
		wikispeech.prepareUtterance( $( '#utterance-0' ) );
		wikispeech.prepareUtterance( $( '#utterance-1' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( $( '#utterance-0' ) );
		addToken( $tokens, 'one', 0.0, 1.0 );
		addToken( $tokens, 'two', 1.0, 2.0 );
		$tokens2 = $( '<tokens></tokens>' ).appendTo( $( '#utterance-1' ) );
		addToken( $tokens2, 'three', 0.0, 1.0 );
		wikispeech.playUtterance( $( '#utterance-1' ) );

		wikispeech.skipBackToken();

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
		wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$tokens = $( '<tokens></tokens>' ).appendTo( $( '#utterance-0' ) );
		addToken( $tokens, 'starting word', 0.0, 1.0 );
		addToken( $tokens, 'no duration', 1.0, 1.0 );
		addToken( $tokens, '', 1.0, 2.0 );
		addToken( $tokens, 'goal', 2.0, 3.0 );
		wikispeech.playUtterance( $( '#utterance-0' ) );
		$( '#utterance-0 audio' ).prop( 'currentTime', 2.1 );

		wikispeech.skipBackToken();

		assert.strictEqual(
			$( '#utterance-0 audio' ).prop( 'currentTime' ),
			0.0
		);
	} );

	QUnit.test( 'highlightUtterance()', function ( assert ) {
		assert.expect( 1 );
		$( '#qunit-fixture' ).append(
			$( '<div></div>' )
				.attr( 'id', 'mw-content-text' )
				.text( 'An utterance.' )
		);
		$( '#utterance-0' ).append(
			$( '<content></content>' )
				.append( $( '<text></text>' )
					.text( 'An utterance.' )
					.attr( 'path', './text()' )
				)
		);
		$( '#utterance-0' ).attr( 'start-offset', 0 );
		$( '#utterance-0' ).attr( 'end-offset', 12 );

		wikispeech.highlightUtterance( $( '#utterance-0' ) );

		assert.strictEqual(
			$( '#mw-content-text' ).prop( 'innerHTML' ),
			'<span class="ext-wikispeech-highlight-sentence">An utterance.</span>'
		);
	} );

	QUnit.test( 'highlightUtterance(): multiple utterances', function ( assert ) {
		assert.expect( 1 );
		$( '#qunit-fixture' ).append(
			$( '<div></div>' )
				.attr( 'id', 'mw-content-text' )
				.text( 'Utterance one. Utterance two. Utterance three.' )
		);
		$( '#utterance-0' ).append(
			$( '<content></content>' )
				.append( $( '<text></text>' )
					.text( 'Utterance two.' )
					.attr( 'path', './text()' )
				)
		);
		$( '#utterance-0' ).attr( 'start-offset', 15 );
		$( '#utterance-0' ).attr( 'end-offset', 28 );

		wikispeech.highlightUtterance( $( '#utterance-0' ) );

		assert.strictEqual(
			$( '#mw-content-text' ).prop( 'innerHTML' ),
			'Utterance one. <span class="ext-wikispeech-highlight-sentence">Utterance two.</span> Utterance three.'
		);
	} );

	QUnit.test( 'highlightUtterance(): with tags', function ( assert ) {
		assert.expect( 1 );
		$( '#qunit-fixture' ).append(
			$( '<div></div>' )
				.attr( 'id', 'mw-content-text' )
				.html( '<p>Utterance with <b>a</b> tag.</p>' )
		);
		$( '#utterance-0' ).append(
			$( '<content></content>' )
				.append(
					$( '<text></text>' )
						.text( 'Utterance with' )
						.attr( 'path', './p/text()[1]' )
				)
				.append(
					$( '<text></text>' )
						.text( 'a' )
						.attr( 'path', './p/b/text()' )
				)
				.append(
					$( '<text></text>' )
						.text( 'tag.' )
						.attr( 'path', './p/text()[2]' )
				)
		);
		$( '#utterance-0' ).attr( 'start-offset', 0 );
		$( '#utterance-0' ).attr( 'end-offset', 4 );

		wikispeech.highlightUtterance( $( '#utterance-0' ) );

		assert.strictEqual(
			$( '#mw-content-text' ).prop( 'innerHTML' ),
			'<p><span class="ext-wikispeech-highlight-sentence">Utterance with </span><b><span class="ext-wikispeech-highlight-sentence">a</span></b><span class="ext-wikispeech-highlight-sentence"> tag.</span></p>'
		);
	} );

	QUnit.test( 'highlightUtterance(): wrap middle text nodes properly', function ( assert ) {
		assert.expect( 1 );
		$( '#qunit-fixture' ).append(
			$( '<div></div>' )
				.attr( 'id', 'mw-content-text' )
				.html( 'First<br />middle<br />last. Next utterance.' )
		);
		$( '#utterance-0' ).append(
			$( '<content></content>' )
				.append(
					$( '<text></text>' )
						.text( 'First' )
						.attr( 'path', './text()[1]' )
				)
				.append(
					$( '<text></text>' )
						.text( 'middle' )
						.attr( 'path', './text()[2]' )
				)
				.append(
					$( '<text></text>' )
						.text( 'last.' )
						.attr( 'path', './text()[3]' )
				)
		);
		$( '#utterance-0' ).attr( 'start-offset', 0 );
		$( '#utterance-0' ).attr( 'end-offset', 4 );

		wikispeech.highlightUtterance( $( '#utterance-0' ) );

		assert.strictEqual(
			$( '#mw-content-text' ).prop( 'innerHTML' ),
			'<span class="ext-wikispeech-highlight-sentence">First</span><br><span class="ext-wikispeech-highlight-sentence">middle</span><br><span class="ext-wikispeech-highlight-sentence">last.</span> Next utterance.'
		);
	} );

	QUnit.test( 'unhighlightUtterance()', function ( assert ) {
		assert.expect( 2 );
		$( '#qunit-fixture' ).append(
			$( '<div></div>' )
				.attr( 'id', 'mw-content-text' )
				.html( '<span class="ext-wikispeech-highlight-sentence">An utterance.</span>' )
		);

		wikispeech.unhighlightUtterances();

		assert.strictEqual(
			$( '#mw-content-text' ).prop( 'innerHTML' ),
			'An utterance.'
		);
		assert.strictEqual(
			$( '.ext-wikispeech-highlight-sentence' ).contents().length,
			0
		);
	} );

	QUnit.test( 'unhighlightUtterance(): restore text nodes as one', function ( assert ) {
		assert.expect( 3 );
		$( '#qunit-fixture' ).append(
			$( '<div></div>' )
				.attr( 'id', 'mw-content-text' )
				.html( 'prefix <span class="ext-wikispeech-highlight-sentence">An utterance.</span> suffix' )
		);

		wikispeech.unhighlightUtterances();

		assert.strictEqual(
			$( '#mw-content-text' ).prop( 'innerHTML' ),
			'prefix An utterance. suffix'
		);
		assert.strictEqual(
			$( '.ext-wikispeech-highlight-sentence' ).contents().length,
			0
		);
		assert.strictEqual( $( '#mw-content-text' ).contents().length, 1 );
	} );

	QUnit.test( 'unhighlightUtterance(): multiple highlights', function ( assert ) {
		assert.expect( 3 );
		$( '#qunit-fixture' ).append(
			$( '<div></div>' )
				.attr( 'id', 'mw-content-text' )
				.html( '<span class="ext-wikispeech-highlight-sentence">An </span><span class="ext-wikispeech-highlight-sentence">utterance.</span>' )
		);
		$( '.ext-wikispeech-highlight-sentence' )
			.prop( 'utterance', $( '#utterance-0' ).get( 0 ) );

		wikispeech.unhighlightUtterances();

		assert.strictEqual(
			$( '#mw-content-text' ).prop( 'innerHTML' ),
			'An utterance.'
		);
		assert.strictEqual( $( '#mw-content-text' ).contents().length, 1 );
		assert.strictEqual(
			$( '.ext-wikispeech-highlight-sentence' ).contents().length,
			0
		);
	} );
} )( mediaWiki, jQuery );
