( function () {

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
		var voiceKey, voice;
		voiceKey = getVoiceConfigVariable( language );
		voice = mw.user.options.get( voiceKey );
		return voice;
	}

	/**
	 * Contains general help functions that are used by multiple modules.
	 *
	 * @class ext.wikispeech.Util
	 * @constructor
	 */

	function Util() {
		/**
		 * Get the last item in an array.
		 *
		 * @param {Array} array The array to look in.
		 * @return {Mixed} The last item in the array.
		 */

		this.getLast = function ( array ) {
			return array[ array.length - 1 ];
		};

		// This allows the old way of loading scripts.
		this.getUserVoice = getUserVoice;
	}

	// For modules that do not use package files.
	mw.wikispeech = mw.wikispeech || {};
	mw.wikispeech.util = new Util();
	// For modules that use package files.
	module.exports = {
		getUserVoice: getUserVoice,
		getVoiceConfigVariable: getVoiceConfigVariable
	};
}() );
