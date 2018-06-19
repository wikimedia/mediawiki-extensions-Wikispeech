( function ( mw, $ ) {
	var player, storage, selectionPlayer, highlighter, ui;

	QUnit.module( 'ext.wikispeech.player', {
		setup: function () {
			mw.wikispeech.highlighter =
				sinon.stub( new mw.wikispeech.Highlighter() );
			highlighter = mw.wikispeech.highlighter;
			mw.wikispeech.ui =
				sinon.stub( new mw.wikispeech.Ui() );
			ui = mw.wikispeech.ui;
			mw.wikispeech.selectionPlayer =
				sinon.stub( new mw.wikispeech.SelectionPlayer() );
			selectionPlayer = mw.wikispeech.selectionPlayer;
			mw.wikispeech.storage =
				sinon.stub( new mw.wikispeech.Storage() );
			storage = mw.wikispeech.storage;
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
			player = new mw.wikispeech.Player();
			mw.config.set(
				'wgWikispeechSkipBackRewindsThreshold',
				3.0
			);
		}
	} );

	QUnit.test( 'playOrStop(): play', function ( assert ) {
		assert.expect( 1 );
		sinon.spy( player, 'play' );

		player.playOrStop();

		assert.strictEqual( player.play.called, true );
	} );

	QUnit.test( 'playOrStop(): stop', function ( assert ) {
		assert.expect( 1 );
		player.play();
		sinon.spy( player, 'stop' );

		player.playOrStop();

		assert.strictEqual( player.stop.called, true );
	} );

	QUnit.test( 'stop()', function ( assert ) {
		assert.expect( 3 );
		player.play();
		storage.utterances[ 0 ].audio.currentTime = 1.0;
		sinon.spy( player, 'stopUtterance' );

		player.stop();

		sinon.assert.calledWith(
			player.stopUtterance, storage.utterances[ 0 ]
		);
		sinon.assert.called( ui.setPlayStopIconToPlay );
		sinon.assert.called( ui.hideBufferingIcon );
	} );

	QUnit.test( 'play()', function ( assert ) {
		assert.expect( 2 );
		selectionPlayer.playSelectionIfValid.returns( false );
		sinon.spy( storage.utterances[ 0 ].audio, 'play' );

		player.play();

		sinon.assert.called( storage.utterances[ 0 ].audio.play );
		sinon.assert.called( ui.setPlayStopIconToStop );
	} );

	QUnit.test( 'play(): do not play utterance when selection is valid', function ( assert ) {
		assert.expect( 1 );
		sinon.spy( player, 'playUtterance' );
		selectionPlayer.playSelectionIfValid.returns( true );

		player.play();

		sinon.assert.notCalled( player.playUtterance );
	} );

	QUnit.test( 'play(): play from beginning when selection is invalid', function ( assert ) {
		assert.expect( 1 );
		sinon.spy( player, 'playUtterance' );
		selectionPlayer.playSelectionIfValid.returns( false );

		player.play();

		sinon.assert.calledWith(
			player.playUtterance,
			storage.utterances[ 0 ]
		);
	} );

	QUnit.test( 'playUtterance()', function ( assert ) {
		assert.expect( 3 );
		sinon.spy( storage.utterances[ 0 ].audio, 'play' );

		player.playUtterance( storage.utterances[ 0 ] );

		sinon.assert.called( storage.utterances[ 0 ].audio.play );
		sinon.assert.calledWith(
			highlighter.highlightUtterance,
			storage.utterances[ 0 ]
		);
		sinon.assert.calledWith(
			ui.showBufferingIconIfAudioIsLoading,
			storage.utterances[ 0 ].audio
		);
	} );

	QUnit.test( 'playUtterance(): stop playing utterance', function ( assert ) {
		assert.expect( 1 );
		player.playUtterance( storage.utterances[ 0 ] );
		sinon.spy( player, 'stopUtterance' );

		player.playUtterance( storage.utterances[ 1 ] );

		sinon.assert.calledWith(
			player.stopUtterance,
			storage.utterances[ 0 ]
		);
	} );

	QUnit.test( 'stopUtterance()', function ( assert ) {
		assert.expect( 5 );
		player.playUtterance( storage.utterances[ 0 ] );
		storage.utterances[ 0 ].audio.currentTime = 1.0;
		sinon.spy( storage.utterances[ 0 ].audio, 'pause' );

		player.stopUtterance( storage.utterances[ 0 ] );

		sinon.assert.called( storage.utterances[ 0 ].audio.pause );
		assert.strictEqual( storage.utterances[ 0 ].audio.currentTime, 0.0 );
		sinon.assert.called( highlighter.clearHighlighting );
		sinon.assert.calledWith(
			ui.removeCanPlayListener,
			$( storage.utterances[ 0 ].audio )
		);
		sinon.assert.called( selectionPlayer.resetPreviousEndUtterance );
	} );

	QUnit.test( 'skipAheadUtterance()', function ( assert ) {
		assert.expect( 1 );
		sinon.spy( player, 'playUtterance' );
		storage.getNextUtterance.returns( storage.utterances[ 1 ] );

		player.skipAheadUtterance();

		sinon.assert.calledWith(
			player.playUtterance,
			storage.utterances[ 1 ]
		);
	} );

	QUnit.test( 'skipAheadUtterance(): stop if no next utterance', function ( assert ) {
		assert.expect( 1 );
		sinon.spy( player, 'stop' );
		storage.getNextUtterance.returns( null );

		player.skipAheadUtterance();

		sinon.assert.called( player.stop );
	} );

	QUnit.test( 'skipBackUtterance()', function ( assert ) {
		assert.expect( 1 );
		sinon.spy( player, 'playUtterance' );
		player.playUtterance( storage.utterances[ 1 ] );
		storage.getPreviousUtterance.returns( storage.utterances[ 0 ] );

		player.skipBackUtterance();

		sinon.assert.calledWith(
			player.playUtterance,
			storage.utterances[ 0 ]
		);
	} );

	QUnit.test( 'skipBackUtterance(): restart if first utterance', function ( assert ) {
		assert.expect( 2 );
		player.playUtterance( storage.utterances[ 0 ] );
		storage.utterances[ 0 ].audio.currentTime = 1.0;
		sinon.spy( storage.utterances[ 0 ].audio, 'pause' );

		player.skipBackUtterance();

		assert.strictEqual(
			storage.utterances[ 0 ].audio.currentTime,
			0.0
		);
		sinon.assert.notCalled( storage.utterances[ 0 ].audio.pause );
	} );

	QUnit.test( 'skipBackUtterance(): restart if played long enough', function ( assert ) {
		assert.expect( 2 );
		player.playUtterance( storage.utterances[ 1 ] );
		storage.utterances[ 1 ].audio.currentTime = 3.1;
		sinon.spy( player, 'playUtterance' );
		storage.getPreviousUtterance.returns( storage.utterances[ 0 ] );

		player.skipBackUtterance();

		assert.strictEqual(
			storage.utterances[ 1 ].audio.currentTime,
			0.0
		);
		sinon.assert.neverCalledWith(
			player.playUtterance, storage.utterances[ 0 ]
		);
	} );

	QUnit.test( 'getCurrentToken()', function ( assert ) {
		var token;

		assert.expect( 1 );
		storage.utterances[ 0 ].audio.src = 'loaded';
		storage.utterances[ 0 ].tokens = [
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
		storage.utterances[ 0 ].audio.currentTime = 1.1;
		player.play();

		token = player.getCurrentToken();

		assert.strictEqual( token, storage.utterances[ 0 ].tokens[ 1 ] );
	} );

	QUnit.test( 'getCurrentToken(): get first token', function ( assert ) {
		var token;

		assert.expect( 1 );
		storage.utterances[ 0 ].audio.src = 'loaded';
		storage.utterances[ 0 ].tokens = [
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
		storage.utterances[ 0 ].audio.currentTime = 0.1;
		player.play();

		token = player.getCurrentToken();

		assert.strictEqual( token, storage.utterances[ 0 ].tokens[ 0 ] );
	} );

	QUnit.test( 'getCurrentToken(): get the last token', function ( assert ) {
		var token;

		assert.expect( 1 );
		storage.utterances[ 0 ].audio.src = 'loaded';
		storage.utterances[ 0 ].tokens = [
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
		storage.utterances[ 0 ].audio.currentTime = 2.1;
		player.play();

		token = player.getCurrentToken();

		assert.strictEqual( token, storage.utterances[ 0 ].tokens[ 2 ] );
	} );

	QUnit.test( 'getCurrentToken(): get the last token when current time is equal to last tokens end time', function ( assert ) {
		var token;

		assert.expect( 1 );
		storage.utterances[ 0 ].audio.src = 'loaded';
		storage.utterances[ 0 ].tokens = [
			{
				startTime: 0.0,
				endTime: 1.0
			},
			{
				startTime: 1.0,
				endTime: 2.0
			}
		];
		storage.utterances[ 0 ].audio.currentTime = 2.0;
		player.play();

		token = player.getCurrentToken();

		assert.strictEqual( token, storage.utterances[ 0 ].tokens[ 1 ] );
	} );

	QUnit.test( 'getCurrentToken(): ignore tokens with no duration', function ( assert ) {
		var token;

		assert.expect( 1 );
		storage.utterances[ 0 ].audio.src = 'loaded';
		storage.utterances[ 0 ].tokens = [
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
		storage.utterances[ 0 ].audio.currentTime = 1.0;
		player.play();

		token = player.getCurrentToken();

		assert.strictEqual(
			token,
			storage.utterances[ 0 ].tokens[ 2 ]
		);
	} );

	QUnit.test( 'getCurrentToken(): give correct token if there are tokens with no duration', function ( assert ) {
		var token;

		assert.expect( 1 );
		storage.utterances[ 0 ].audio.src = 'loaded';
		storage.utterances[ 0 ].tokens = [
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
		storage.utterances[ 0 ].audio.currentTime = 1.1;
		player.play();

		token = player.getCurrentToken();

		assert.strictEqual( token, storage.utterances[ 0 ].tokens[ 2 ] );
	} );

	QUnit.test( 'skipAheadToken()', function ( assert ) {
		assert.expect( 2 );
		storage.utterances[ 0 ].tokens = [
			{
				startTime: 0.0,
				endTime: 1.0
			},
			{
				startTime: 1.0,
				endTime: 2.0
			}
		];
		player.playUtterance( storage.utterances[ 0 ] );
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

	QUnit.test( 'skipAheadToken(): skip ahead utterance when last token', function ( assert ) {
		assert.expect( 1 );
		storage.utterances[ 0 ].tokens = [
			{
				startTime: 0.0,
				endTime: 1.0
			}
		];
		player.play();
		storage.utterances[ 0 ].audio.currentTime = 0.1;
		sinon.spy( player, 'skipAheadUtterance' );

		player.skipAheadToken();

		sinon.assert.called( player.skipAheadUtterance );
	} );

	QUnit.test( 'skipBackToken()', function ( assert ) {
		assert.expect( 2 );
		storage.utterances[ 0 ].tokens = [
			{
				startTime: 0.0,
				endTime: 1.0
			},
			{
				startTime: 1.0,
				endTime: 2.0
			}
		];
		player.playUtterance( storage.utterances[ 0 ] );
		storage.utterances[ 0 ].audio.currentTime = 1.1;
		storage.getPreviousToken.returns(
			storage.utterances[ 0 ].tokens[ 0 ]
		);

		player.skipBackToken();

		assert.strictEqual(
			storage.utterances[ 0 ].audio.currentTime,
			0.0
		);
		sinon.assert.calledWith(
			highlighter.startTokenHighlighting,
			storage.utterances[ 0 ].tokens[ 0 ]
		);
	} );

	QUnit.test( 'skipBackToken(): skip to last token in previous utterance if first token', function ( assert ) {
		assert.expect( 2 );
		storage.utterances[ 0 ].tokens = [
			{
				startTime: 0.0,
				endTime: 1.0
			},
			{
				startTime: 1.0,
				endTime: 2.0
			}
		];
		storage.utterances[ 1 ].tokens = [
			{
				startTime: 0.0,
				endTime: 1.0
			}
		];
		player.playUtterance( storage.utterances[ 1 ] );
		storage.getPreviousUtterance.returns( storage.utterances[ 0 ] );
		storage.getPreviousToken.returns( null );
		storage.getLastToken.returns( storage.utterances[ 0 ].tokens[ 1 ] );
		sinon.spy( player, 'skipBackUtterance' );

		player.skipBackToken();

		sinon.assert.calledOnce( player.skipBackUtterance );
		assert.strictEqual(
			storage.utterances[ 0 ].audio.currentTime,
			1.0
		);
	} );
}( mediaWiki, jQuery ) );
