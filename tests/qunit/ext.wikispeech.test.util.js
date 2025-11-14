/**
 * Set the text of the MW content div.
 *
 * If the div doesn't exist, a new one is added.
 *
 * @param {string} html The HTML added to the div element.
 * @return {jQuery} The content.
 */
function setContentHtml( html ) {
	const contentSelector =
		mw.config.get( 'wgWikispeechContentSelector' );
	let $content;
	if ( $( '#qunit-fixture ' + contentSelector ).length ) {
		$content = $( '#qunit-fixture ' + contentSelector );
		$content.html( html );
	} else {
		$content = $( '<div>' )
			// Remove the leading "#".
			.attr( 'id', contentSelector.slice( 1 ) );
		$( '#qunit-fixture' ).append( $content.html( html ) );
	}
	return $content;
}
module.exports = { setContentHtml };
