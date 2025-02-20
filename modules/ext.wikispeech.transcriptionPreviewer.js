const util = require( './ext.wikispeech.util.js' );

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
 *
 * @return {jQuery.Promise} Fulfilled when audio starts playing, rejected if
 *  audio was not present.
 */
TranscriptionPreviewer.prototype.play = function () {
	const transcription = this.$transcription.val();
	// Rewind in case it is already playing. Just calling play() is not enought to play from start.
	this.$player.prop( 'currentTime', 0 );
	const self = this;
	let promise;
	if ( transcription !== this.lastTranscription || !this.$player.attr( 'src' ) ) {
		promise = this.fetchAudio().then( () => {
			self.$player.get( 0 ).play();
		} );
		this.lastTranscription = transcription;
	} else {
		this.$player.get( 0 ).play();
		promise = $.Deferred().resolve().promise();
	}

	return promise;
};

/**
 * Get audio for the player using the listen API
 *
 * @return {jQuery.Promise} Fulfilled when audio is fetched, rejected
 *  if there was an error.
 */
TranscriptionPreviewer.prototype.fetchAudio = function () {
	const language = this.$language.val();
	const voice = util.getUserVoice( language );
	const transcription = this.$transcription.val();
	mw.log( 'Fetching transcription preview for (' + language + '): ' + transcription );
	const self = this;
	const request = this.api.get( {
		action: 'wikispeech-listen',
		lang: language,
		ipa: transcription,
		voice: voice
	} ).done( ( response ) => {
		const audioData = response[ 'wikispeech-listen' ].audio;
		self.$player.attr( 'src', 'data:audio/ogg;base64,' + audioData );
	} ).fail( ( code, result ) => {
		self.$player.attr( 'src', '' );
		mw.log.error( 'Failed to synthesize:', code, result );
		const message = result.error.info;
		const title = mw.msg( 'wikispeech-error-generate-preview-title' );
		OO.ui.alert( message, { title: title, size: 'medium' } );
	} );

	return request;
};

module.exports = TranscriptionPreviewer;
