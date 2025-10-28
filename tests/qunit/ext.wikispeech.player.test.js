const Player = require( 'ext.wikispeech/ext.wikispeech.player.js' );
const Highlighter = require( 'ext.wikispeech/ext.wikispeech.highlighter.js' );
const Ui = require( 'ext.wikispeech/ext.wikispeech.ui.js' );
const SelectionPlayer = require( 'ext.wikispeech/ext.wikispeech.selectionPlayer.js' );
const Storage = require( 'ext.wikispeech/ext.wikispeech.storage.js' );

QUnit.module( 'ext.wikispeech.player', {
	beforeEach: function () {
		this.player = new Player();
		this.highlighter = sinon.stub( new Highlighter() );
		this.player.highlighter = this.highlighter;
		this.ui = sinon.stub( new Ui() );
		this.player.ui = this.ui;
		this.selectionPlayer = sinon.stub( new SelectionPlayer() );
		this.player.selectionPlayer = this.selectionPlayer;
		this.storage = sinon.stub( new Storage() );
		this.player.storage = this.storage;
		this.storage.utterancesLoaded.resolve();
		this.storage.utterances = [
			{
				audio: {
					play: function () {},
					pause: function () {}
				},
				content: []
			},
			{
				audio: {
					play: function () {},
					pause: function () {}
				}
			}
		];
		this.storage.prepareUtterance = sinon.stub().returns( $.Deferred().resolve() );

		mw.config.set(
			'wgWikispeechSkipBackRewindsThreshold',
			3.0
		);
		// Base case is that there is no selection. Test that test
		// for when there is a selection overwrites this.
		this.selectionPlayer.playSelectionIfValid.returns( false );
	}
} );

QUnit.test( 'playOrPause(): play', function ( assert ) {
	sinon.stub( this.player, 'play' );

	this.player.playOrPause();

	assert.strictEqual( this.player.play.called, true );
} );

QUnit.test( 'playOrPause(): pause', function ( assert ) {
	this.player.currentUtterance = this.storage.utterances[ 0 ];
	sinon.stub( this.player, 'pause' );

	this.player.playOrPause();

	assert.strictEqual( this.player.pause.called, true );
} );

QUnit.test( 'stop()', function () {
	this.player.currentUtterance = this.storage.utterances[ 0 ];
	this.storage.utterances[ 0 ].audio.currentTime = 1.0;
	sinon.stub( this.player, 'stopUtterance' );

	this.player.stop();

	sinon.assert.calledWith(
		this.player.stopUtterance, this.storage.utterances[ 0 ]
	);
	sinon.assert.called( this.ui.hideBufferingIcon );
} );

QUnit.test( 'play()', function () {
	sinon.stub( this.player, 'playUtterance' );

	this.player.play();

	sinon.assert.called( this.player.playUtterance );
} );

QUnit.test( 'pause()', function () {
	sinon.stub( this.player, 'pauseUtterance' );

	this.player.currentUtterance = this.storage.utterances[ 0 ];
	this.player.paused = false;
	this.player.pause();

	sinon.assert.calledWith( this.player.pauseUtterance, this.storage.utterances[ 0 ] );
} );

QUnit.test( 'play(): delay until utterances has been loaded', function () {
	sinon.stub( this.player, 'playUtterance' );
	// We want an unresolved promise for this test.
	this.storage.utterancesLoaded = $.Deferred();

	this.player.play();

	sinon.assert.notCalled( this.player.playUtterance );
} );

QUnit.test( 'play(): do not play utterance when selection is valid', function () {
	sinon.stub( this.player, 'playUtterance' );
	this.selectionPlayer.playSelectionIfValid.returns( true );

	this.player.play();

	sinon.assert.notCalled( this.player.playUtterance );
} );

QUnit.test( 'play(): play from beginning when selection is invalid', function () {
	sinon.stub( this.player, 'playUtterance' );
	this.selectionPlayer.playSelectionIfValid.returns( false );

	this.player.play();

	sinon.assert.calledWith(
		this.player.playUtterance,
		this.storage.utterances[ 0 ]
	);
} );

QUnit.test( 'playUtterance()', function () {
	const utterance = this.storage.utterances[ 0 ];
	sinon.stub( utterance.audio, 'play' );
	this.storage.prepareUtterance.returns( $.Deferred().resolve() );

	this.player.playUtterance( utterance );

	sinon.assert.called( utterance.audio.play );
	sinon.assert.calledWith( this.highlighter.highlightUtterance, utterance );
	sinon.assert.calledWith(
		this.ui.showBufferingIconIfAudioIsLoading,
		utterance.audio
	);
} );

