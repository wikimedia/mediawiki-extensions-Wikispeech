const Player = require( 'ext.wikispeech/ext.wikispeech.player.js' );
const Highlighter = require( 'ext.wikispeech/ext.wikispeech.highlighter.js' );
const Ui = require( 'ext.wikispeech/ext.wikispeech.ui.js' );
const SelectionPlayer = require( 'ext.wikispeech/ext.wikispeech.selectionPlayer.js' );
const Storage = require( 'ext.wikispeech/ext.wikispeech.storage.js' );

let player, storage, selectionPlayer, highlighter, ui;

QUnit.module( 'ext.wikispeech.player', {
	beforeEach: function () {
		player = new Player();
		highlighter = sinon.stub( new Highlighter() );
		player.highlighter = highlighter;
		ui = sinon.stub( new Ui() );
		player.ui = ui;
		selectionPlayer = sinon.stub( new SelectionPlayer() );
		player.selectionPlayer = selectionPlayer;
		storage = sinon.stub( new Storage() );
		player.storage = storage;
		storage.utterancesLoaded.resolve();
		storage.utterances = [
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
		storage.prepareUtterance = sinon.stub().returns( $.Deferred().resolve() );

		mw.config.set(
			'wgWikispeechSkipBackRewindsThreshold',
			3.0
		);
		// Base case is that there is no selection. Test that test
		// for when there is a selection overwrites this.
		selectionPlayer.playSelectionIfValid.returns( false );
	}
} );

QUnit.test( 'playOrPause(): play', ( assert ) => {
	sinon.stub( player, 'play' );

	player.playOrPause();

	assert.strictEqual( player.play.called, true );
} );

QUnit.test( 'playOrPause(): pause', ( assert ) => {
	player.currentUtterance = storage.utterances[ 0 ];
	sinon.stub( player, 'pause' );

	player.playOrPause();

	assert.strictEqual( player.pause.called, true );
} );

QUnit.test( 'stop()', () => {
	player.currentUtterance = storage.utterances[ 0 ];
	storage.utterances[ 0 ].audio.currentTime = 1.0;
	sinon.stub( player, 'stopUtterance' );

	player.stop();

	sinon.assert.calledWith(
		player.stopUtterance, storage.utterances[ 0 ]
	);
	sinon.assert.called( ui.hideBufferingIcon );
} );

QUnit.test( 'play()', () => {
	sinon.stub( player, 'playUtterance' );

	player.play();

	sinon.assert.called( player.playUtterance );
} );

QUnit.test( 'pause()', () => {
	sinon.stub( player, 'pauseUtterance' );

	player.currentUtterance = storage.utterances[ 0 ];
	player.paused = false;
	player.pause();

	sinon.assert.calledWith( player.pauseUtterance, storage.utterances[ 0 ] );
} );

QUnit.test( 'play(): delay until utterances has been loaded', () => {
	sinon.stub( player, 'playUtterance' );
	// We want an unresolved promise for this test.
	storage.utterancesLoaded = $.Deferred();

	player.play();

	sinon.assert.notCalled( player.playUtterance );
} );

QUnit.test( 'play(): do not play utterance when selection is valid', () => {
	sinon.stub( player, 'playUtterance' );
	selectionPlayer.playSelectionIfValid.returns( true );

	player.play();

	sinon.assert.notCalled( player.playUtterance );
} );

QUnit.test( 'play(): play from beginning when selection is invalid', () => {
	sinon.stub( player, 'playUtterance' );
	selectionPlayer.playSelectionIfValid.returns( false );

	player.play();

	sinon.assert.calledWith(
		player.playUtterance,
		storage.utterances[ 0 ]
	);
} );

QUnit.test( 'playUtterance()', () => {
	const utterance = storage.utterances[ 0 ];
	sinon.stub( utterance.audio, 'play' );
	storage.prepareUtterance.returns( $.Deferred().resolve() );

	player.playUtterance( utterance );

	sinon.assert.called( utterance.audio.play );
	sinon.assert.calledWith( highlighter.highlightUtterance, utterance );
	sinon.assert.calledWith(
		ui.showBufferingIconIfAudioIsLoading,
		utterance.audio
	);
} );

QUnit.test( 'playUtterance(): stop playing utterance', () => {
	storage.prepareUtterance.returns( $.Deferred().resolve() );
	player.currentUtterance = storage.utterances[ 0 ];
	sinon.stub( player, 'stopUtterance' );

	player.playUtterance( storage.utterances[ 1 ] );

	sinon.assert.calledWith(
		player.stopUtterance,
		storage.utterances[ 0 ]
	);
} );

QUnit.test( 'playUtterance(): show load error dialog', () => {
	const utterance = storage.utterances[ 0 ];
	storage.prepareUtterance.returns( $.Deferred().reject() );
	ui.showLoadAudioError.returns( $.Deferred() );

	player.playUtterance( utterance );

	sinon.assert.called( ui.showLoadAudioError );
} );

QUnit.test( 'playUtterance(): show load error dialog again', () => {
	const utterance = storage.utterances[ 0 ];
	storage.prepareUtterance.returns( $.Deferred().reject() );
	ui.showLoadAudioError.onFirstCall().returns( $.Deferred().resolveWith( null, [ { action: 'retry' } ] ) );
	ui.showLoadAudioError.returns( $.Deferred() );

	player.playUtterance( utterance );

	sinon.assert.calledTwice( ui.showLoadAudioError );
} );

QUnit.test( 'playUtterance(): retry preparing utterance', ( assert ) => {
	const utterance = storage.utterances[ 0 ];
	storage.prepareUtterance.returns( $.Deferred().reject() );
	ui.showLoadAudioError.onFirstCall().returns( $.Deferred().resolveWith( null, [ { action: 'retry' } ] ) );
	ui.showLoadAudioError.returns( $.Deferred().resolve() );

	player.playUtterance( utterance );

	assert.true( storage.prepareUtterance.firstCall.calledWithExactly( utterance ) );
	assert.true( storage.prepareUtterance.secondCall.calledWithExactly( utterance ) );
} );

QUnit.test( 'stopUtterance()', ( assert ) => {
	storage.utterances[ 0 ].audio.currentTime = 1.0;
	sinon.stub( storage.utterances[ 0 ].audio, 'pause' );

	player.stopUtterance( storage.utterances[ 0 ] );

	sinon.assert.called( storage.utterances[ 0 ].audio.pause );
	assert.strictEqual( storage.utterances[ 0 ].audio.currentTime, 0 );
	sinon.assert.called( highlighter.clearHighlighting );
	sinon.assert.calledWith(
		ui.removeCanPlayListener,
		$( storage.utterances[ 0 ].audio )
	);
} );

QUnit.test( 'skipAheadUtterance()', () => {
	sinon.stub( player, 'playUtterance' );
	storage.getNextUtterance.returns( storage.utterances[ 1 ] );

	player.skipAheadUtterance();

	sinon.assert.calledWith(
		player.playUtterance,
		storage.utterances[ 1 ]
	);
} );

QUnit.test( 'skipAheadUtterance(): stop if no next utterance', () => {
	sinon.stub( player, 'stop' );
	storage.getNextUtterance.returns( null );

	player.skipAheadUtterance();

	sinon.assert.called( player.stop );
} );

QUnit.test( "skipAheadUtterance(): don't play prepared utterance when paused", ( assert ) => {
	storage.getNextUtterance.returns( storage.utterances[ 1 ] );
	const utterance = storage.utterances[ 1 ];
	sinon.stub( utterance.audio, 'play' );
	player.paused = true;
	const promise = $.Deferred().resolve();
	storage.prepareUtterance.returns( promise );
	const done = assert.async();

	player.skipAheadUtterance();

	promise.then( () => {
		sinon.assert.notCalled( utterance.audio.play );
		done();
	} );
} );

QUnit.test( 'skipBackUtterance()', () => {
	sinon.stub( player, 'playUtterance' );
	player.currentUtterance = storage.utterances[ 1 ];
	storage.getPreviousUtterance.returns( storage.utterances[ 0 ] );

	player.skipBackUtterance();

	sinon.assert.calledWith(
		player.playUtterance,
		storage.utterances[ 0 ]
	);
} );

QUnit.test( 'skipBackUtterance(): restart if first utterance', ( assert ) => {
	player.currentUtterance = storage.utterances[ 0 ];
	storage.utterances[ 0 ].audio.currentTime = 1.0;
	sinon.stub( storage.utterances[ 0 ].audio, 'pause' );
	player.skipBackUtterance();
	assert.strictEqual(
		storage.utterances[ 0 ].audio.currentTime,
		0
	);
	sinon.assert.notCalled( storage.utterances[ 0 ].audio.pause );
} );

QUnit.test( 'skipBackUtterance(): restart if played long enough', ( assert ) => {
	player.currentUtterance = storage.utterances[ 1 ];
	storage.utterances[ 1 ].audio.currentTime = 3.1;
	sinon.stub( player, 'playUtterance' );
	storage.getPreviousUtterance.returns( storage.utterances[ 0 ] );

	player.skipBackUtterance();

	assert.strictEqual(
		storage.utterances[ 1 ].audio.currentTime,
		0
	);
	sinon.assert.neverCalledWith(
		player.playUtterance, storage.utterances[ 0 ]
	);
} );

QUnit.test( 'getCurrentToken()', ( assert ) => {
	storage.utterances[ 0 ].audio.src = 'loaded';
	storage.utterances[ 0 ].tokens = [
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
	storage.utterances[ 0 ].audio.currentTime = 1.1;
	storage.utterancesLoaded.resolve();
	player.currentUtterance = storage.utterances[ 0 ];

	const token = player.getCurrentToken();

	assert.strictEqual( token, storage.utterances[ 0 ].tokens[ 1 ] );
} );

QUnit.test( 'getCurrentToken(): get first token', ( assert ) => {
	storage.utterances[ 0 ].audio.src = 'loaded';
	storage.utterances[ 0 ].tokens = [
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
	storage.utterances[ 0 ].audio.currentTime = 0.1;
	player.currentUtterance = storage.utterances[ 0 ];

	const token = player.getCurrentToken();

	assert.strictEqual( token, storage.utterances[ 0 ].tokens[ 0 ] );
} );

QUnit.test( 'getCurrentToken(): get the last token', ( assert ) => {
	storage.utterances[ 0 ].audio.src = 'loaded';
	storage.utterances[ 0 ].tokens = [
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
	storage.utterances[ 0 ].audio.currentTime = 2.1;
	player.currentUtterance = storage.utterances[ 0 ];

	const token = player.getCurrentToken();

	assert.strictEqual( token, storage.utterances[ 0 ].tokens[ 2 ] );
} );

QUnit.test( 'getCurrentToken(): get the last token when current time is equal to last tokens end time', ( assert ) => {
	storage.utterances[ 0 ].audio.src = 'loaded';
	storage.utterances[ 0 ].tokens = [
		{
			startTime: 0,
			endTime: 1000
		},
		{
			startTime: 1000,
			endTime: 2000
		}
	];
	storage.utterances[ 0 ].audio.currentTime = 2.0;
	player.currentUtterance = storage.utterances[ 0 ];

	const token = player.getCurrentToken();

	assert.strictEqual( token, storage.utterances[ 0 ].tokens[ 1 ] );
} );

QUnit.test( 'getCurrentToken(): ignore tokens with no duration', ( assert ) => {
	storage.utterances[ 0 ].audio.src = 'loaded';
	storage.utterances[ 0 ].tokens = [
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
	storage.utterances[ 0 ].audio.currentTime = 1.0;
	player.currentUtterance = storage.utterances[ 0 ];

	const token = player.getCurrentToken();

	assert.strictEqual(
		token,
		storage.utterances[ 0 ].tokens[ 2 ]
	);
} );

QUnit.test( 'getCurrentToken(): give correct token if there are tokens with no duration', ( assert ) => {
	storage.utterances[ 0 ].audio.src = 'loaded';
	storage.utterances[ 0 ].tokens = [
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
	storage.utterances[ 0 ].audio.currentTime = 1.1;
	player.currentUtterance = storage.utterances[ 0 ];

	const token = player.getCurrentToken();

	assert.strictEqual( token, storage.utterances[ 0 ].tokens[ 2 ] );
} );

QUnit.test( 'skipAheadToken()', ( assert ) => {
	storage.utterances[ 0 ].tokens = [
		{
			startTime: 0,
			endTime: 1000
		},
		{
			startTime: 1000,
			endTime: 2000
		}
	];
	player.currentUtterance = storage.utterances[ 0 ];

	sinon.stub( player, 'getCurrentToken' ).returns(
		storage.utterances[ 0 ].tokens[ 0 ]
	);

	storage.getNextToken.returns( storage.utterances[ 0 ].tokens[ 1 ] );

	player.skipAheadToken();

	assert.strictEqual(
		storage.utterances[ 0 ].audio.currentTime,
		1.0
	);
	sinon.assert.calledWith(
		highlighter.startTokenHighlighting,
		storage.utterances[ 0 ].tokens[ 1 ]
	);
} );

QUnit.test( 'skipAheadToken(): skip ahead utterance when last token', () => {
	storage.utterances[ 0 ].tokens = [
		{
			startTime: 0,
			endTime: 1000
		}
	];
	player.currentUtterance = storage.utterances[ 0 ];
	storage.utterances[ 0 ].audio.currentTime = 0.1;
	sinon.stub( player, 'skipAheadUtterance' );

	player.skipAheadToken();

	sinon.assert.called( player.skipAheadUtterance );
} );

QUnit.test( 'skipBackToken()', ( assert ) => {
	storage.utterances[ 0 ].tokens = [
		{
			startTime: 0,
			endTime: 1000
		},
		{
			startTime: 1000,
			endTime: 2000
		}
	];
	player.currentUtterance = storage.utterances[ 0 ];
	storage.utterances[ 0 ].audio.currentTime = 1.1;
	storage.getPreviousToken.returns(
		storage.utterances[ 0 ].tokens[ 0 ]
	);

	player.skipBackToken();

	assert.strictEqual(
		storage.utterances[ 0 ].audio.currentTime,
		0
	);
	sinon.assert.calledWith(
		highlighter.startTokenHighlighting,
		storage.utterances[ 0 ].tokens[ 0 ]
	);
} );

QUnit.test( 'skipBackToken(): skip to last token in previous utterance if first token', ( assert ) => {
	const previousUtterance = storage.utterances[ 0 ];
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
	const currentUtterance = storage.utterances[ 1 ];
	currentUtterance.tokens = [
		{
			startTime: 0,
			endTime: 1000
		}
	];
	player.currentUtterance = currentUtterance;
	storage.getPreviousToken.returns( null );
	storage.getLastToken.returns( previousUtterance.tokens[ 1 ] );
	// Custom mocking since currentUtterance needs
	// to change during the skipBackToken.
	player.skipBackUtterance = function () {
		player.currentUtterance = previousUtterance;
	};
	sinon.spy( player, 'skipBackUtterance' );

	player.skipBackToken();

	sinon.assert.calledOnce( player.skipBackUtterance );
	assert.strictEqual( previousUtterance.audio.currentTime, 1.0 );
} );
