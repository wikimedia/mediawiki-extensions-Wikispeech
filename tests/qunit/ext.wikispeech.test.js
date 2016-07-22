( function ( mw, $ ) {
	var wikispeech, server;

	QUnit.module( 'ext.wikispeech', {
		setup: function () {
			wikispeech = new mw.wikispeech.Wikispeech();
			server = sinon.fakeServer.create();
			server.respondWith( '{"audio": "http://server.url/audio"}' );
			// overrideMimeType() isn't defined by default.
			server.xhr.prototype.overrideMimeType = function () {};
			$( '#qunit-fixture' ).append( createUtteranceElement(
				'utterance-0',
				'A mockup utterance.'
			) );
			$( '#qunit-fixture' ).append( createUtteranceElement(
				'utterance-1',
				'Another mockup utterance.'
			) );
			$( '#qunit-fixture' ).append(
				$( '<h1></h1>' ).attr( 'id', 'firstHeading' )
			);
			mw.config.set(
				'wgWikispeechKeyboardShortcuts', {
					playStop: {
						key: 32,
						modifiers: [ 'ctrl' ]
					},
					skipAheadUtterance: {
						key: 39,
						modifiers: [ 'ctrl' ]
					}
				}
			);
		},
		teardown: function () {
			server.restore();
		}
	} );

	function createUtteranceElement( id, text ) {
		return $( '<utterance></utterance>' )
			.attr( 'id', id )
			.append( $( '<text></text>' )
				.text( text ) )
			.append( $( '<audio></audio>' ) );
	}

	QUnit.test( 'prepareUtterance', function ( assert ) {
		assert.expect( 1 );
		sinon.spy( wikispeech, 'loadAudio' );

		wikispeech.prepareUtterance( $( '#utterance-0' ) );

		assert.strictEqual(
			wikispeech.loadAudio.calledWith( $( '#utterance-0' ) ),
			true
		);
	} );

	// jscs:disable validateQuoteMarks
	QUnit.test( "prepareUtterance: don't request if already requested", function ( assert ) {
		// jscs:enable validateQuoteMarks
		assert.expect( 1 );
		sinon.spy( wikispeech, 'loadAudio' );
		$( '#utterance-0' ).prop( 'requested', true );

		wikispeech.prepareUtterance( $( '#utterance-0' ) );

		assert.strictEqual( wikispeech.loadAudio.called, false );
	} );

	QUnit.test( 'prepareUtterance: prepare next utterance when playing', function ( assert ) {
		var $nextUtterance;

		assert.expect( 1 );
		wikispeech.prepareUtterance( $( '#utterance-0' ) );
		$nextUtterance = $( '#utterance-1' );
		sinon.spy( wikispeech, 'prepareUtterance' );

		$( '#utterance-0 audio' ).trigger( 'play' );

		assert.strictEqual(
			wikispeech.prepareUtterance.calledWith( $nextUtterance ),
			true
		);
	} );

	// jscs:disable validateQuoteMarks
	QUnit.test( "prepareUtterance: don't prepare next audio if it doesn't exist", function ( assert ) {
		// jscs:enable validateQuoteMarks
		assert.expect( 1 );
		sinon.spy( wikispeech, 'prepareUtterance' );
		wikispeech.prepareUtterance( $( '#utterance-1' ) );

		$( '#utterance-1 audio' ).trigger( 'play' );

		assert.strictEqual( wikispeech.prepareUtterance.calledWith(
			$( '#utterance-2' ) ), false );
	} );

	QUnit.test( 'prepareUtterance: play next utterance when ended', function ( assert ) {
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

	QUnit.test( 'prepareUtterance: stop when end of text is reached', function ( assert ) {
		var $lastUtterance;

		assert.expect( 1 );
		sinon.spy( wikispeech, 'stop' );
		$lastUtterance = $( '#utterance-1' );
		wikispeech.prepareUtterance( $lastUtterance );
		wikispeech.playUtterance( $lastUtterance );

		$lastUtterance.children( 'audio' ).trigger( 'ended' );

		assert.strictEqual( wikispeech.stop.called, true );
	} );

	QUnit.test( 'loadAudio', function ( assert ) {
		assert.expect( 3 );

		wikispeech.loadAudio( $( '#utterance-0' ) );

		server.respond();
		assert.strictEqual(
			server.requests[ 0 ].requestBody,
			'lang=en&input_type=text&input=A+mockup+utterance.'
		);
		assert.strictEqual(
			$( '#utterance-0 audio' ).attr( 'src' ),
			'http://server.url/audio'
		);
		assert.strictEqual(
			$( '#utterance-0' ).prop( 'requested' ),
			true
		);
	} );

	QUnit.test( 'addPlayStopButton', function ( assert ) {
		assert.expect( 1 );
		wikispeech.addPlayStopButton();

		assert.strictEqual(
			$( '#firstHeading #ext-wikispeech-play-stop-button' ).length,
			1
		);
	} );

	QUnit.test( 'addPlayStopButton: clicking calls playOrStop()', function ( assert ) {
		assert.expect( 1 );
		wikispeech.addPlayStopButton();
		sinon.spy( wikispeech, 'playOrStop' );

		$( '#ext-wikispeech-play-stop-button' ).click();

		assert.strictEqual( wikispeech.playOrStop.called, true );
	} );

	QUnit.test( 'playOrStop: play', function ( assert ) {
		assert.expect( 1 );
		sinon.spy( wikispeech, 'play' );

		wikispeech.playOrStop();

		assert.strictEqual( wikispeech.play.called, true );
	} );

	QUnit.test( 'playOrStop: stop', function ( assert ) {
		assert.expect( 1 );
		wikispeech.play();
		sinon.spy( wikispeech, 'stop' );

		wikispeech.playOrStop();

		assert.strictEqual( wikispeech.stop.called, true );
	} );

	QUnit.test( 'addKeyboardShortcut: shortcut for playOrStop()', function ( assert ) {
		assert.expect( 1 );
		sinon.spy( wikispeech, 'playOrStop' );
		wikispeech.addKeyboardShortcuts();

		$( document ).trigger( createKeydownEvent( 32, 'c' ) );

		assert.strictEqual( wikispeech.playOrStop.called, true );
	} );

	/**
	 * Create a keydown event.
	 *
	 * @param keyCode The key code for the event.
	 * @param {string} modifiers A string that defines the modifiers. The
	 *  characters c, a and s triggers the modifiers for ctrl, alt and shift,
	 *  respectively.
	 * @return The created keydown event.
	 */

	function createKeydownEvent( keyCode, modifiers ) {
		var event;

		event = $.Event( 'keydown' );
		event.which = keyCode;
		event.ctrlKey = modifiers.indexOf( 'c' ) >= 0;
		event.altKey = modifiers.indexOf( 'a' ) >= 0;
		event.shiftKey = modifiers.indexOf( 's' ) >= 0;
		return event;
	}

	QUnit.test( 'addKeyboardShortcut: shortcut for skipAheadUtterance()', function ( assert ) {
		assert.expect( 1 );
		sinon.spy( wikispeech, 'skipAheadUtterance' );
		wikispeech.addKeyboardShortcuts();

		$( document ).trigger( createKeydownEvent( 39, 'c' ) );

		assert.strictEqual( wikispeech.skipAheadUtterance.called, true );
	} );

	QUnit.test( 'stop', function ( assert ) {
		assert.expect( 4 );
		wikispeech.addPlayStopButton();
		wikispeech.play();
		$( '#utterance-0 audio' ).prop( 'currentTime', 1 );

		wikispeech.stop();

		assert.strictEqual( $( '#utterance-0 audio' ).prop( 'paused' ), true );
		assert.strictEqual(
			$( '#utterance-0 audio' ).prop( 'currentTime' ),
			0
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

	QUnit.test( 'play', function ( assert ) {
		var $firstUtterance;

		assert.expect( 3 );
		wikispeech.addPlayStopButton();
		$firstUtterance = $( '#utterance-0' );

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

	QUnit.test( 'addSkipAheadSentenceButton: clicking calls skipAheadUtterance()', function ( assert ) {
		var $button;

		assert.expect( 1 );
		wikispeech.addSkipAheadSentenceButton();
		$button = $( '#ext-wikispeech-skip-ahead-sentence-button' );
		sinon.spy( wikispeech, 'skipAheadUtterance' );

		$button.trigger( 'click' );

		assert.strictEqual( wikispeech.skipAheadUtterance.called, true );
	} );

	QUnit.test( 'skipAheadUtterance', function ( assert ) {
		assert.expect( 2 );
		wikispeech.play();

		wikispeech.skipAheadUtterance();

		assert.strictEqual( $( '#utterance-0 audio' ).prop( 'paused' ), true );
		assert.strictEqual(
			$( '#utterance-1 audio' ).prop( 'paused' ),
			false
		);
	} );

	QUnit.test( 'skipAheadUtterance: stop if no next utterance', function ( assert ) {
		assert.expect( 1 );
		sinon.spy( wikispeech, 'stop' );
		wikispeech.playUtterance( $( '#utterance-1' ) );

		wikispeech.skipAheadUtterance();

		assert.strictEqual( wikispeech.stop.called, true );
	} );

	QUnit.test( 'getNextUtterance', function ( assert ) {
		var $nextUtterance;

		assert.expect( 1 );
		$nextUtterance =
			wikispeech.getNextUtterance( $( '#utterance-0' ) );

		assert.strictEqual(
			$nextUtterance.get( 0 ),
			$( '#utterance-1' ).get( 0 )
		);
	} );

	QUnit.test( 'getNextUtterance: return the empty object if no current utterance', function ( assert ) {
		var $nextUtterance;

		assert.expect( 1 );
		$nextUtterance = wikispeech.getNextUtterance( $() );

		assert.strictEqual( $nextUtterance.length, 0 );
	} );
} )( mediaWiki, jQuery );
