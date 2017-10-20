( function ( mw, $ ) {
	var server, utterances;

	QUnit.module( 'ext.wikispeech', {
		setup: function () {
			mw.wikispeech.wikispeech = new mw.wikispeech.Wikispeech();
			// Mock other modules for methods that are called as side
			// effects.
			mw.wikispeech.highlighter = {
				removeWrappers: function () {},
				highlightUtterance: function () {},
				clearHighlightTokenTimer: function () {},
				startTokenHighlighting: sinon.spy()
			};
			mw.wikispeech.selectionPlayer = {
				playSelectionIfValid: function () {},
				playSelection: sinon.spy(),
				resetPreviousEndUtterance: function () {}
			};
			server = sinon.fakeServer.create();
			// overrideMimeType() isn't defined by default.
			server.xhr.prototype.overrideMimeType = function () {};
			$( '#qunit-fixture' ).append(
				$( '<div></div>' ).attr( 'id', 'content' )
			);
			utterances = [
				{
					audio: $( '<audio></audio>' ).get( 0 ),
					startOffset: 0,
					endOffset: 14,
					content: [ { string: 'Utterance zero.' } ]
				},
				{
					audio: $( '<audio></audio>' ).get( 0 ),
					content: [ { string: 'Utterance one.' } ]
				}
			];
			mw.wikispeech.wikispeech.utterances = utterances;
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

	QUnit.test( 'enabledForNamespace()', function ( assert ) {
		assert.expect( 1 );

		mw.config.set( 'wgWikispeechNamespaces', [ 1, 2 ] );
		mw.config.set( 'wgNamespaceNumber', 1 );

		assert.strictEqual(
			mw.wikispeech.wikispeech.enabledForNamespace(),
			true
		);
	} );

	QUnit.test( 'enabledForNamespace(): false if invalid namespace', function ( assert ) {
		assert.expect( 1 );

		mw.config.set( 'wgWikispeechNamespaces', [ 1 ] );
		mw.config.set( 'wgNamespaceNumber', 2 );

		assert.strictEqual(
			mw.wikispeech.wikispeech.enabledForNamespace(),
			false
		);
	} );

	QUnit.test( 'loadUtterances()', function ( assert ) {
		assert.expect( 1 );

		mw.config.set( 'wgPageName', 'Page' );

		mw.wikispeech.wikispeech.loadUtterances();

		assert.strictEqual(
			decodeURIComponent( server.requests[ 0 ].requestBody ),
			'action=wikispeech&format=json&page=Page&output=segments'
		);
	} );

	QUnit.test( 'prepareUtterance()', function ( assert ) {
		assert.expect( 1 );
		sinon.spy( mw.wikispeech.wikispeech, 'loadAudio' );

		mw.wikispeech.wikispeech.prepareUtterance( utterances[ 0 ] );

		assert.strictEqual(
			mw.wikispeech.wikispeech.loadAudio.calledWith( utterances[ 0 ] ),
			true
		);
	} );

	QUnit.test( 'prepareUtterance(): do not request if waiting for response', function ( assert ) {
		assert.expect( 1 );
		sinon.spy( mw.wikispeech.wikispeech, 'loadAudio' );
		utterances[ 0 ].request = { done: function () {} };

		mw.wikispeech.wikispeech.prepareUtterance( utterances[ 0 ] );

		assert.strictEqual(
			mw.wikispeech.wikispeech.loadAudio.notCalled,
			true
		);
	} );

	QUnit.test( 'prepareUtterance(): do not load audio if already loaded', function ( assert ) {
		assert.expect( 1 );
		utterances[ 0 ].audio = $( '<audio></audio>' )
			.attr( 'src', 'http://server.url/audio' );
		sinon.spy( mw.wikispeech.wikispeech, 'loadAudio' );

		mw.wikispeech.wikispeech.prepareUtterance( utterances[ 0 ] );

		assert.strictEqual(
			mw.wikispeech.wikispeech.loadAudio.notCalled,
			true
		);
	} );

	QUnit.test( 'prepareUtterance(): prepare next utterance when playing', function ( assert ) {
		assert.expect( 1 );
		mw.wikispeech.wikispeech.prepareUtterance( utterances[ 0 ] );
		sinon.spy( mw.wikispeech.wikispeech, 'prepareUtterance' );

		$( utterances[ 0 ].audio ).trigger( 'play' );

		assert.strictEqual(
			mw.wikispeech.wikispeech.prepareUtterance.calledWith(
				utterances[ 1 ]
			),
			true
		);
	} );

	QUnit.test( 'prepareUtterance(): do not prepare next audio if it does not exist', function ( assert ) {
		assert.expect( 1 );
		sinon.spy( mw.wikispeech.wikispeech, 'prepareUtterance' );
		mw.wikispeech.wikispeech.prepareUtterance( utterances[ 1 ] );

		$( utterances[ 1 ].audio ).trigger( 'play' );

		assert.strictEqual(
			mw.wikispeech.wikispeech.prepareUtterance.calledOnce,
			true
		);
	} );

	QUnit.test( 'prepareUtterance(): play next utterance when ended', function ( assert ) {
		var nextAudio;

		assert.expect( 1 );
		// Assume that both utterances are prepared.
		mw.wikispeech.wikispeech.prepareUtterance( utterances[ 0 ] );
		mw.wikispeech.wikispeech.prepareUtterance( utterances[ 1 ] );
		nextAudio = utterances[ 1 ].audio;
		sinon.spy( nextAudio, 'play' );
		mw.wikispeech.wikispeech.playUtterance( utterances[ 0 ] );

		$( utterances[ 0 ].audio ).trigger( 'ended' );

		assert.strictEqual( nextAudio.play.called, true );
	} );

	QUnit.test( 'prepareUtterance(): stop when end of text is reached', function ( assert ) {
		var lastUtterance = utterances[ 1 ];
		assert.expect( 1 );
		sinon.spy( mw.wikispeech.wikispeech, 'stop' );
		mw.wikispeech.wikispeech.prepareUtterance( lastUtterance );
		mw.wikispeech.wikispeech.playUtterance( lastUtterance );

		$( lastUtterance.audio ).trigger( 'ended' );

		assert.strictEqual( mw.wikispeech.wikispeech.stop.called, true );
	} );

	QUnit.test( 'loadAudio()', function ( assert ) {
		assert.expect( 2 );
		sinon.spy( mw.wikispeech.wikispeech, 'requestTts' );

		mw.wikispeech.wikispeech.loadAudio( utterances[ 0 ] );

		assert.strictEqual( mw.wikispeech.wikispeech.requestTts.called, true );
		assert.strictEqual(
			server.requests[ 0 ].requestBody,
			'lang=en&input_type=text&input=Utterance+zero.'
		);
	} );

	QUnit.test( 'loadAudio(): request successful', function ( assert ) {
		assert.expect( 3 );
		utterances[ 0 ].audio = $( '<audio></audio>' ).get( 0 );
		utterances[ 0 ].request = { done: function () {} };
		server.respondWith(
			'{"audio": "http://server.url/audio", "tokens": [{"orth": "Utterance"}, {"orth": "zero"}, {"orth": "."}]}'
		);
		sinon.stub( mw.wikispeech.wikispeech, 'addTokens' );
		mw.wikispeech.wikispeech.loadAudio( utterances[ 0 ] );

		server.respond();

		assert.strictEqual(
			utterances[ 0 ].audio.src,
			'http://server.url/audio'
		);
		assert.strictEqual(
			mw.wikispeech.wikispeech.addTokens.calledWith(
				utterances[ 0 ],
				[ { orth: 'Utterance' }, { orth: 'zero' }, { orth: '.' } ]
			),
			true
		);
		assert.strictEqual( utterances[ 0 ].request, null );
	} );

	QUnit.test( 'loadAudio(): request failed', function ( assert ) {
		assert.expect( 3 );
		utterances[ 0 ].audio = $( '<audio></audio>' ).get( 0 );
		utterances[ 0 ].request = { done: function () {} };
		server.respondWith( [ 404, {}, '' ] );
		sinon.spy( mw.wikispeech.wikispeech, 'addTokens' );
		mw.wikispeech.wikispeech.loadAudio( utterances[ 0 ] );

		server.respond();

		assert.strictEqual(
			mw.wikispeech.wikispeech.addTokens.notCalled,
			true
		);
		assert.strictEqual( utterances[ 0 ].request, null );
		assert.strictEqual( utterances[ 0 ].audio.src, '' );
	} );

	QUnit.test( 'addControlPanel()', function ( assert ) {
		assert.expect( 5 );
		sinon.stub( mw.wikispeech.wikispeech, 'addStackToPlayStopButton' );

		mw.wikispeech.wikispeech.addControlPanel();

		assert.strictEqual(
			$( '#ext-wikispeech-control-panel .ext-wikispeech-play-stop-button' ).length,
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
		mw.config.set( 'wgWikispeechHelpPage', null );

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

	/**
	 * Test that clicking a button calls the correct function.
	 *
	 * @param {QUnit.assert} assert
	 * @param {string} functionName Name of the function that should
	 *  be called.
	 * @param {string} buttonSelector Id of the button that is clicked.
	 */
	function testClickButton( assert, functionName, buttonSelector ) {
		assert.expect( 1 );
		sinon.stub( mw.wikispeech.wikispeech, functionName );
		mw.wikispeech.wikispeech.addControlPanel();

		$( buttonSelector ).click();

		assert.strictEqual(
			mw.wikispeech.wikispeech[ functionName ].called,
			true
		);
	}

	QUnit.test( 'Clicking play/stop button', function ( assert ) {
		testClickButton(
			assert,
			'playOrStop',
			'.ext-wikispeech-play-stop-button'
		);
	} );

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
		utterances[ 0 ].audio = $( '<audio></audio>' ).get( 0 );
		sinon.spy( mw.wikispeech.wikispeech, 'play' );

		mw.wikispeech.wikispeech.playOrStop();

		assert.strictEqual( mw.wikispeech.wikispeech.play.called, true );
	} );

	QUnit.test( 'playOrStop(): stop', function ( assert ) {
		assert.expect( 1 );
		utterances[ 0 ].audio = $( '<audio></audio>' ).get( 0 );
		mw.wikispeech.wikispeech.play();
		sinon.spy( mw.wikispeech.wikispeech, 'stop' );

		mw.wikispeech.wikispeech.playOrStop();

		assert.strictEqual( mw.wikispeech.wikispeech.stop.called, true );
	} );

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
		utterances[ 0 ].audio = $( '<audio></audio>' ).get( 0 );
		utterances[ 0 ].tokens = [];
		utterances[ 1 ].audio = $( '<audio></audio>' ).get( 0 );

		sinon.stub( mw.wikispeech.wikispeech, functionName );
		mw.wikispeech.wikispeech.addKeyboardShortcuts();

		$( document ).trigger( createKeydownEvent( keyCode, modifiers ) );

		assert.strictEqual( mw.wikispeech.wikispeech[ functionName ].called, true );
	}

	QUnit.test( 'Pressing keyboard shortcut for play/stop', function ( assert ) {
		testKeyboardShortcut( assert, 'playOrStop', 32, 'c' );
	} );

	// TODO: T174799
	// QUnit.test( 'Pressing keyboard shortcut for skipping ahead sentence', function ( assert ) {
	// 	testKeyboardShortcut( assert, 'skipAheadUtterance', 39, 'c' );
	// } );

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
		mw.wikispeech.wikispeech.addStackToPlayStopButton();
		mw.wikispeech.wikispeech.prepareUtterance( utterances[ 0 ] );
		mw.wikispeech.wikispeech.play();
		utterances[ 0 ].audio.currentTime = 1.0;

		mw.wikispeech.wikispeech.stop();

		assert.strictEqual( utterances[ 0 ].audio.paused, true );
		assert.strictEqual(
			utterances[ 0 ].audio.currentTime,
			0.0
		);
		assert.strictEqual(
			$( '.ext-wikispeech-play-stop' )
				.hasClass( 'ext-wikispeech-play' ),
			true
		);
		assert.strictEqual(
			$( '.ext-wikispeech-play-stop' )
				.hasClass( 'ext-wikispeech-stop' ),
			false
		);
	} );

	function setUpBufferIcon( ready ) {
		sinon.stub( mw.wikispeech.wikispeech, 'audioIsReady', function () { return ready; } );
		mw.wikispeech.wikispeech.addControlPanel();
		mw.wikispeech.wikispeech.addStackToPlayStopButton();
		mw.wikispeech.wikispeech.prepareUtterance( utterances[ 0 ] );
		mw.wikispeech.wikispeech.playUtterance( utterances[ 0 ] );
	}

	QUnit.test( 'stop(): stopping hides buffering icon and turns listeners off', function ( assert ) {
		assert.expect( 3 );
		setUpBufferIcon( false );
		// stop hides the buffering icon
		assert.deepEqual(
			$( '.ext-wikispeech-buffering-icon' ).css( 'visibility' ),
			'visible'
		);
		mw.wikispeech.wikispeech.stop();
		assert.deepEqual(
			$( '.ext-wikispeech-buffering-icon' ).css( 'visibility' ),
			'hidden'
		);
		// stop turns listeners off
		$( '.ext-wikispeech-buffering-icon' ).css( 'visibility', 'visible' );
		$( utterances[ 0 ].audio ).trigger( 'canplay' );
		assert.strictEqual(
			$( '.ext-wikispeech-buffering-icon' ).css( 'visibility' ),
			'visible'
		);
	} );

	QUnit.test( 'play()', function ( assert ) {
		var firstUtterance = utterances[ 0 ];
		assert.expect( 3 );
		mw.wikispeech.wikispeech.addControlPanel();
		mw.wikispeech.wikispeech.addStackToPlayStopButton();
		mw.wikispeech.wikispeech.prepareUtterance( firstUtterance );

		mw.wikispeech.wikispeech.play();

		assert.strictEqual( firstUtterance.audio.paused, false );
		assert.strictEqual(
			$( '.ext-wikispeech-play-stop' )
				.hasClass( 'ext-wikispeech-stop' ),
			true
		);
		assert.strictEqual(
			$( '.ext-wikispeech-play-stop' )
				.hasClass( 'ext-wikispeech-play' ),
			false
		);
	} );

	QUnit.test( 'play(): play selection when valid', function ( assert ) {
		assert.expect( 1 );
		sinon.spy( mw.wikispeech.wikispeech, 'playUtterance' );
		sinon.stub( mw.wikispeech.selectionPlayer, 'playSelectionIfValid' )
			.returns( true );

		mw.wikispeech.wikispeech.play();

		sinon.assert.notCalled( mw.wikispeech.wikispeech.playUtterance );
	} );

	QUnit.test( 'play(): play from beginning when selection is invalid', function ( assert ) {
		assert.expect( 1 );
		sinon.spy( mw.wikispeech.wikispeech, 'playUtterance' );
		sinon.stub( mw.wikispeech.selectionPlayer, 'playSelectionIfValid' )
			.returns( false );

		mw.wikispeech.wikispeech.play();

		sinon.assert.calledWith(
			mw.wikispeech.wikispeech.playUtterance,
			utterances[ 0 ]
		);
	} );

	QUnit.test( 'skipAheadUtterance()', function ( assert ) {
		assert.expect( 2 );
		utterances[ 0 ].audio.src = 'loaded';
		mw.wikispeech.wikispeech.prepareUtterance( utterances[ 1 ] );
		mw.wikispeech.wikispeech.play();

		mw.wikispeech.wikispeech.skipAheadUtterance();

		assert.strictEqual( utterances[ 0 ].audio.paused, true );
		assert.strictEqual( utterances[ 1 ].audio.paused, false );
	} );

	QUnit.test( 'skipAheadUtterance(): stop if no next utterance', function ( assert ) {
		assert.expect( 1 );
		sinon.spy( mw.wikispeech.wikispeech, 'stop' );
		mw.wikispeech.wikispeech.prepareUtterance( utterances[ 1 ] );
		mw.wikispeech.wikispeech.playUtterance( utterances[ 1 ] );

		mw.wikispeech.wikispeech.skipAheadUtterance();

		assert.strictEqual( mw.wikispeech.wikispeech.stop.called, true );
	} );

	QUnit.test( 'skipBackUtterance()', function ( assert ) {
		assert.expect( 2 );
		utterances[ 0 ].audio.src = 'loaded';
		mw.wikispeech.wikispeech.prepareUtterance( utterances[ 1 ] );
		mw.wikispeech.wikispeech.playUtterance( utterances[ 1 ] );

		mw.wikispeech.wikispeech.skipBackUtterance();

		assert.strictEqual( utterances[ 1 ].audio.paused, true );
		assert.strictEqual( utterances[ 0 ].audio.paused, false );
	} );

	QUnit.test( 'skipBackUtterance(): restart if first utterance', function ( assert ) {
		assert.expect( 2 );
		utterances[ 0 ].audio.src = 'loaded';
		mw.wikispeech.wikispeech.playUtterance( utterances[ 0 ] );
		utterances[ 0 ].audio.currentTime = 1.0;

		mw.wikispeech.wikispeech.skipBackUtterance();

		assert.strictEqual(
			utterances[ 0 ].audio.paused,
			false
		);
		assert.strictEqual(
			utterances[ 0 ].audio.currentTime,
			0.0
		);
	} );

	QUnit.test( 'skipBackUtterance(): restart if played long enough', function ( assert ) {
		assert.expect( 3 );
		utterances[ 0 ].audio.src = 'loaded';
		mw.wikispeech.wikispeech.prepareUtterance( utterances[ 1 ] );
		mw.wikispeech.wikispeech.playUtterance( utterances[ 1 ] );
		utterances[ 1 ].audio.currentTime = 3.1;

		mw.wikispeech.wikispeech.skipBackUtterance();

		assert.strictEqual(
			utterances[ 1 ].audio.paused,
			false
		);
		assert.strictEqual(
			utterances[ 1 ].audio.currentTime,
			0.0
		);
		assert.strictEqual(
			utterances[ 0 ].audio.paused,
			true
		);
	} );

	QUnit.test( 'getNextUtterance()', function ( assert ) {
		var nextUtterance;

		assert.expect( 1 );

		nextUtterance =
			mw.wikispeech.wikispeech.getNextUtterance( utterances[ 0 ] );

		assert.strictEqual( nextUtterance, utterances[ 1 ] );
	} );

	QUnit.test( 'getNextUtterance(): return null if no current utterance', function ( assert ) {
		var nextUtterance;

		assert.expect( 1 );

		nextUtterance = mw.wikispeech.wikispeech.getNextUtterance( null );

		assert.strictEqual( nextUtterance, null );
	} );

	QUnit.test( 'addTokens()', function ( assert ) {
		var tokens;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml( 'Utterance zero.' );
		tokens = [
			{
				orth: 'Utterance',
				endtime: 1.0
			},
			{
				orth: 'zero',
				endtime: 2.0
			},
			{
				orth: '.',
				endtime: 3.0
			}
		];

		mw.wikispeech.wikispeech.addTokens( utterances[ 0 ], tokens );

		assert.deepEqual(
			[
				{
					string: 'Utterance',
					utterance: utterances[ 0 ],
					startTime: 0.0,
					endTime: 1.0,
					items: [ utterances[ 0 ].content[ 0 ] ],
					startOffset: 0,
					endOffset: 8
				},
				{
					string: 'zero',
					utterance: utterances[ 0 ],
					startTime: 1.0,
					endTime: 2.0,
					items: [ utterances[ 0 ].content[ 0 ] ],
					startOffset: 10,
					endOffset: 13
				},
				{
					string: '.',
					utterance: utterances[ 0 ],
					startTime: 2.0,
					endTime: 3.0,
					items: [ utterances[ 0 ].content[ 0 ] ],
					startOffset: 14,
					endOffset: 14
				}
			],
			utterances[ 0 ].tokens
		);
	} );

	QUnit.test( 'addTokens(): handle tag', function ( assert ) {
		var tokens;

		assert.expect( 12 );
		mw.wikispeech.test.util.setContentHtml( 'Utterance with <b>tag</b>.' );
		utterances[ 0 ].content[ 0 ].string = 'Utterance with ';
		utterances[ 0 ].content[ 1 ] = { string: 'tag' };
		utterances[ 0 ].content[ 2 ] = { string: '.' };
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

		mw.wikispeech.wikispeech.addTokens( utterances[ 0 ], tokens );

		assert.deepEqual(
			utterances[ 0 ].tokens[ 0 ].items,
			[ utterances[ 0 ].content[ 0 ] ]
		);
		assert.strictEqual( utterances[ 0 ].tokens[ 0 ].startOffset, 0 );
		assert.strictEqual( utterances[ 0 ].tokens[ 0 ].endOffset, 8 );
		assert.deepEqual(
			utterances[ 0 ].tokens[ 1 ].items,
			[ utterances[ 0 ].content[ 0 ] ]
		);
		assert.strictEqual( utterances[ 0 ].tokens[ 1 ].startOffset, 10 );
		assert.strictEqual( utterances[ 0 ].tokens[ 1 ].endOffset, 13 );
		assert.deepEqual(
			utterances[ 0 ].tokens[ 2 ].items,
			[ utterances[ 0 ].content[ 1 ] ]
		);
		assert.strictEqual( utterances[ 0 ].tokens[ 2 ].startOffset, 0 );
		assert.strictEqual( utterances[ 0 ].tokens[ 2 ].endOffset, 2 );
		assert.deepEqual(
			utterances[ 0 ].tokens[ 3 ].items,
			[ utterances[ 0 ].content[ 2 ] ]
		);
		assert.strictEqual( utterances[ 0 ].tokens[ 3 ].startOffset, 0 );
		assert.strictEqual( utterances[ 0 ].tokens[ 3 ].endOffset, 0 );
	} );

	QUnit.test( 'addTokens(): handle removed element', function ( assert ) {
		var tokens;

		assert.expect( 3 );
		mw.wikispeech.test.util.setContentHtml(
			'Utterance with <del>removed tag</del>.'
		);
		utterances[ 0 ].content[ 0 ].string = 'Utterance with ';
		utterances[ 0 ].content[ 1 ] = { string: '.' };
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

		mw.wikispeech.wikispeech.addTokens( utterances[ 0 ], tokens );

		assert.deepEqual(
			utterances[ 0 ].tokens[ 2 ].items,
			[ utterances[ 0 ].content[ 1 ] ]
		);
		assert.strictEqual( utterances[ 0 ].tokens[ 2 ].startOffset, 0 );
		assert.strictEqual( utterances[ 0 ].tokens[ 2 ].endOffset, 0 );
	} );

	QUnit.test( 'addTokens(): divided tokens', function ( assert ) {
		var tokens;

		assert.expect( 3 );
		mw.wikispeech.test.util.setContentHtml(
			'Utterance with divided to<b>k</b>en.'
		);
		utterances[ 0 ].content[ 0 ].string = 'Utterance with divided to';
		utterances[ 0 ].content[ 1 ] = { string: 'k' };
		utterances[ 0 ].content[ 2 ] = { string: 'en.' };
		tokens = [
			{ orth: 'Utterance' },
			{ orth: 'with' },
			{ orth: 'divided' },
			{ orth: 'token' },
			{ orth: '.' }
		];

		mw.wikispeech.wikispeech.addTokens( utterances[ 0 ], tokens );

		assert.deepEqual(
			utterances[ 0 ].tokens[ 3 ].items,
			[
				utterances[ 0 ].content[ 0 ],
				utterances[ 0 ].content[ 1 ],
				utterances[ 0 ].content[ 2 ]
			]
		);
		assert.strictEqual( utterances[ 0 ].tokens[ 3 ].startOffset, 23 );
		assert.strictEqual( utterances[ 0 ].tokens[ 3 ].endOffset, 1 );
	} );

	QUnit.test( 'addTokens(): ambiguous tokens', function ( assert ) {
		var tokens;

		assert.expect( 4 );
		mw.wikispeech.test.util.setContentHtml( 'A word and the same word.' );
		utterances[ 0 ].content[ 0 ].string = 'A word and the same word.';
		tokens = [
			{ orth: 'A' },
			{ orth: 'word' },
			{ orth: 'and' },
			{ orth: 'the' },
			{ orth: 'same' },
			{ orth: 'word' },
			{ orth: '.' }
		];

		mw.wikispeech.wikispeech.addTokens( utterances[ 0 ], tokens );

		assert.deepEqual( utterances[ 0 ].tokens[ 1 ].startOffset, 2 );
		assert.deepEqual( utterances[ 0 ].tokens[ 1 ].endOffset, 5 );
		assert.deepEqual( utterances[ 0 ].tokens[ 5 ].startOffset, 20 );
		assert.deepEqual( utterances[ 0 ].tokens[ 5 ].endOffset, 23 );
	} );

	QUnit.test( 'addTokens(): ambiguous tokens in tag', function ( assert ) {
		var tokens;

		assert.expect( 2 );
		mw.wikispeech.test.util.setContentHtml(
			'Utterance with <b>word and word</b>.'
		);
		utterances[ 0 ].content[ 0 ].string = 'Utterance with ';
		utterances[ 0 ].content[ 1 ] = { string: 'word and word' };
		utterances[ 0 ].content[ 2 ] = { string: '.' };
		tokens = [
			{ orth: 'Utterance' },
			{ orth: 'with' },
			{ orth: 'word' },
			{ orth: 'and' },
			{ orth: 'word' },
			{ orth: '.' }
		];

		mw.wikispeech.wikispeech.addTokens( utterances[ 0 ], tokens );

		assert.deepEqual( utterances[ 0 ].tokens[ 4 ].startOffset, 9 );
		assert.deepEqual( utterances[ 0 ].tokens[ 4 ].endOffset, 12 );
	} );

	QUnit.test( 'addTokens(): multiple utterances', function ( assert ) {
		var tokens;

		assert.expect( 6 );
		mw.wikispeech.test.util.setContentHtml(
			'An utterance. Another utterance.'
		);
		utterances[ 1 ].content[ 0 ].string =
			'Another utterance.';
		utterances[ 1 ].startOffset = 14;
		tokens = [
			{ orth: 'Another' },
			{ orth: 'utterance' },
			{ orth: '.' }
		];

		mw.wikispeech.wikispeech.addTokens( utterances[ 1 ], tokens );

		assert.deepEqual( utterances[ 1 ].tokens[ 0 ].startOffset, 14 );
		assert.deepEqual( utterances[ 1 ].tokens[ 0 ].endOffset, 20 );
		assert.deepEqual( utterances[ 1 ].tokens[ 1 ].startOffset, 22 );
		assert.deepEqual( utterances[ 1 ].tokens[ 1 ].endOffset, 30 );
		assert.deepEqual( utterances[ 1 ].tokens[ 2 ].startOffset, 31 );
		assert.deepEqual( utterances[ 1 ].tokens[ 2 ].endOffset, 31 );
	} );

	QUnit.test( 'addTokens(): multiple utterances and nodes', function ( assert ) {
		var tokens;

		assert.expect( 6 );
		mw.wikispeech.test.util.setContentHtml(
			'An utterance. Another <b>utterance</b>.'
		);
		utterances[ 1 ].content = [
			{ string: 'Another ' },
			{ string: 'utterance' },
			{ string: '.' }
		];
		utterances[ 1 ].startOffset = 14;
		tokens = [
			{ orth: 'Another' },
			{ orth: 'utterance' },
			{ orth: '.' }
		];

		mw.wikispeech.wikispeech.addTokens( utterances[ 1 ], tokens );

		assert.deepEqual( utterances[ 1 ].tokens[ 0 ].startOffset, 14 );
		assert.deepEqual( utterances[ 1 ].tokens[ 0 ].endOffset, 20 );
		assert.deepEqual( utterances[ 1 ].tokens[ 1 ].startOffset, 0 );
		assert.deepEqual( utterances[ 1 ].tokens[ 1 ].endOffset, 8 );
		assert.deepEqual( utterances[ 1 ].tokens[ 2 ].startOffset, 0 );
		assert.deepEqual( utterances[ 1 ].tokens[ 2 ].endOffset, 0 );
	} );

	QUnit.test( 'addTokens(): ambiguous, one character long tokens', function ( assert ) {
		var tokens;

		assert.expect( 2 );
		mw.wikispeech.test.util.setContentHtml( 'a a a.' );
		utterances[ 0 ].content[ 0 ].string = 'a a a.';
		tokens = [
			{ orth: 'a' },
			{ orth: 'a' },
			{ orth: 'a' },
			{ orth: '.' }
		];

		mw.wikispeech.wikispeech.addTokens( utterances[ 0 ], tokens );

		assert.strictEqual( utterances[ 0 ].tokens[ 2 ].startOffset, 4 );
		assert.strictEqual( utterances[ 0 ].tokens[ 2 ].endOffset, 4 );
	} );

	QUnit.test( 'getCurrentToken()', function ( assert ) {
		var token;

		assert.expect( 1 );
		utterances[ 0 ].audio.src = 'loaded';
		utterances[ 0 ].tokens = [
			{
				startTime: 0.0,
				endTime: 1.0
			},
			{
				startTime: 1.0,
				endTime: 2.0
			},
			{
				startTime: 2.0,
				endTime: 3.0
			}
		];
		utterances[ 0 ].audio.currentTime = 1.1;
		mw.wikispeech.wikispeech.play();

		token = mw.wikispeech.wikispeech.getCurrentToken();

		assert.strictEqual( token, utterances[ 0 ].tokens[ 1 ] );
	} );

	QUnit.test( 'getCurrentToken(): get first token', function ( assert ) {
		var token;

		assert.expect( 1 );
		utterances[ 0 ].audio.src = 'loaded';
		utterances[ 0 ].tokens = [
			{
				startTime: 0.0,
				endTime: 1.0
			},
			{
				startTime: 1.0,
				endTime: 2.0
			},
			{
				startTime: 2.0,
				endTime: 3.0
			}
		];
		utterances[ 0 ].audio.currentTime = 0.1;
		mw.wikispeech.wikispeech.play();

		token = mw.wikispeech.wikispeech.getCurrentToken();

		assert.strictEqual( token, utterances[ 0 ].tokens[ 0 ] );
	} );

	QUnit.test( 'getCurrentToken(): get the last token', function ( assert ) {
		var token;

		assert.expect( 1 );
		utterances[ 0 ].audio.src = 'loaded';
		utterances[ 0 ].tokens = [
			{
				startTime: 0.0,
				endTime: 1.0
			},
			{
				startTime: 1.0,
				endTime: 2.0
			},
			{
				startTime: 2.0,
				endTime: 3.0
			}
		];
		utterances[ 0 ].audio.currentTime = 2.1;
		mw.wikispeech.wikispeech.play();

		token = mw.wikispeech.wikispeech.getCurrentToken();

		assert.strictEqual( token, utterances[ 0 ].tokens[ 2 ] );
	} );

	QUnit.test( 'getCurrentToken(): get the last token when current time is equal to last tokens end time', function ( assert ) {
		var token;

		assert.expect( 1 );
		utterances[ 0 ].audio.src = 'loaded';
		utterances[ 0 ].tokens = [
			{
				startTime: 0.0,
				endTime: 1.0
			},
			{
				startTime: 1.0,
				endTime: 2.0
			}
		];
		utterances[ 0 ].audio.currentTime = 2.0;
		mw.wikispeech.wikispeech.play();

		token = mw.wikispeech.wikispeech.getCurrentToken();

		assert.strictEqual( token, utterances[ 0 ].tokens[ 1 ] );
	} );

	QUnit.test( 'getCurrentToken(): ignore tokens with no duration', function ( assert ) {
		var token;

		assert.expect( 1 );
		utterances[ 0 ].audio.src = 'loaded';
		utterances[ 0 ].tokens = [
			{
				startTime: 0.0,
				endTime: 1.0
			},
			{
				startTime: 1.0,
				endTime: 1.0
			},
			{
				startTime: 1.0,
				endTime: 2.0
			}
		];
		utterances[ 0 ].audio.currentTime = 1.0;
		mw.wikispeech.wikispeech.play();

		token = mw.wikispeech.wikispeech.getCurrentToken();

		assert.strictEqual(
			token,
			utterances[ 0 ].tokens[ 2 ]
		);
	} );

	QUnit.test( 'getCurrentToken(): give correct token if there are tokens with no duration', function ( assert ) {
		var token;

		assert.expect( 1 );
		utterances[ 0 ].audio.src = 'loaded';
		utterances[ 0 ].tokens = [
			{
				startTime: 0.0,
				endTime: 1.0
			},
			{
				startTime: 1.0,
				endTime: 1.0
			},
			{
				startTime: 1.0,
				endTime: 2.0
			}
		];
		utterances[ 0 ].audio.currentTime = 1.1;
		mw.wikispeech.wikispeech.play();

		token = mw.wikispeech.wikispeech.getCurrentToken();

		assert.strictEqual( token, utterances[ 0 ].tokens[ 2 ] );
	} );

	QUnit.test( 'skipAheadToken()', function ( assert ) {
		assert.expect( 2 );
		utterances[ 0 ].audio.src = 'loaded';
		utterances[ 0 ].tokens = [
			{
				string: 'one',
				utterance: utterances[ 0 ],
				startTime: 0.0,
				endTime: 1.0
			},
			{
				string: 'two',
				utterance: utterances[ 0 ],
				startTime: 1.0,
				endTime: 2.0
			}
		];
		mw.wikispeech.wikispeech.play();

		mw.wikispeech.wikispeech.skipAheadToken();

		assert.strictEqual(
			utterances[ 0 ].audio.currentTime,
			1.0
		);
		assert.strictEqual(
			mw.wikispeech.highlighter.startTokenHighlighting.calledWith(
				utterances[ 0 ].tokens[ 1 ]
			),
			true
		);
	} );

	QUnit.test( 'skipAheadToken(): skip ahead utterance when last token', function ( assert ) {
		assert.expect( 1 );
		utterances[ 0 ].audio.src = 'loaded';
		utterances[ 0 ].tokens = [
			{
				string: 'first',
				utterance: utterances[ 0 ],
				startTime: 0.0,
				endTime: 1.0
			},
			{
				string: 'last',
				utterance: utterances[ 0 ],
				startTime: 1.0,
				endTime: 2.0
			}
		];
		mw.wikispeech.wikispeech.play();
		utterances[ 0 ].audio.currentTime = 1.1;
		sinon.stub( mw.wikispeech.wikispeech, 'skipAheadUtterance' );

		mw.wikispeech.wikispeech.skipAheadToken();

		assert.strictEqual( mw.wikispeech.wikispeech.skipAheadUtterance.called, true );
	} );

	QUnit.test( 'skipAheadToken(): ignore silent tokens', function ( assert ) {
		assert.expect( 1 );
		utterances[ 0 ].audio.src = 'loaded';
		utterances[ 0 ].tokens = [
			{
				string: 'starting word',
				utterance: utterances[ 0 ],
				startTime: 0.0,
				endTime: 1.0
			},
			{
				string: 'no duration',
				utterance: utterances[ 0 ],
				startTime: 1.0,
				endTime: 1.0
			},
			{
				string: '',
				utterance: utterances[ 0 ],
				startTime: 1.0,
				endTime: 2.0
			},
			{
				string: 'goal',
				utterance: utterances[ 0 ],
				startTime: 2.0,
				endTime: 3.0
			}
		];
		mw.wikispeech.wikispeech.play();

		mw.wikispeech.wikispeech.skipAheadToken();

		assert.strictEqual(
			utterances[ 0 ].audio.currentTime,
			2.0
		);
	} );

	QUnit.test( 'skipBackToken()', function ( assert ) {
		assert.expect( 2 );
		mw.wikispeech.wikispeech.prepareUtterance(
			utterances[ 0 ]
		);
		utterances[ 0 ].tokens = [
			{
				string: 'one',
				startTime: 0.0,
				endTime: 1.0,
				utterance: utterances[ 0 ]
			},
			{
				string: 'two',
				startTime: 1.0,
				endTime: 2.0,
				utterance: utterances[ 0 ]
			}
		];
		mw.wikispeech.wikispeech.play();
		utterances[ 0 ].audio.currentTime = 1.1;

		mw.wikispeech.wikispeech.skipBackToken();

		assert.strictEqual(
			utterances[ 0 ].audio.currentTime,
			0.0
		);
		assert.strictEqual(
			mw.wikispeech.highlighter.startTokenHighlighting.calledWith(
				utterances[ 0 ].tokens[ 0 ]
			),
			true
		);
	} );

	QUnit.test( 'skipBackToken(): skip to last token in previous utterance if first token', function ( assert ) {
		assert.expect( 3 );
		mw.wikispeech.wikispeech.prepareUtterance(
			utterances[ 0 ]
		);
		utterances[ 0 ].tokens = [
			{
				string: 'one',
				startTime: 0.0,
				endTime: 1.0
			},
			{
				string: 'two',
				startTime: 1.0,
				endTime: 2.0,
				utterance: utterances[ 0 ]
			}
		];
		mw.wikispeech.wikispeech.prepareUtterance(
			utterances[ 1 ]
		);
		utterances[ 1 ].tokens = [
			{
				string: 'three',
				startTime: 0.0,
				endTime: 1.0,
				utterance: utterances[ 1 ]
			}
		];
		mw.wikispeech.wikispeech.playUtterance(
			utterances[ 1 ]
		);

		mw.wikispeech.wikispeech.skipBackToken();

		assert.strictEqual(
			utterances[ 0 ].audio.paused,
			false
		);
		assert.strictEqual(
			utterances[ 0 ].audio.currentTime,
			1.0
		);
		assert.strictEqual(
			utterances[ 1 ].audio.paused,
			true
		);
	} );

	QUnit.test( 'skipBackToken(): ignore silent tokens', function ( assert ) {
		assert.expect( 1 );
		mw.wikispeech.wikispeech.prepareUtterance(
			utterances[ 0 ]
		);
		utterances[ 0 ].tokens = [
			{
				string: 'goal',
				startTime: 0.0,
				endTime: 1.0,
				utterance: utterances[ 0 ]
			},
			{
				string: 'no duration',
				startTime: 1.0,
				endTime: 1.0
			},
			{
				string: '',
				startTime: 1.0,
				endTime: 2.0
			},
			{
				string: 'starting word',
				startTime: 2.0,
				endTime: 3.0,
				utterance: utterances[ 0 ]
			}
		];
		mw.wikispeech.wikispeech.playUtterance(
			utterances[ 0 ]
		);
		utterances[ 0 ].audio.currentTime = 2.1;

		mw.wikispeech.wikispeech.skipBackToken();

		assert.strictEqual(
			utterances[ 0 ].audio.currentTime,
			0.0
		);
	} );

	QUnit.test( 'addControlPanel(): the stack is added to the play-stop button and buffering icon is initially hidden ', function ( assert ) {
		assert.expect( 2 );
		mw.wikispeech.wikispeech.addControlPanel();
		mw.wikispeech.wikispeech.addStackToPlayStopButton();
		assert.ok(
			$( '.ext-wikispeech-play-stop-button' ).has(
				$( '.ext-wikispeech-play-stop-stack' ) )
		);
		assert.strictEqual(
			$( '.ext-wikispeech-buffering-icon' ).css( 'visibility' ),
			'hidden'
		);
	} );

	QUnit.test( 'playUtterance(): audio element is not ready and the buffering icon is displayed', function ( assert ) {
		assert.expect( 1 );
		setUpBufferIcon( false );
		assert.strictEqual(
			$( '.ext-wikispeech-buffering-icon' ).css( 'visibility' ),
			'visible'
		);
	} );

	QUnit.test( 'playUtterance(): audio element is ready and the buffering icon is not displayed', function ( assert ) {
		assert.expect( 1 );
		setUpBufferIcon( true );
		assert.strictEqual(
			$( '.ext-wikispeech-buffering-icon' ).css( 'visibility' ),
			'hidden'
		);
	} );

	QUnit.test( 'playUtterance(): when loading audio starts playing, the buffering icon is turned off', function ( assert ) {
		assert.expect( 2 );
		setUpBufferIcon( false );
		assert.deepEqual(
			$( '.ext-wikispeech-buffering-icon' ).css( 'visibility' ),
			'visible'
		);
		$( utterances[ 0 ].audio ).trigger( 'canplay' );
		assert.strictEqual(
			$( '.ext-wikispeech-buffering-icon' ).css( 'visibility' ),
			'hidden'
		);
	} );

	QUnit.test( 'playUtterance(): buffering icon is shown when former of two successive utterances plays while later loads', function ( assert ) {
		assert.expect( 1 );
		mw.wikispeech.wikispeech.addControlPanel();
		mw.wikispeech.wikispeech.addStackToPlayStopButton();
		// ensure that the audio is not ready
		mw.wikispeech.wikispeech.prepareUtterance( utterances[ 0 ] );
		mw.wikispeech.wikispeech.prepareUtterance( utterances[ 1 ] );
		mw.wikispeech.wikispeech.playUtterance( utterances[ 0 ] );
		mw.wikispeech.wikispeech.playUtterance( utterances[ 1 ] );
		$( utterances[ 0 ].audio ).trigger( 'canplay' );
		assert.strictEqual(
			$( '.ext-wikispeech-buffering-icon' ).css( 'visibility' ),
			'visible'
		);
	} );
}( mediaWiki, jQuery ) );