QUnit.test( 'playUtterance(): stop playing utterance', function () {
	this.storage.prepareUtterance.returns( $.Deferred().resolve() );
	this.player.currentUtterance = this.storage.utterances[ 0 ];
	sinon.stub( this.player, 'stopUtterance' );

	this.player.playUtterance( this.storage.utterances[ 1 ] );

	sinon.assert.calledWith(
		this.player.stopUtterance,
		this.storage.utterances[ 0 ]
	);
} );

QUnit.test( 'playUtterance(): show load error dialog', function () {
	const utterance = this.storage.utterances[ 0 ];
	this.storage.prepareUtterance.returns( $.Deferred().reject() );
	this.ui.showLoadAudioError.returns( $.Deferred() );

	this.player.playUtterance( utterance );

	sinon.assert.called( this.ui.showLoadAudioError );
} );

QUnit.test( 'playUtterance(): show load error dialog again', function () {
	const utterance = this.storage.utterances[ 0 ];
	this.storage.prepareUtterance.returns( $.Deferred().reject() );
	this.ui.showLoadAudioError.onFirstCall().returns( $.Deferred().resolveWith( null, [ { action: 'retry' } ] ) );
	this.ui.showLoadAudioError.returns( $.Deferred() );

	this.player.playUtterance( utterance );

	sinon.assert.calledTwice( this.ui.showLoadAudioError );
} );

QUnit.test( 'playUtterance(): retry preparing utterance', function ( assert ) {
	const utterance = this.storage.utterances[ 0 ];
	this.storage.prepareUtterance.returns( $.Deferred().reject() );
	this.ui.showLoadAudioError.onFirstCall().returns( $.Deferred().resolveWith( null, [ { action: 'retry' } ] ) );
	this.ui.showLoadAudioError.returns( $.Deferred().resolve() );

	this.player.playUtterance( utterance );

	assert.true( this.storage.prepareUtterance.firstCall.calledWithExactly( utterance ) );
	assert.true( this.storage.prepareUtterance.secondCall.calledWithExactly( utterance ) );
} );

QUnit.test( 'stopUtterance()', function ( assert ) {
	this.storage.utterances[ 0 ].audio.currentTime = 1.0;
	sinon.stub( this.storage.utterances[ 0 ].audio, 'pause' );

	this.player.stopUtterance( this.storage.utterances[ 0 ] );

	sinon.assert.called( this.storage.utterances[ 0 ].audio.pause );
	assert.strictEqual( this.storage.utterances[ 0 ].audio.currentTime, 0 );
	sinon.assert.called( this.highlighter.clearHighlighting );
	sinon.assert.calledWith(
		this.ui.removeCanPlayListener,
		$( this.storage.utterances[ 0 ].audio )
	);
} );

QUnit.test( 'skipAheadUtterance()', function () {
	sinon.stub( this.player, 'playUtterance' );
	this.storage.getNextUtterance.returns( this.storage.utterances[ 1 ] );

	this.player.skipAheadUtterance();

	sinon.assert.calledWith(
		this.player.playUtterance,
		this.storage.utterances[ 1 ]
	);
} );

QUnit.test( 'skipAheadUtterance(): stop if no next utterance', function () {
	sinon.stub( this.player, 'stop' );
	this.storage.getNextUtterance.returns( null );

	this.player.skipAheadUtterance();

	sinon.assert.called( this.player.stop );
} );

QUnit.test( "skipAheadUtterance(): don't play prepared utterance when paused", function ( assert ) {
	this.storage.getNextUtterance.returns( this.storage.utterances[ 1 ] );
	const utterance = this.storage.utterances[ 1 ];
	sinon.stub( utterance.audio, 'play' );
	this.player.paused = true;
	const promise = $.Deferred().resolve();
	this.storage.prepareUtterance.returns( promise );
	const done = assert.async();

	this.player.skipAheadUtterance();

	promise.then( () => {
		sinon.assert.notCalled( utterance.audio.play );
		done();
	} );
} );

QUnit.test( 'skipBackUtterance()', function () {
	sinon.stub( this.player, 'playUtterance' );
	this.player.currentUtterance = this.storage.utterances[ 1 ];
	this.storage.getPreviousUtterance.returns( this.storage.utterances[ 0 ] );

	this.player.skipBackUtterance();

	sinon.assert.calledWith(
		this.player.playUtterance,
		this.storage.utterances[ 0 ]
	);
} );

