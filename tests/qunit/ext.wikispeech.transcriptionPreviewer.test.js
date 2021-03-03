QUnit.module( 'ext.wikispeech.transcriptionPreviewer', QUnit.newMwEnvironment( {
	setup: function () {
		var TranscriptionPreviewer, languageField, transcriptionField, api,
			$previewPlayer;

		TranscriptionPreviewer = require(
			'../../modules/ext.wikispeech.transcriptionPreviewer.js'
		);
		languageField = sinon.stub( new OO.ui.TextInputWidget() );
		transcriptionField = sinon.stub( new OO.ui.TextInputWidget() );
		api = sinon.stub( new mw.Api() );
		$previewPlayer = sinon.stub( $() );
		this.transcriptionPreviewer = new TranscriptionPreviewer(
			languageField,
			transcriptionField,
			api,
			$previewPlayer
		);
	}
} ) );

QUnit.test( 'synthesizePreview()', function () {
	var response;

	this.transcriptionPreviewer.languageField.getValue.returns( 'en' );
	this.transcriptionPreviewer.transcriptionField.getValue.returns(
		'transcription'
	);
	response = $.Deferred().resolve( {
		'wikispeech-listen': {
			audio: 'audio data'
		}
	} );
	this.transcriptionPreviewer.api.get.returns( response );

	this.transcriptionPreviewer.synthesizePreview();

	sinon.assert.calledOnce( this.transcriptionPreviewer.api.get );
	sinon.assert.calledWithExactly(
		this.transcriptionPreviewer.api.get,
		{
			action: 'wikispeech-listen',
			lang: 'en',
			ipa: 'transcription'
		}
	);
	sinon.assert.calledOnce( this.transcriptionPreviewer.$previewPlayer.attr );
	sinon.assert.calledWithExactly(
		this.transcriptionPreviewer.$previewPlayer.attr,
		'src',
		'data:audio/ogg;base64,audio data'
	);
} );
