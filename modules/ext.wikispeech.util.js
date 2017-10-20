( function ( mw, $ ) {

	/**
	 * Contains general help functions that are used by multiple modules.
	 *
	 * @class ext.wikispeech.Util
	 * @constructor
	 */

	function Util() {

		/**
		 * Find the text node from which a content item was created.
		 *
		 * The path property of the item is an XPath expression
		 * that is used to traverse the DOM tree.
		 *
		 * @param {Object} item The item to find the text node for.
		 * @return {TextNode} The text node associated with the item.
		 */

		this.getNodeForItem = function ( item ) {
			var node, result, contentSelector;

			// The path should be unambiguous, so just get the first
			// matching node.
			contentSelector = mw.config.get( 'wgWikispeechContentSelector' );
			result = document.evaluate(
				item.path,
				$( contentSelector ).get( 0 ),
				null,
				XPathResult.FIRST_ORDERED_NODE_TYPE,
				null
			);
			node = result.singleNodeValue;
			return node;
		};

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
}( mediaWiki, jQuery ) );
