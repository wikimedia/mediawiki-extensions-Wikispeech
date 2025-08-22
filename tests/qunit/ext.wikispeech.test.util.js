/**
 * Set the text of the MW content div.
 *
 * If the div doesn't exist, a new one is added.
 *
 * @param {string} html The HTML added to the div element.
 */
function setContentHtml( html ) {
	const contentSelector =
		mw.config.get( 'wgWikispeechContentSelector' );
	if ( $( '#qunit-fixture ' + contentSelector ).length ) {
		$( '#qunit-fixture ' + contentSelector ).html( html );
	} else {
		$( '#qunit-fixture' ).append(
			$( '<div>' )
				// Remove the leading "#".
				.attr( 'id', contentSelector.slice( 1 ) )
				.html( html )
		);
	}
}
module.exports = { setContentHtml };
