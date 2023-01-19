QUnit.module( 'ext.wikispeech.transcriptionPreviewer', QUnit.newMwEnvironment( {
	beforeEach: function () {
		var TranscriptionPreviewer = require(
			'../../modules/ext.wikispeech.transcriptionPreviewer.js'
		);
		var $language = sinon.stub( $( '<select>' ) );
		var $transcription = sinon.stub( $( '<input>' ) );
		var api = sinon.stub( new mw.Api() );
		var $player = sinon.stub( $( '<audio>' ) );
		$player.get.returns( sinon.stub( $( '<audio>' ).get( 0 ) ) );
		this.transcriptionPreviewer = new TranscriptionPreviewer(
			$language,
			$transcription,
			api,
			$player
		);
	}
} ) );

QUnit.test( 'fetchAudio(): fetch audio from API and play', function () {
	var originalVoice = mw.user.options.get( 'wikispeechVoiceEn' );
	mw.user.options.set( 'wikispeechVoiceEn', 'en-voice' );
	this.transcriptionPreviewer.$language.val.returns( 'en' );
	this.transcriptionPreviewer.$transcription.val.returns(
		'transcription'
	);
	var response = $.Deferred().resolve( {
		'wikispeech-listen': {
			audio: 'audio data'
		}
	} );
	this.transcriptionPreviewer.api.get.returns( response );

	this.transcriptionPreviewer.fetchAudio();

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
	sinon.assert.calledOnce(
		this.transcriptionPreviewer.$player.get( 0 ).play
	);

	// Reset user option to avoid any side effects in other tests.
	mw.user.options.set( 'wikispeechVoiceEn', originalVoice );
} );

QUnit.test( 'play(): fetch new audio when not played before', function ( assert ) {
	this.transcriptionPreviewer.lastTranscription = null;
	this.transcriptionPreviewer.$transcription.val.returns(
		'new transcription'
	);
	sinon.stub( this.transcriptionPreviewer, 'fetchAudio' );

	this.transcriptionPreviewer.play();

	sinon.assert.calledOnce( this.transcriptionPreviewer.fetchAudio );
	// Audio is played as part of fetchAudio(), so we do not want to
	// do it again.
	sinon.assert.notCalled(
		this.transcriptionPreviewer.$player.get( 0 ).play
	);
	assert.strictEqual(
		this.transcriptionPreviewer.lastTranscription,
		'new transcription'
	);
} );

QUnit.test( 'play(): fetch new audio when no audio data', function () {
	this.transcriptionPreviewer.lastTranscription = 'same transcription';
	this.transcriptionPreviewer.$transcription.val.returns(
		'same transcription'
	);
	this.transcriptionPreviewer.$player.attr.returns( '' );
	sinon.stub( this.transcriptionPreviewer, 'fetchAudio' );

	this.transcriptionPreviewer.play();

	sinon.assert.calledOnce( this.transcriptionPreviewer.fetchAudio );
	// Audio is played as part of fetchAudio(), so we do not want to
	// do it again.
	sinon.assert.notCalled(
		this.transcriptionPreviewer.$player.get( 0 ).play
	);
} );

QUnit.test( 'play(): play same audio if transcription has not changed', function () {
	this.transcriptionPreviewer.lastTranscription = 'same transcription';
	this.transcriptionPreviewer.$transcription.val.returns(
		'same transcription'
	);
	this.transcriptionPreviewer.$player.attr.returns( 'not empty' );
	sinon.stub( this.transcriptionPreviewer, 'fetchAudio' );

	this.transcriptionPreviewer.play();

	sinon.assert.notCalled( this.transcriptionPreviewer.fetchAudio );
	sinon.assert.calledOnce(
		this.transcriptionPreviewer.$player.get( 0 ).play
	);
} );

QUnit.test( 'play(): rewind before playing', function () {
	this.transcriptionPreviewer.$player.currentTime = 1.0;
	this.transcriptionPreviewer.$player.attr.returns( 'not empty' );

	this.transcriptionPreviewer.play();

	sinon.assert.calledOnce( this.transcriptionPreviewer.$player.prop );
	sinon.assert.calledWithExactly(
		this.transcriptionPreviewer.$player.prop,
		'currentTime',
		0
	);
} );
