/**
 * Generates audio preview for the transcription in SpecialEditLexicon.
 *
 * @class TranscriptionPreviewer
 * @param {OO.ui.TextInputWidget} languageField
 * @param {OO.ui.TextInputWidget} transcriptionField
 * @param {mw.Api} api
 * @param {jQuery} $previewPlayer
 */
function TranscriptionPreviewer(
	languageField,
	transcriptionField,
	api,
	$previewPlayer
) {
	this.languageField = languageField;
	this.transcriptionField = transcriptionField;
	this.api = api;
	this.$previewPlayer = $previewPlayer;
}

/**
 * Synthesize what the transcription sounds like as read by Speechiod.
 */
TranscriptionPreviewer.prototype.synthesizePreview = function () {
	var self = this;
	this.api.get( {
		action: 'wikispeech-listen',
		lang: this.languageField.getValue(),
		ipa: this.transcriptionField.getValue()
	} ).done( function ( response ) {
		var audioData = response[ 'wikispeech-listen' ].audio;
		self.$previewPlayer.attr( 'src', 'data:audio/ogg;base64,' + audioData );
	} ).fail( function ( code, result ) {
		mw.log.error( 'Failed to synthesize:', code, result );
	} );
};

module.exports = TranscriptionPreviewer;
