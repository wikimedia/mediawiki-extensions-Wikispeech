( function () {

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
	}

	mw.wikispeech = mw.wikispeech || {};
	mw.wikispeech.util = new Util();
}() );
