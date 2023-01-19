var util = require( './ext.wikispeech.util.js' );

/**
 * Generates audio preview for the transcription in SpecialEditLexicon.
 *
 * @class TranscriptionPreviewer
 * @param {jQuery} $language
 * @param {jQuery} $transcription
 * @param {mw.Api} api
 * @param {jQuery} $player
 */
function TranscriptionPreviewer(
	$language,
	$transcription,
	api,
	$player
) {
	this.$language = $language;
	this.$transcription = $transcription;
	this.api = api;
	this.$player = $player;
}

/**
 * Play the transcription using TTS.
 *
 * If the transcription has changed since last play, a new one
 * retrieved. Otherwise the previous one is replayed.
 */
TranscriptionPreviewer.prototype.play = function () {
	var transcription = this.$transcription.val();
	// Rewind in case it is already playing. Just calling play() is not enought to play from start.
	this.$player.prop( 'currentTime', 0 );
	if ( transcription !== this.lastTranscription || !this.$player.attr( 'src' ) ) {
		this.fetchAudio();
		this.lastTranscription = transcription;
	} else {
		this.$player.get( 0 ).play();
	}
};

/**
 * Get audio for the player using the listen API
 */
TranscriptionPreviewer.prototype.fetchAudio = function () {
	var language, voice, transcription, self, message, title;
	language = this.$language.val();
	voice = util.getUserVoice( language );
	transcription = this.$transcription.val();
	mw.log( 'Fetching transcription preview for (' + language + '): ' + transcription );
	self = this;
	this.api.get( {
		action: 'wikispeech-listen',
		lang: language,
		ipa: transcription,
		voice: voice
	} ).done( function ( response ) {
		var audioData = response[ 'wikispeech-listen' ].audio;
		self.$player.attr( 'src', 'data:audio/ogg;base64,' + audioData );
		self.$player.get( 0 ).play();
	} ).fail( function ( code, result ) {
		self.$player.attr( 'src', '' );
		mw.log.error( 'Failed to synthesize:', code, result );
		message = mw.msg( 'wikispeech-error-generate-preview-message' ) +
			result.error.info;
		title = mw.msg( 'wikispeech-error-generate-preview-title' );
		OO.ui.alert( message, { title: title } );
	} );
};

module.exports = TranscriptionPreviewer;