QUnit.test( 'skipBackUtterance(): restart if first utterance', function ( assert ) {
	this.player.currentUtterance = this.storage.utterances[ 0 ];
	this.storage.utterances[ 0 ].audio.currentTime = 1.0;
	sinon.stub( this.storage.utterances[ 0 ].audio, 'pause' );
	this.player.skipBackUtterance();
	assert.strictEqual(
		this.storage.utterances[ 0 ].audio.currentTime,
		0
	);
	sinon.assert.notCalled( this.storage.utterances[ 0 ].audio.pause );
} );

QUnit.test( 'skipBackUtterance(): restart if played long enough', function ( assert ) {
	this.player.currentUtterance = this.storage.utterances[ 1 ];
	this.storage.utterances[ 1 ].audio.currentTime = 3.1;
	sinon.stub( this.player, 'playUtterance' );
	this.storage.getPreviousUtterance.returns( this.storage.utterances[ 0 ] );

	this.player.skipBackUtterance();

	assert.strictEqual(
		this.storage.utterances[ 1 ].audio.currentTime,
		0
	);
	sinon.assert.neverCalledWith(
		this.player.playUtterance, this.storage.utterances[ 0 ]
	);
} );

QUnit.test( 'getCurrentToken()', function ( assert ) {
	this.storage.utterances[ 0 ].audio.src = 'loaded';
	this.storage.utterances[ 0 ].tokens = [
		{
			startTime: 0,
			endTime: 1000
		},
		{
			startTime: 1000,
			endTime: 2000
		},
		{
			startTime: 2000,
			endTime: 3000
		}
	];
	this.storage.utterances[ 0 ].audio.currentTime = 1.1;
	this.storage.utterancesLoaded.resolve();
	this.player.currentUtterance = this.storage.utterances[ 0 ];

	const token = this.player.getCurrentToken();

	assert.strictEqual( token, this.storage.utterances[ 0 ].tokens[ 1 ] );
} );

QUnit.test( 'getCurrentToken(): get first token', function ( assert ) {
	this.storage.utterances[ 0 ].audio.src = 'loaded';
	this.storage.utterances[ 0 ].tokens = [
		{
			startTime: 0,
			endTime: 1000
		},
		{
			startTime: 1000,
			endTime: 2000
		},
		{
			startTime: 2000,
			endTime: 3000
		}
	];
	this.storage.utterances[ 0 ].audio.currentTime = 0.1;
	this.player.currentUtterance = this.storage.utterances[ 0 ];

	const token = this.player.getCurrentToken();

	assert.strictEqual( token, this.storage.utterances[ 0 ].tokens[ 0 ] );
} );

QUnit.test( 'getCurrentToken(): get the last token', function ( assert ) {
	this.storage.utterances[ 0 ].audio.src = 'loaded';
	this.storage.utterances[ 0 ].tokens = [
		{
			startTime: 0,
			endTime: 1000
		},
		{
			startTime: 1000,
			endTime: 2000
		},
		{
			startTime: 2000,
			endTime: 3000
		}
	];
	this.storage.utterances[ 0 ].audio.currentTime = 2.1;
	this.player.currentUtterance = this.storage.utterances[ 0 ];

	const token = this.player.getCurrentToken();

	assert.strictEqual( token, this.storage.utterances[ 0 ].tokens[ 2 ] );
} );

QUnit.test( 'getCurrentToken(): get the last token when current time is equal to last tokens end time', function ( assert ) {
	this.storage.utterances[ 0 ].audio.src = 'loaded';
	this.storage.utterances[ 0 ].tokens = [
		{
			startTime: 0,
			endTime: 1000
		},
		{
			startTime: 1000,
			endTime: 2000
		}
	];
	this.storage.utterances[ 0 ].audio.currentTime = 2.0;
	this.player.currentUtterance = this.storage.utterances[ 0 ];

	const token = this.player.getCurrentToken();

	assert.strictEqual( token, this.storage.utterances[ 0 ].tokens[ 1 ] );
} );

