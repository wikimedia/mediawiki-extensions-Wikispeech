QUnit.module( 'ext.wikispeech.transcriptionPreviewer', QUnit.newMwEnvironment( {
	beforeEach: function () {
		const TranscriptionPreviewer = require(
			'../../modules/ext.wikispeech.transcriptionPreviewer.js'
		);
		const $language = sinon.stub( $( '<select>' ) );
		const $transcription = sinon.stub( $( '<input>' ) );
		const api = sinon.stub( new mw.Api() );
		const $player = sinon.stub( $( '<audio>' ) );
		$player.get.returns( sinon.stub( $( '<audio>' ).get( 0 ) ) );
		this.transcriptionPreviewer = new TranscriptionPreviewer(
			$language,
			$transcription,
			api,
			$player
		);
	}
} ) );

QUnit.test( 'fetchAudio(): fetch audio from API', function ( assert ) {
	const originalVoice = mw.user.options.get( 'wikispeechVoiceEn' );
	mw.user.options.set( 'wikispeechVoiceEn', 'en-voice' );
	this.transcriptionPreviewer.$language.val.returns( 'en' );
	this.transcriptionPreviewer.$transcription.val.returns(
		'transcription'
	);
	const response = $.Deferred().resolve( {
		'wikispeech-listen': {
			audio: 'audio data'
		}
	} );
	this.transcriptionPreviewer.api.get.returns( response );

	const done = this.transcriptionPreviewer.fetchAudio();

	sinon.assert.calledOnce( this.transcriptionPreviewer.api.get );
	sinon.assert.calledWithExactly(
		this.transcriptionPreviewer.api.get,
		{
			action: 'wikispeech-listen',
			lang: 'en',
			voice: 'en-voice',
			ipa: 'transcription'
		}
	);
	sinon.assert.calledOnce( this.transcriptionPreviewer.$player.attr );
	sinon.assert.calledWithExactly(
		this.transcriptionPreviewer.$player.attr,
		'src',
		'data:audio/ogg;base64,audio data'
	);
	assert.strictEqual( done.state(), 'resolved' );

	// Reset user option to avoid any side effects in other tests.
	mw.user.options.set( 'wikispeechVoiceEn', originalVoice );
} );

QUnit.test( 'play(): fetch new audio when not played before', function ( assert ) {
	let promise, self;
	this.transcriptionPreviewer.lastTranscription = null;
	this.transcriptionPreviewer.$transcription.val.returns(
		'new transcription'
	);
	promise = $.Deferred().resolve().promise();
	sinon.stub( this.transcriptionPreviewer, 'fetchAudio' ).returns( promise );

	const done = this.transcriptionPreviewer.play();

	sinon.assert.calledOnce( this.transcriptionPreviewer.fetchAudio );
	self = this;
	promise.then( () => {
		sinon.assert.calledOnce(
			self.transcriptionPreviewer.$player.get( 0 ).play
		);
		assert.strictEqual( done.state(), 'resolved' );
	} );
	assert.strictEqual(
		this.transcriptionPreviewer.lastTranscription,
		'new transcription'
	);
} );

QUnit.test( 'play(): fetch new audio when no audio data', function ( assert ) {
	let promise, self;
	this.transcriptionPreviewer.lastTranscription = 'same transcription';
	this.transcriptionPreviewer.$transcription.val.returns(
		'same transcription'
	);
	this.transcriptionPreviewer.$player.attr.returns( '' );
	promise = $.Deferred().resolve().promise();
	sinon.stub( this.transcriptionPreviewer, 'fetchAudio' ).returns( promise );

	const done = this.transcriptionPreviewer.play();

	sinon.assert.calledOnce( this.transcriptionPreviewer.fetchAudio );
	self = this;
	promise.then( () => {
		sinon.assert.calledOnce(
			self.transcriptionPreviewer.$player.get( 0 ).play
		);
		assert.strictEqual( done.state(), 'resolved' );
	} );
} );

QUnit.test( 'play(): play same audio if transcription has not changed', function ( assert ) {
	this.transcriptionPreviewer.lastTranscription = 'same transcription';
	this.transcriptionPreviewer.$transcription.val.returns(
		'same transcription'
	);
	this.transcriptionPreviewer.$player.attr.returns( 'not empty' );
	sinon.stub( this.transcriptionPreviewer, 'fetchAudio' );

	const done = this.transcriptionPreviewer.play();

	sinon.assert.notCalled( this.transcriptionPreviewer.fetchAudio );
	sinon.assert.calledOnce(
		this.transcriptionPreviewer.$player.get( 0 ).play
	);
	assert.strictEqual( done.state(), 'resolved' );
} );

QUnit.test( 'play(): rewind before playing', function ( assert ) {
	this.transcriptionPreviewer.$player.currentTime = 1.0;
	this.transcriptionPreviewer.$player.attr.returns( 'not empty' );

	const done = this.transcriptionPreviewer.play();

	sinon.assert.calledOnce( this.transcriptionPreviewer.$player.prop );
	sinon.assert.calledWithExactly(
		this.transcriptionPreviewer.$player.prop,
		'currentTime',
		0
	);
	assert.strictEqual( done.state(), 'resolved' );
} );
