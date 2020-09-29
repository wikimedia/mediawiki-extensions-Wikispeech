( function ( mw, $ ) {
	var server, storage, player, util, contentSelector;

	QUnit.module( 'ext.wikispeech.storage', {
		setup: function () {
			util = mw.wikispeech.test.util;
			mw.wikispeech.player = {
				skipAheadUtterance: sinon.spy(),
				stop: sinon.spy()
			};
			player = mw.wikispeech.player;
			storage = new mw.wikispeech.Storage();
			server = sinon.fakeServer.create( {
				respondImmediately: true
			} );
			// overrideMimeType() isn't defined by default.
			server.xhr.prototype.overrideMimeType = function () {};
			$( '#qunit-fixture' ).append(
				$( '<div></div>' ).attr( 'id', 'content' )
			);
			mw.config.set( 'wgWikispeechContentSelector', '#mw-content-text' );
			contentSelector =
				mw.config.get( 'wgWikispeechContentSelector' );
			storage.utterances = [
				{
					audio: $( '<audio></audio>' ).get( 0 ),
					startOffset: 0,
					content: [ { string: 'Utterance zero.' } ]
				},
				{
					audio: $( '<audio></audio>' ).get( 0 ),
					content: [ { string: 'Utterance one.' } ]
				}
			];
		},
		teardown: function () {
			server.restore();
			mw.user.options.set( 'wikispeechVoiceEn', '' );
			mw.user.options.set( 'wikispeechSpeechRate', 1.0 );
		}
	} );

	QUnit.test( 'loadUtterances()', function ( assert ) {
		var response, expectedUtterances;
		assert.expect( 2 );

		sinon.stub( storage, 'prepareUtterance' );
		mw.config.set( 'wgPageName', 'Page' );
		response = {
			'wikispeech-segment': {
				segments: [ {
					startOffset: 0,
					endOffset: 3,
					content: [ {
						string: 'Page',
						path: 'path'
					} ],
					hash: 'hash1234'
				} ]
			}
		};
		server.respondWith( JSON.stringify( response ) );

		storage.loadUtterances();

		assert.strictEqual(
			decodeURIComponent( server.requests[ 0 ].requestBody ),
			'action=wikispeech-segment&format=json&page=Page'
		);
		expectedUtterances = [ {
			startOffset: 0,
			endOffset: 3,
			content: [ {
				string: 'Page',
				path: 'path'
			} ],
			hash: 'hash1234',
			audio: $( '<audio></audio>' ).get( 0 )
		} ];
		assert.deepEqual(
			storage.utterances,
			expectedUtterances
		);
	} );

	QUnit.test( 'prepareUtterance()', function ( assert ) {
		assert.expect( 1 );
		sinon.stub( storage, 'loadAudio' );

		storage.prepareUtterance( storage.utterances[ 0 ] );

		sinon.assert.calledWith(
			storage.loadAudio, storage.utterances[ 0 ]
		);
	} );

	QUnit.test( 'prepareUtterance(): do not request if waiting for response', function ( assert ) {
		assert.expect( 1 );
		sinon.spy( storage, 'loadAudio' );
		storage.utterances[ 0 ].request = { done: function () {} };

		storage.prepareUtterance( storage.utterances[ 0 ] );

		sinon.assert.notCalled( storage.loadAudio );

	} );

	QUnit.test( 'prepareUtterance(): do not load audio if already loaded', function ( assert ) {
		assert.expect( 1 );
		storage.utterances[ 0 ].audio.setAttribute(
			'src',
			'DummyBase64Audio='
		);
		sinon.spy( storage, 'loadAudio' );

		storage.prepareUtterance( storage.utterances[ 0 ] );

		sinon.assert.notCalled( storage.loadAudio );
	} );

	QUnit.test( 'prepareUtterance(): prepare next utterance when playing', function ( assert ) {
		assert.expect( 1 );
		sinon.stub( storage, 'loadAudio' );
		sinon.spy( storage, 'prepareUtterance' );
		storage.prepareUtterance( storage.utterances[ 0 ] );

		$( storage.utterances[ 0 ].audio ).trigger( 'play' );

		sinon.assert.calledWith(
			storage.prepareUtterance, storage.utterances[ 1 ]
		);
	} );

	QUnit.test( 'prepareUtterance(): do not prepare next audio if it does not exist', function ( assert ) {
		assert.expect( 1 );
		sinon.spy( storage, 'prepareUtterance' );
		storage.prepareUtterance( storage.utterances[ 1 ] );

		$( storage.utterances[ 1 ].audio ).trigger( 'play' );

		sinon.assert.calledOnce( storage.prepareUtterance );
	} );

	QUnit.test( 'prepareUtterance(): skip to next utterance when ended', function ( assert ) {
		assert.expect( 1 );
		storage.prepareUtterance( storage.utterances[ 0 ] );

		$( storage.utterances[ 0 ].audio ).trigger( 'ended' );

		sinon.assert.called( player.skipAheadUtterance );
	} );

	QUnit.test( 'prepareUtterance(): stop when end of text is reached', function ( assert ) {
		var lastUtterance;

		assert.expect( 1 );
		lastUtterance = storage.utterances[ 1 ];
		storage.prepareUtterance( lastUtterance );

		$( lastUtterance.audio ).trigger( 'ended' );

		sinon.assert.called( player.stop );
	} );

	QUnit.test( 'loadAudio()', function ( assert ) {
		assert.expect( 1 );
		mw.config.set( 'wgRevisionId', 1 );
		storage.utterances[ 0 ].hash = 'hash1234';

		storage.loadAudio( storage.utterances[ 0 ] );

		assert.strictEqual(
			server.requests[ 0 ].requestBody,
			'action=wikispeech-listen&format=json&lang=en&revision=1&segment=hash1234'
		);
	} );

	QUnit.test( 'loadAudio(): request successful', function ( assert ) {
		assert.expect( 4 );
		server.respondWith(
			'{"wikispeech-listen": {"audio": "DummyBase64Audio=", "tokens": [{"orth": "Utterance"}, {"orth": "zero"}, {"orth": "."}]}}'
		);
		sinon.stub( storage, 'addTokens' );
		mw.user.options.set( 'wikispeechSpeechRate', 2.0 );

		storage.loadAudio( storage.utterances[ 0 ] );

		assert.strictEqual(
			storage.utterances[ 0 ].audio.src,
			'data:audio/ogg;base64,DummyBase64Audio='
		);
		sinon.assert.calledWith(
			storage.addTokens,
			storage.utterances[ 0 ],
			[ { orth: 'Utterance' }, { orth: 'zero' }, { orth: '.' } ]
		);
		assert.strictEqual( storage.utterances[ 0 ].request, null );
		assert.strictEqual( storage.utterances[ 0 ].audio.playbackRate, 2.0 );
	} );

	QUnit.test( 'loadAudio(): request failed', function ( assert ) {
		assert.expect( 3 );
		storage.utterances[ 0 ].request = { done: function () {} };
		server.respondWith( [ 404, {}, '' ] );
		sinon.spy( storage, 'addTokens' );

		storage.loadAudio( storage.utterances[ 0 ] );

		sinon.assert.notCalled( storage.addTokens );
		assert.strictEqual( storage.utterances[ 0 ].request, null );
		assert.strictEqual( storage.utterances[ 0 ].audio.src, '' );
	} );

	QUnit.test( 'loadAudio(): non-default voice', function ( assert ) {
		assert.expect( 2 );
		sinon.spy( storage, 'requestTts' );
		mw.user.options.set( 'wikispeechVoiceEn', 'en-voice' );
		mw.config.set( 'wgPageContentLanguage', 'en' );
		mw.config.set( 'wgRevisionId', 1 );
		storage.utterances[ 0 ].hash = 'hash1234';

		storage.loadAudio( storage.utterances[ 0 ] );

		sinon.assert.called( storage.requestTts );
		assert.strictEqual(
			server.requests[ 0 ].requestBody,
			'action=wikispeech-listen&format=json&lang=en&revision=1&segment=hash1234&voice=en-voice'
		);
	} );

	QUnit.test( 'getUtteranceByOffset(): after', function ( assert ) {
		var actualUtterance;

		assert.expect( 1 );

		actualUtterance =
			storage.getUtteranceByOffset( storage.utterances[ 0 ], 1 );

		assert.strictEqual( actualUtterance, storage.utterances[ 1 ] );
	} );

	QUnit.test( 'getUtteranceByOffset(): before', function ( assert ) {
		var actualUtterance;

		assert.expect( 1 );

		actualUtterance =
			storage.getUtteranceByOffset( storage.utterances[ 1 ], -1 );

		assert.strictEqual( actualUtterance, storage.utterances[ 0 ] );
	} );

	QUnit.test( 'getUtteranceByOffset(): original utterance is null', function ( assert ) {
		var actualUtterance;

		assert.expect( 1 );

		actualUtterance = storage.getUtteranceByOffset( null, 1 );

		assert.strictEqual( actualUtterance, null );
	} );

	QUnit.test( 'addTokens()', function ( assert ) {
		var tokens;

		assert.expect( 1 );
		util.setContentHtml( 'Utterance zero.' );
		tokens = [
			{
				orth: 'Utterance',
				endtime: 1000
			},
			{
				orth: 'zero',
				endtime: 2000
			},
			{
				orth: '.',
				endtime: 3000
			}
		];

		storage.addTokens( storage.utterances[ 0 ], tokens );

		assert.deepEqual(
			[
				{
					string: 'Utterance',
					utterance: storage.utterances[ 0 ],
					startTime: 0,
					endTime: 1000,
					items: [ storage.utterances[ 0 ].content[ 0 ] ],
					startOffset: 0,
					endOffset: 8
				},
				{
					string: 'zero',
					utterance: storage.utterances[ 0 ],
					startTime: 1000,
					endTime: 2000,
					items: [ storage.utterances[ 0 ].content[ 0 ] ],
					startOffset: 10,
					endOffset: 13
				},
				{
					string: '.',
					utterance: storage.utterances[ 0 ],
					startTime: 2000,
					endTime: 3000,
					items: [ storage.utterances[ 0 ].content[ 0 ] ],
					startOffset: 14,
					endOffset: 14
				}
			],
			storage.utterances[ 0 ].tokens
		);
	} );

	QUnit.test( 'addTokens(): handle tag', function ( assert ) {
		var tokens;

		assert.expect( 12 );
		util.setContentHtml( 'Utterance with <b>tag</b>.' );
		storage.utterances[ 0 ].content[ 0 ].string = 'Utterance with ';
		storage.utterances[ 0 ].content[ 1 ] = { string: 'tag' };
		storage.utterances[ 0 ].content[ 2 ] = { string: '.' };
		tokens = [
			{
				orth: 'Utterance',
				endtime: 1000
			},
			{
				orth: 'with',
				endtime: 2000
			},
			{
				orth: 'tag',
				endtime: 3000
			},
			{
				orth: '.',
				endtime: 4000
			}
		];

		storage.addTokens( storage.utterances[ 0 ], tokens );

		assert.deepEqual(
			storage.utterances[ 0 ].tokens[ 0 ].items,
			[ storage.utterances[ 0 ].content[ 0 ] ]
		);
		assert.strictEqual( storage.utterances[ 0 ].tokens[ 0 ].startOffset, 0 );
		assert.strictEqual( storage.utterances[ 0 ].tokens[ 0 ].endOffset, 8 );
		assert.deepEqual(
			storage.utterances[ 0 ].tokens[ 1 ].items,
			[ storage.utterances[ 0 ].content[ 0 ] ]
		);
		assert.strictEqual( storage.utterances[ 0 ].tokens[ 1 ].startOffset, 10 );
		assert.strictEqual( storage.utterances[ 0 ].tokens[ 1 ].endOffset, 13 );
		assert.deepEqual(
			storage.utterances[ 0 ].tokens[ 2 ].items,
			[ storage.utterances[ 0 ].content[ 1 ] ]
		);
		assert.strictEqual( storage.utterances[ 0 ].tokens[ 2 ].startOffset, 0 );
		assert.strictEqual( storage.utterances[ 0 ].tokens[ 2 ].endOffset, 2 );
		assert.deepEqual(
			storage.utterances[ 0 ].tokens[ 3 ].items,
			[ storage.utterances[ 0 ].content[ 2 ] ]
		);
		assert.strictEqual( storage.utterances[ 0 ].tokens[ 3 ].startOffset, 0 );
		assert.strictEqual( storage.utterances[ 0 ].tokens[ 3 ].endOffset, 0 );
	} );

	QUnit.test( 'addTokens(): handle removed element', function ( assert ) {
		var tokens;

		assert.expect( 3 );
		util.setContentHtml(
			'Utterance with <del>removed tag</del>.'
		);
		storage.utterances[ 0 ].content[ 0 ].string = 'Utterance with ';
		storage.utterances[ 0 ].content[ 1 ] = { string: '.' };
		tokens = [
			{
				orth: 'Utterance',
				endtime: 1000
			},
			{
				orth: 'with',
				endtime: 2000
			},
			{
				orth: '.',
				endtime: 3000
			}
		];

		storage.addTokens( storage.utterances[ 0 ], tokens );

		assert.deepEqual(
			storage.utterances[ 0 ].tokens[ 2 ].items,
			[ storage.utterances[ 0 ].content[ 1 ] ]
		);
		assert.strictEqual( storage.utterances[ 0 ].tokens[ 2 ].startOffset, 0 );
		assert.strictEqual( storage.utterances[ 0 ].tokens[ 2 ].endOffset, 0 );
	} );

	QUnit.test( 'addTokens(): divided tokens', function ( assert ) {
		var tokens;

		assert.expect( 3 );
		util.setContentHtml(
			'Utterance with divided to<b>k</b>en.'
		);
		storage.utterances[ 0 ].content[ 0 ].string = 'Utterance with divided to';
		storage.utterances[ 0 ].content[ 1 ] = { string: 'k' };
		storage.utterances[ 0 ].content[ 2 ] = { string: 'en.' };
		tokens = [
			{ orth: 'Utterance' },
			{ orth: 'with' },
			{ orth: 'divided' },
			{ orth: 'token' },
			{ orth: '.' }
		];

		storage.addTokens( storage.utterances[ 0 ], tokens );

		assert.deepEqual(
			storage.utterances[ 0 ].tokens[ 3 ].items,
			[
				storage.utterances[ 0 ].content[ 0 ],
				storage.utterances[ 0 ].content[ 1 ],
				storage.utterances[ 0 ].content[ 2 ]
			]
		);
		assert.strictEqual( storage.utterances[ 0 ].tokens[ 3 ].startOffset, 23 );
		assert.strictEqual( storage.utterances[ 0 ].tokens[ 3 ].endOffset, 1 );
	} );

	QUnit.test( 'addTokens(): ambiguous tokens', function ( assert ) {
		var tokens;

		assert.expect( 4 );
		util.setContentHtml( 'A word and the same word.' );
		storage.utterances[ 0 ].content[ 0 ].string = 'A word and the same word.';
		tokens = [
			{ orth: 'A' },
			{ orth: 'word' },
			{ orth: 'and' },
			{ orth: 'the' },
			{ orth: 'same' },
			{ orth: 'word' },
			{ orth: '.' }
		];

		storage.addTokens( storage.utterances[ 0 ], tokens );

		assert.deepEqual( storage.utterances[ 0 ].tokens[ 1 ].startOffset, 2 );
		assert.deepEqual( storage.utterances[ 0 ].tokens[ 1 ].endOffset, 5 );
		assert.deepEqual( storage.utterances[ 0 ].tokens[ 5 ].startOffset, 20 );
		assert.deepEqual( storage.utterances[ 0 ].tokens[ 5 ].endOffset, 23 );
	} );

	QUnit.test( 'addTokens(): ambiguous tokens in tag', function ( assert ) {
		var tokens;

		assert.expect( 2 );
		util.setContentHtml(
			'Utterance with <b>word and word</b>.'
		);
		storage.utterances[ 0 ].content[ 0 ].string = 'Utterance with ';
		storage.utterances[ 0 ].content[ 1 ] = { string: 'word and word' };
		storage.utterances[ 0 ].content[ 2 ] = { string: '.' };
		tokens = [
			{ orth: 'Utterance' },
			{ orth: 'with' },
			{ orth: 'word' },
			{ orth: 'and' },
			{ orth: 'word' },
			{ orth: '.' }
		];

		storage.addTokens( storage.utterances[ 0 ], tokens );

		assert.deepEqual( storage.utterances[ 0 ].tokens[ 4 ].startOffset, 9 );
		assert.deepEqual( storage.utterances[ 0 ].tokens[ 4 ].endOffset, 12 );
	} );

	QUnit.test( 'addTokens(): multiple utterances', function ( assert ) {
		var tokens;

		assert.expect( 6 );
		util.setContentHtml(
			'An utterance. Another utterance.'
		);
		storage.utterances[ 1 ].content[ 0 ].string =
			'Another utterance.';
		storage.utterances[ 1 ].startOffset = 14;
		tokens = [
			{ orth: 'Another' },
			{ orth: 'utterance' },
			{ orth: '.' }
		];

		storage.addTokens( storage.utterances[ 1 ], tokens );

		assert.deepEqual( storage.utterances[ 1 ].tokens[ 0 ].startOffset, 14 );
		assert.deepEqual( storage.utterances[ 1 ].tokens[ 0 ].endOffset, 20 );
		assert.deepEqual( storage.utterances[ 1 ].tokens[ 1 ].startOffset, 22 );
		assert.deepEqual( storage.utterances[ 1 ].tokens[ 1 ].endOffset, 30 );
		assert.deepEqual( storage.utterances[ 1 ].tokens[ 2 ].startOffset, 31 );
		assert.deepEqual( storage.utterances[ 1 ].tokens[ 2 ].endOffset, 31 );
	} );

	QUnit.test( 'addTokens(): multiple utterances and nodes', function ( assert ) {
		var tokens;

		assert.expect( 6 );
		util.setContentHtml(
			'An utterance. Another <b>utterance</b>.'
		);
		storage.utterances[ 1 ].content = [
			{ string: 'Another ' },
			{ string: 'utterance' },
			{ string: '.' }
		];
		storage.utterances[ 1 ].startOffset = 14;
		tokens = [
			{ orth: 'Another' },
			{ orth: 'utterance' },
			{ orth: '.' }
		];

		storage.addTokens( storage.utterances[ 1 ], tokens );

		assert.deepEqual( storage.utterances[ 1 ].tokens[ 0 ].startOffset, 14 );
		assert.deepEqual( storage.utterances[ 1 ].tokens[ 0 ].endOffset, 20 );
		assert.deepEqual( storage.utterances[ 1 ].tokens[ 1 ].startOffset, 0 );
		assert.deepEqual( storage.utterances[ 1 ].tokens[ 1 ].endOffset, 8 );
		assert.deepEqual( storage.utterances[ 1 ].tokens[ 2 ].startOffset, 0 );
		assert.deepEqual( storage.utterances[ 1 ].tokens[ 2 ].endOffset, 0 );
	} );

	QUnit.test( 'addTokens(): ambiguous, one character long tokens', function ( assert ) {
		var tokens;

		assert.expect( 2 );
		util.setContentHtml( 'a a a.' );
		storage.utterances[ 0 ].content[ 0 ].string = 'a a a.';
		tokens = [
			{ orth: 'a' },
			{ orth: 'a' },
			{ orth: 'a' },
			{ orth: '.' }
		];

		storage.addTokens( storage.utterances[ 0 ], tokens );

		assert.strictEqual( storage.utterances[ 0 ].tokens[ 2 ].startOffset, 4 );
		assert.strictEqual( storage.utterances[ 0 ].tokens[ 2 ].endOffset, 4 );
	} );

	QUnit.test( 'isSilent(): no duration', function ( assert ) {
		var actual, token;

		assert.expect( 1 );
		token = {
			string: 'no duration',
			startTime: 1000,
			endTime: 1000
		};
		actual = storage.isSilent( token );

		assert.strictEqual( actual, true );
	} );

	QUnit.test( 'isSilent(): no transcription', function ( assert ) {
		var actual, token;

		assert.expect( 1 );
		token = {
			string: '',
			startTime: 1000,
			endTime: 2000
		};
		actual = storage.isSilent( token );

		assert.strictEqual( actual, true );
	} );

	QUnit.test( 'isSilent(): non-silent', function ( assert ) {
		var actual, token;

		assert.expect( 1 );
		token = {
			string: 'token',
			startTime: 1000,
			endTime: 2000
		};
		actual = storage.isSilent( token );

		assert.strictEqual( actual, false );
	} );

	QUnit.test( 'getNextToken()', function ( assert ) {
		var actualToken;

		assert.expect( 1 );
		storage.utterances[ 0 ].tokens = [
			{
				string: 'original',
				utterance: storage.utterances[ 0 ],
				startTime: 0,
				endTime: 1000
			},
			{
				string: 'next',
				utterance: storage.utterances[ 0 ],
				startTime: 1000,
				endTime: 2000
			}
		];

		actualToken =
			storage.getNextToken( storage.utterances[ 0 ].tokens[ 0 ] );

		assert.strictEqual( actualToken, storage.utterances[ 0 ].tokens[ 1 ] );
	} );

	QUnit.test( 'getNextToken(): ignore silent tokens', function ( assert ) {
		var actualToken;

		assert.expect( 1 );
		storage.utterances[ 0 ].tokens = [
			{
				string: 'starting token',
				utterance: storage.utterances[ 0 ],
				startTime: 0,
				endTime: 1000
			},
			{
				string: 'no duration',
				utterance: storage.utterances[ 0 ],
				startTime: 1000,
				endTime: 1000
			},
			{
				string: '',
				utterance: storage.utterances[ 0 ],
				startTime: 1000,
				endTime: 2000
			},
			{
				string: 'goal',
				utterance: storage.utterances[ 0 ],
				startTime: 2000,
				endTime: 3000
			}
		];

		actualToken =
			storage.getNextToken( storage.utterances[ 0 ].tokens[ 0 ] );

		assert.strictEqual( actualToken, storage.utterances[ 0 ].tokens[ 3 ] );
	} );

	QUnit.test( 'getPreviousToken()', function ( assert ) {
		var actualToken;

		assert.expect( 1 );
		storage.utterances[ 0 ].tokens = [
			{
				string: 'previous',
				utterance: storage.utterances[ 0 ],
				startTime: 0,
				endTime: 1000
			},
			{
				string: 'original',
				utterance: storage.utterances[ 0 ],
				startTime: 1000,
				endTime: 2000
			}
		];

		actualToken =
			storage.getPreviousToken( storage.utterances[ 0 ].tokens[ 1 ] );

		assert.strictEqual( actualToken, storage.utterances[ 0 ].tokens[ 0 ] );
	} );

	QUnit.test( 'getPreviousToken(): ignore silent tokens', function ( assert ) {
		var actualToken;

		assert.expect( 1 );
		storage.utterances[ 0 ].tokens = [
			{
				string: 'goal',
				startTime: 0,
				endTime: 1000,
				utterance: storage.utterances[ 0 ]
			},
			{
				string: 'no duration',
				startTime: 1000,
				endTime: 1000,
				utterance: storage.utterances[ 0 ]
			},
			{
				string: '',
				startTime: 1000,
				endTime: 2000,
				utterance: storage.utterances[ 0 ]
			},
			{
				string: 'starting token',
				startTime: 2000,
				endTime: 3000,
				utterance: storage.utterances[ 0 ]
			}
		];
		storage.utterances[ 0 ].audio.currentTime = 2.1;

		actualToken =
			storage.getPreviousToken( storage.utterances[ 0 ].tokens[ 3 ] );

		assert.strictEqual( actualToken, storage.utterances[ 0 ].tokens[ 0 ] );
	} );

	QUnit.test( 'getLastToken()', function ( assert ) {
		var actualToken;

		assert.expect( 1 );
		storage.utterances[ 0 ].tokens = [
			{
				string: 'token',
				startTime: 0,
				endTime: 1000,
				utterance: storage.utterances[ 0 ]
			},
			{
				string: 'last',
				startTime: 1000,
				endTime: 2000,
				utterance: storage.utterances[ 0 ]
			}
		];

		actualToken =
			storage.getLastToken( storage.utterances[ 0 ] );

		assert.strictEqual( actualToken, storage.utterances[ 0 ].tokens[ 1 ] );
	} );

	QUnit.test( 'getLastToken(): ignore silent tokens', function ( assert ) {
		var actualToken;

		assert.expect( 1 );
		storage.utterances[ 0 ].tokens = [
			{
				string: 'token',
				startTime: 0,
				endTime: 1000,
				utterance: storage.utterances[ 0 ]
			},
			{
				string: 'last',
				startTime: 1000,
				endTime: 2000,
				utterance: storage.utterances[ 0 ]
			},
			{
				string: 'no duration',
				startTime: 2000,
				endTime: 2000,
				utterance: storage.utterances[ 0 ]
			},
			{
				string: '',
				startTime: 2000,
				endTime: 3000,
				utterance: storage.utterances[ 0 ]
			}
		];

		actualToken = storage.getLastToken( storage.utterances[ 0 ] );

		assert.strictEqual( actualToken, storage.utterances[ 0 ].tokens[ 1 ] );
	} );

	QUnit.test( 'getFirstTextNode()', function ( assert ) {
		var parentNode, expectedNode, actualNode;

		assert.expect( 1 );
		util.setContentHtml(
			'<a>first text node<br />other text node</a>'
		);
		parentNode = $( contentSelector + ' a' ).get( 0 );
		expectedNode = $( contentSelector + ' a' ).contents().get( 0 );

		actualNode = storage.getFirstTextNode( parentNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getFirstTextNode(): deeper than other text node', function ( assert ) {
		var parentNode, expectedNode, actualNode;

		assert.expect( 1 );
		util.setContentHtml(
			'<a><b>first text node</b>other text node</a>'
		);
		parentNode = $( contentSelector + ' a' ).get( 0 );
		expectedNode = $( contentSelector + ' b' ).contents().get( 0 );

		actualNode = storage.getFirstTextNode( parentNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getFirstTextNode(): given node is a text node', function ( assert ) {
		var expectedNode, actualNode;

		assert.expect( 1 );
		util.setContentHtml(
			'first text node<br />other text node'
		);
		expectedNode = $( contentSelector ).contents().get( 0 );

		actualNode = storage.getFirstTextNode( expectedNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getLastTextNode()', function ( assert ) {
		var parentNode, expectedNode, actualNode;

		assert.expect( 1 );
		util.setContentHtml(
			'<a>other text node<br />last text node</a>'
		);
		parentNode = $( contentSelector + ' a' ).get( 0 );
		expectedNode = $( contentSelector + ' a' ).contents().get( 2 );

		actualNode = storage.getLastTextNode( parentNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getLastTextNode(): deeper than other text node', function ( assert ) {
		var parentNode, expectedNode, actualNode;

		assert.expect( 1 );
		util.setContentHtml(
			'<a>other text node<b>other text node</b></a>'
		);
		parentNode = $( contentSelector + ' a' ).get( 0 );
		expectedNode = $( contentSelector + ' b' ).contents().get( 0 );

		actualNode = storage.getLastTextNode( parentNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getLastTextNode(): given node is a text node', function ( assert ) {
		var expectedNode, actualNode;

		assert.expect( 1 );
		util.setContentHtml(
			'other text node<br />last text node'
		);
		expectedNode = $( contentSelector ).contents().get( 2 );

		actualNode = storage.getLastTextNode( expectedNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getStartUtterance()', function ( assert ) {
		var textNode, offset, actualUtterance;

		assert.expect( 1 );
		storage.utterances[ 0 ].content[ 0 ].path = './text()';
		storage.utterances[ 0 ].endOffset = 14;
		storage.utterances[ 1 ].content[ 0 ].path = './text()';
		storage.utterances[ 1 ].startOffset = 16;
		storage.utterances[ 1 ].endOffset = 29;
		storage.utterances[ 2 ] = {
			content: [ { path: './text()' } ],
			startOffset: 31,
			endOffset: 44
		};
		util.setContentHtml(
			'Utterance zero. Utterance one. Utterance two.'
		);
		textNode = $( contentSelector ).contents().get( 0 );
		offset = 16;

		actualUtterance =
			storage.getStartUtterance(
				textNode,
				offset
			);

		assert.strictEqual( actualUtterance, storage.utterances[ 1 ] );
	} );

	QUnit.test( 'getStartUtterance(): offset between utterances', function ( assert ) {
		var textNode, offset, actualUtterance;

		assert.expect( 1 );
		storage.utterances[ 0 ].content[ 0 ].path = './text()';
		storage.utterances[ 1 ].content[ 0 ].path = './text()';
		storage.utterances[ 1 ].startOffset = 16;
		storage.utterances[ 1 ].endOffset = 29;
		storage.utterances[ 2 ] = {
			content: [ { path: './text()' } ],
			startOffset: 31,
			endOffset: 44
		};
		util.setContentHtml(
			'Utterance zero. Utterance one. Utterance two.'
		);
		textNode = $( contentSelector ).contents().get( 0 );
		offset = 15;

		actualUtterance =
			storage.getStartUtterance(
				textNode,
				offset
			);

		assert.strictEqual( actualUtterance, storage.utterances[ 1 ] );
	} );

	QUnit.test( 'getStartUtterance(): offset between utterances and next utterance in different node', function ( assert ) {
		var textNode, offset, actualUtterance;

		assert.expect( 1 );
		storage.utterances[ 0 ].content[ 0 ].path = './text()[1]';
		storage.utterances[ 1 ].content[ 0 ].path = './a/text()';
		storage.utterances[ 1 ].startOffset = 0;
		storage.utterances[ 1 ].endOffset = 13;
		storage.utterances[ 2 ] = {
			content: [ { path: './text()[2]' } ],
			startOffset: 1,
			endOffset: 14
		};
		util.setContentHtml(
			'Utterance zero. <a>Utterance one.</a> Utterance two.'
		);
		textNode = $( contentSelector ).contents().get( 0 );
		offset = 15;

		actualUtterance =
			storage.getStartUtterance(
				textNode,
				offset
			);

		assert.strictEqual( actualUtterance, storage.utterances[ 1 ] );
	} );

	QUnit.test( 'getEndUtterance()', function ( assert ) {
		var textNode, offset, actualUtterance;

		assert.expect( 1 );
		storage.utterances[ 0 ].content[ 0 ].path = './text()';
		storage.utterances[ 1 ].content[ 0 ].path = './text()';
		storage.utterances[ 1 ].startOffset = 16;
		storage.utterances[ 1 ].endOffset = 29;
		storage.utterances[ 2 ] = {
			content: [ { path: './text()' } ],
			startOffset: 31,
			endOffset: 44
		};
		util.setContentHtml(
			'Utterance zero. Utterance one. Utterance two.'
		);
		textNode = $( contentSelector ).contents().get( 0 );
		offset = 16;

		actualUtterance =
			storage.getEndUtterance(
				textNode,
				offset
			);

		assert.strictEqual( actualUtterance, storage.utterances[ 1 ] );
	} );

	QUnit.test( 'getEndUtterance(): offset between utterances', function ( assert ) {
		var textNode, offset, actualUtterance;

		assert.expect( 1 );
		storage.utterances[ 0 ].content[ 0 ].path = './text()';
		storage.utterances[ 1 ].content[ 0 ].path = './text()';
		storage.utterances[ 1 ].startOffset = 16;
		storage.utterances[ 1 ].endOffset = 29;
		storage.utterances[ 2 ] = {
			content: [ { path: './text()' } ],
			startOffset: 31,
			endOffset: 44
		};
		util.setContentHtml(
			'Utterance zero. Utterance one. Utterance two.'
		);
		textNode = $( contentSelector ).contents().get( 0 );
		offset = 30;

		actualUtterance =
			storage.getEndUtterance(
				textNode,
				offset
			);

		assert.strictEqual( actualUtterance, storage.utterances[ 1 ] );
	} );

	QUnit.test( 'getEndUtterance(): offset between utterances and previous utterance in different node', function ( assert ) {
		var textNode, offset, actualUtterance;

		assert.expect( 1 );
		storage.utterances[ 0 ].content[ 0 ].path = './text()[1]';
		storage.utterances[ 1 ].content[ 0 ].path = './a/text()';
		storage.utterances[ 1 ].startOffset = 0;
		storage.utterances[ 1 ].endOffset = 13;
		storage.utterances[ 2 ] = {
			content: [ { path: './text()[2]' } ],
			startOffset: 1,
			endOffset: 14
		};
		util.setContentHtml(
			'Utterance zero. <a>Utterance one.</a> Utterance two.'
		);
		textNode = $( contentSelector ).contents().get( 2 );
		offset = 0;

		actualUtterance =
			storage.getEndUtterance(
				textNode,
				offset
			);

		assert.strictEqual( actualUtterance, storage.utterances[ 1 ] );
	} );

	QUnit.test( 'getEndUtterance(): offset between utterances and previous utterance in different node with other utterance', function ( assert ) {
		var textNode, offset, actualUtterance;

		assert.expect( 1 );
		storage.utterances[ 0 ].content[ 0 ].path = './text()[1]';
		storage.utterances[ 1 ].content[ 0 ].path = './text()[1]';
		storage.utterances[ 1 ].startOffset = 16;
		storage.utterances[ 1 ].endOffset = 29;
		storage.utterances[ 2 ] = {
			content: [ { path: './text()[2]' } ],
			startOffset: 1,
			endOffset: 14
		};
		util.setContentHtml(
			'Utterance zero. Utterance one.<br /> Utterance two.'
		);
		textNode = $( contentSelector ).contents().get( 2 );
		offset = 0;

		actualUtterance =
			storage.getEndUtterance(
				textNode,
				offset
			);

		assert.strictEqual( actualUtterance, storage.utterances[ 1 ] );
	} );

	QUnit.test( 'getNextTextNode()', function ( assert ) {
		var originalNode, expectedNode, actualNode;
		assert.expect( 1 );
		util.setContentHtml(
			'original node<br />next node'
		);
		originalNode = $( contentSelector ).contents().get( 0 );
		expectedNode = $( contentSelector ).contents().get( 2 );

		actualNode =
			storage.getNextTextNode( originalNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getNextTextNode(): node is one level down', function ( assert ) {
		var originalNode, expectedNode, actualNode;
		assert.expect( 1 );
		util.setContentHtml(
			'original node<a>next node</a>'
		);
		originalNode = $( contentSelector ).contents().get( 0 );
		expectedNode = $( contentSelector + ' a' ).contents().get( 0 );

		actualNode =
			storage.getNextTextNode( originalNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getNextTextNode(): node is one level up', function ( assert ) {
		var originalNode, expectedNode, actualNode;
		assert.expect( 1 );
		util.setContentHtml(
			'<a>original node</a>next node'
		);
		originalNode = $( contentSelector + ' a' ).contents().get( 0 );
		expectedNode = $( contentSelector ).contents().get( 1 );

		actualNode =
			storage.getNextTextNode( originalNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getNextTextNode(): node contains non-text nodes', function ( assert ) {
		var originalNode, expectedNode, actualNode;

		assert.expect( 1 );
		util.setContentHtml(
			'original node<a><!--comment--></a>next node'
		);
		originalNode = $( contentSelector ).contents().get( 0 );
		expectedNode = $( contentSelector ).contents().get( 2 );

		actualNode =
			storage.getNextTextNode( originalNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getPreviousTextNode()', function ( assert ) {
		var originalNode, expectedNode, actualNode;

		assert.expect( 1 );
		util.setContentHtml(
			'previous node<br />original node'
		);
		originalNode = $( contentSelector ).contents().get( 2 );
		expectedNode = $( contentSelector ).contents().get( 0 );

		actualNode =
			storage.getPreviousTextNode( originalNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getPreviousTextNode(): node is one level down', function ( assert ) {
		var originalNode, expectedNode, actualNode;

		assert.expect( 1 );
		util.setContentHtml(
			'<a>previous node</a>original node'
		);
		originalNode = $( contentSelector ).contents().get( 1 );
		expectedNode = $( contentSelector + ' a' ).contents().get( 0 );

		actualNode =
			storage.getPreviousTextNode( originalNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getPreviousTextNode(): node is one level up', function ( assert ) {
		var originalNode, expectedNode, actualNode;

		assert.expect( 1 );
		util.setContentHtml(
			'previous node<a>original node</a>'
		);
		originalNode = $( contentSelector + ' a' ).contents().get( 0 );
		expectedNode = $( contentSelector ).contents().get( 0 );

		actualNode =
			storage.getPreviousTextNode( originalNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getPreviousTextNode(): node contains non-text nodes', function ( assert ) {
		var originalNode, expectedNode, actualNode;

		assert.expect( 1 );
		util.setContentHtml(
			'previous node<a><!--comment--></a>original node'
		);
		originalNode = $( contentSelector ).contents().get( 2 );
		expectedNode = $( contentSelector ).contents().get( 0 );

		actualNode =
			storage.getPreviousTextNode( originalNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getStartToken()', function ( assert ) {
		var textNode, actualToken;

		assert.expect( 1 );
		util.setContentHtml( 'Utterance zero.' );
		textNode = $( contentSelector ).contents().get( 0 );
		storage.utterances[ 0 ].content[ 0 ].path = './text()';
		storage.utterances[ 0 ].tokens = [
			{
				string: 'Utterance',
				items: [ storage.utterances[ 0 ].content[ 0 ] ],
				utterance: storage.utterances[ 0 ],
				startOffset: 0,
				endOffset: 8
			},
			{
				string: 'zero',
				items: [ storage.utterances[ 0 ].content[ 0 ] ],
				utterance: storage.utterances[ 0 ],
				startOffset: 10,
				endOffset: 13
			},
			{
				string: '.',
				items: [ storage.utterances[ 0 ].content[ 0 ] ],
				utterance: storage.utterances[ 0 ],
				startOffset: 14,
				endOffset: 14
			}
		];

		actualToken =
			storage.getStartToken(
				storage.utterances[ 0 ],
				textNode,
				0
			);

		assert.strictEqual( actualToken, storage.utterances[ 0 ].tokens[ 0 ] );
	} );

	QUnit.test( 'getStartToken(): between tokens', function ( assert ) {
		var textNode, actualToken;

		assert.expect( 1 );
		util.setContentHtml( 'Utterance zero.' );
		textNode = $( contentSelector ).contents().get( 0 );
		storage.utterances[ 0 ].content[ 0 ].path = './text()';
		storage.utterances[ 0 ].tokens = [
			{
				string: 'Utterance',
				items: [ storage.utterances[ 0 ].content[ 0 ] ],
				utterance: storage.utterances[ 0 ],
				startOffset: 0,
				endOffset: 8
			},
			{
				string: 'zero',
				items: [ storage.utterances[ 0 ].content[ 0 ] ],
				utterance: storage.utterances[ 0 ],
				startOffset: 10,
				endOffset: 13
			},
			{
				string: '.',
				items: [ storage.utterances[ 0 ].content[ 0 ] ],
				utterance: storage.utterances[ 0 ],
				startOffset: 14,
				endOffset: 14
			}
		];

		actualToken =
			storage.getStartToken(
				storage.utterances[ 0 ],
				textNode,
				9
			);

		assert.strictEqual( actualToken, storage.utterances[ 0 ].tokens[ 1 ] );
	} );

	QUnit.test( 'getStartToken(): in different node', function ( assert ) {
		var textNode, actualToken;

		assert.expect( 1 );
		util.setContentHtml( 'Utterance <br />zero.' );
		textNode = $( contentSelector ).contents().get( 0 );
		storage.utterances[ 0 ].content[ 0 ].path = './text()[1]';
		storage.utterances[ 0 ].content[ 1 ] = { path: './text()[2]' };
		storage.utterances[ 0 ].tokens = [
			{
				string: 'Utterance',
				items: [ storage.utterances[ 0 ].content[ 0 ] ],
				utterance: storage.utterances[ 0 ],
				startOffset: 0,
				endOffset: 8
			},
			{
				string: 'zero',
				items: [ storage.utterances[ 0 ].content[ 1 ] ],
				utterance: storage.utterances[ 0 ],
				startOffset: 0,
				endOffset: 3
			},
			{
				string: '.',
				items: [ storage.utterances[ 0 ].content[ 1 ] ],
				utterance: storage.utterances[ 0 ],
				startOffset: 4,
				endOffset: 4
			}
		];

		actualToken =
			storage.getStartToken(
				storage.utterances[ 0 ],
				textNode,
				9
			);

		assert.strictEqual( actualToken, storage.utterances[ 0 ].tokens[ 1 ] );
	} );

	QUnit.test( 'getEndToken()', function ( assert ) {
		var textNode, actualToken;

		assert.expect( 1 );
		util.setContentHtml( 'Utterance zero.' );
		textNode = $( contentSelector ).contents().get( 0 );
		storage.utterances[ 0 ].content[ 0 ].path = './text()';
		storage.utterances[ 0 ].tokens = [
			{
				string: 'Utterance',
				items: [ storage.utterances[ 0 ].content[ 0 ] ],
				utterance: storage.utterances[ 0 ],
				startOffset: 0,
				endOffset: 8
			},
			{
				string: 'zero',
				items: [ storage.utterances[ 0 ].content[ 0 ] ],
				utterance: storage.utterances[ 0 ],
				startOffset: 10,
				endOffset: 13
			},
			{
				string: '.',
				items: [ storage.utterances[ 0 ].content[ 0 ] ],
				utterance: storage.utterances[ 0 ],
				startOffset: 14,
				endOffset: 14
			}
		];

		actualToken =
			storage.getEndToken(
				storage.utterances[ 0 ],
				textNode,
				10
			);

		assert.strictEqual( actualToken, storage.utterances[ 0 ].tokens[ 1 ] );
	} );

	QUnit.test( 'getEndToken(): between tokens', function ( assert ) {
		var textNode, actualToken;

		assert.expect( 1 );
		util.setContentHtml( 'Utterance zero.' );
		textNode = $( contentSelector ).contents().get( 0 );
		storage.utterances[ 0 ].content[ 0 ].path = './text()';
		storage.utterances[ 0 ].tokens = [
			{
				string: 'Utterance',
				items: [ storage.utterances[ 0 ].content[ 0 ] ],
				utterance: storage.utterances[ 0 ],
				startOffset: 0,
				endOffset: 8
			},
			{
				string: 'zero',
				items: [ storage.utterances[ 0 ].content[ 0 ] ],
				utterance: storage.utterances[ 0 ],
				startOffset: 10,
				endOffset: 13
			},
			{
				string: '.',
				items: [ storage.utterances[ 0 ].content[ 0 ] ],
				utterance: storage.utterances[ 0 ],
				startOffset: 14,
				endOffset: 14
			}
		];

		actualToken =
			storage.getEndToken(
				storage.utterances[ 0 ],
				textNode,
				9
			);

		assert.strictEqual( actualToken, storage.utterances[ 0 ].tokens[ 0 ] );
	} );

	QUnit.test( 'getEndToken(): in different node', function ( assert ) {
		var textNode, actualToken;

		assert.expect( 1 );
		util.setContentHtml( 'Utterance<br /> zero.' );
		textNode = $( contentSelector ).contents().get( 0 );
		storage.utterances[ 0 ].content[ 0 ].path = './text()[1]';
		storage.utterances[ 0 ].content[ 1 ] = { path: './text()[2]' };
		storage.utterances[ 0 ].tokens = [
			{
				string: 'Utterance',
				items: [ storage.utterances[ 0 ].content[ 0 ] ],
				utterance: storage.utterances[ 0 ],
				startOffset: 0,
				endOffset: 8
			},
			{
				string: 'zero',
				items: [ storage.utterances[ 0 ].content[ 1 ] ],
				utterance: storage.utterances[ 0 ],
				startOffset: 1,
				endOffset: 4
			},
			{
				string: '.',
				items: [ storage.utterances[ 0 ].content[ 1 ] ],
				utterance: storage.utterances[ 0 ],
				startOffset: 5,
				endOffset: 5
			}
		];

		actualToken =
			storage.getEndToken(
				storage.utterances[ 0 ],
				textNode,
				0
			);

		assert.strictEqual( actualToken, storage.utterances[ 0 ].tokens[ 0 ] );
	} );

	QUnit.test( 'getNodeForItem()', function ( assert ) {
		var item, textNode, contentSelector;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml( 'Text node.' );
		item = { path: './text()' };
		contentSelector = mw.config.get( 'wgWikispeechContentSelector' );

		textNode = storage.getNodeForItem( item );

		assert.strictEqual(
			textNode,
			$( contentSelector ).contents().get( 0 )
		);
	} );
}( mediaWiki, jQuery ) );
