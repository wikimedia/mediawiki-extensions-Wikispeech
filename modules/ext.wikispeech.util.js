/**
 * @module ext.wikispeech
 */

// This module can be used by modules that use both package files
// and "the old way". It is not very elegant, but it works. When
// T277733 is done support for non-package files can be dropped.

/**
 * Get the voice variable name for a given language.
 *
 * @param {string} language Language code.
 * @return {string}
 */
function getVoiceConfigVariable( language ) {
	// Capitalize first letter in language code.
	return 'wikispeechVoice' +
		language[ 0 ].toUpperCase() +
		language.slice( 1 );
}

/**
 * Get the users selected voice for a given language.
 *
 * @param {string} language Language code.
 * @return {string}
 */
function getUserVoice( language ) {
	const voiceKey = getVoiceConfigVariable( language );
	const voice = mw.user.options.get( voiceKey );
	return voice;
}

/**
 * Get the last item in an array.
 *
 * @param {Array} array The array to look in.
 * @return {any} The last item in the array.
 */
function getLast( array ) {
	return array[ array.length - 1 ];
}
// This allows the old way of loading scripts.
// this.getUserVoice = getUserVoice;
// For modules that do not use package files.
// mw.wikispeech = mw.wikispeech || {};
// mw.wikispeech.util = new Util();
// For modules that use package files.
module.exports = {
	getUserVoice,
	getVoiceConfigVariable,
	getLast
};