QUnit.test( 'getCurrentToken(): ignore tokens with no duration', function ( assert ) {
	this.storage.utterances[ 0 ].audio.src = 'loaded';
	this.storage.utterances[ 0 ].tokens = [
		{
			startTime: 0,
			endTime: 1000
		},
		{
			startTime: 1000,
			endTime: 1000
		},
		{
			startTime: 1000,
			endTime: 2000
		}
	];
	this.storage.utterances[ 0 ].audio.currentTime = 1.0;
	this.player.currentUtterance = this.storage.utterances[ 0 ];

	const token = this.player.getCurrentToken();

	assert.strictEqual(
		token,
		this.storage.utterances[ 0 ].tokens[ 2 ]
	);
} );

QUnit.test( 'getCurrentToken(): give correct token if there are tokens with no duration', function ( assert ) {
	this.storage.utterances[ 0 ].audio.src = 'loaded';
	this.storage.utterances[ 0 ].tokens = [
		{
			startTime: 0,
			endTime: 1000
		},
		{
			startTime: 1000,
			endTime: 1000
		},
		{
			startTime: 1000,
			endTime: 2000
		}
	];
	this.storage.utterances[ 0 ].audio.currentTime = 1.1;
	this.player.currentUtterance = this.storage.utterances[ 0 ];

	const token = this.player.getCurrentToken();

	assert.strictEqual( token, this.storage.utterances[ 0 ].tokens[ 2 ] );
} );

QUnit.test( 'skipAheadToken()', function ( assert ) {
	this.storage.utterances[ 0 ].tokens = [
		{
			startTime: 0,
			endTime: 1000
		},
		{
			startTime: 1000,
			endTime: 2000
		}
	];
	this.player.currentUtterance = this.storage.utterances[ 0 ];

	sinon.stub( this.player, 'getCurrentToken' ).returns(
		this.storage.utterances[ 0 ].tokens[ 0 ]
	);

	this.storage.getNextToken.returns( this.storage.utterances[ 0 ].tokens[ 1 ] );

	this.player.skipAheadToken();

	assert.strictEqual(
		this.storage.utterances[ 0 ].audio.currentTime,
		1.0
	);
	sinon.assert.calledWith(
		this.highlighter.startTokenHighlighting,
		this.storage.utterances[ 0 ].tokens[ 1 ]
	);
} );

QUnit.test( 'skipAheadToken(): skip ahead utterance when last token', function () {
	this.storage.utterances[ 0 ].tokens = [
		{
			startTime: 0,
			endTime: 1000
		}
	];
	this.player.currentUtterance = this.storage.utterances[ 0 ];
	this.storage.utterances[ 0 ].audio.currentTime = 0.1;
	sinon.stub( this.player, 'skipAheadUtterance' );

	this.player.skipAheadToken();

	sinon.assert.called( this.player.skipAheadUtterance );
} );

QUnit.test( 'skipBackToken()', function ( assert ) {
	this.storage.utterances[ 0 ].tokens = [
		{
			startTime: 0,
			endTime: 1000
		},
		{
			startTime: 1000,
			endTime: 2000
		}
	];
	this.player.currentUtterance = this.storage.utterances[ 0 ];
	this.storage.utterances[ 0 ].audio.currentTime = 1.1;
	this.storage.getPreviousToken.returns(
		this.storage.utterances[ 0 ].tokens[ 0 ]
	);

	this.player.skipBackToken();

	assert.strictEqual(
		this.storage.utterances[ 0 ].audio.currentTime,
		0
	);
	sinon.assert.calledWith(
		this.highlighter.startTokenHighlighting,
		this.storage.utterances[ 0 ].tokens[ 0 ]
	);
} );

QUnit.test( 'skipBackToken(): skip to last token in previous utterance if first token', function ( assert ) {
	const previousUtterance = this.storage.utterances[ 0 ];
	previousUtterance.tokens = [
		{
			startTime: 0,
			endTime: 1000
		},
		{
			startTime: 1000,
			endTime: 2000
		}
	];
	const currentUtterance = this.storage.utterances[ 1 ];
	currentUtterance.tokens = [
		{
			startTime: 0,
			endTime: 1000
		}
	];
	this.player.currentUtterance = currentUtterance;
	this.storage.getPreviousToken.returns( null );
	this.storage.getLastToken.returns( previousUtterance.tokens[ 1 ] );
	// Custom mocking since currentUtterance needs
	// to change during the skipBackToken.
	const player = this.player;
	this.player.skipBackUtterance = function () {
		player.currentUtterance = previousUtterance;
	};
	sinon.spy( this.player, 'skipBackUtterance' );

	this.player.skipBackToken();

	sinon.assert.calledOnce( this.player.skipBackUtterance );
	assert.strictEqual( previousUtterance.audio.currentTime, 1.0 );
} );
