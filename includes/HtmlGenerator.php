<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0+
 */

class HtmlGenerator {

	/**
	 * Generate an HTML string for a sequence of utternaces. Utterance tags
	 * look like this:
	 * <utterance id="utterance-0><text>Utterance string.</text><audio></audio></utterance>
	 * The <text> and <audio> tags are used to request audio from the TTS
	 * server and store the response.
	 *
	 * @since 0.0.1
	 * @param array $utterances The utterance strings to generate HTML from.
	 * @return string An HTML string containing the <utterance> tags, wrapped
	 *	in an <utterances> tag.
	 */

	public static function generateUtterancesHtml( $utterances ) {
		if ( count( $utterances ) ) {
			$dom = new DOMDocument();
			$utterancesNode = $dom->createElement( 'utterances' );
			// Hide the content of the utterance elements.
			$utterancesNode->setAttribute( 'hidden', '' );
			$index = 0;
			foreach ( $utterances as $utteranceString ) {
				$utteranceNode = self::generateUtteranceElement(
					$dom,
					$utteranceString,
					$index
				);
				$utterancesNode->appendChild( $utteranceNode );
				$index += 1;
			}
			$utternacesHtml = urldecode( $dom->saveHTML( $utterancesNode ) );
			return $utternacesHtml;
		}
	}

	/**
	 * Create an utterance element, which has child elements for the utterance
	 * string and audio.
	 *
	 * @since 0.0.1
	 * @param DOMDocument $dom The DOMDocument to use for creating the
	 *	elements.
	 * @param string $utteranceString The string to add to the text element,
	 *	which is later sent to the TTS server.
	 * @param int $index The index of the element, used for giving it an id.
	 *	Later used for playing the utterances in the correct order.
	 * @return DOMElement The resulting utterance element.
	 */

	private static function generateUtteranceElement(
		$dom,
		$utteranceString,
		$index
	) {
		$utteranceElement = $dom->createElement( 'utterance' );
		$utteranceElement->setAttribute( 'id', "utterance-$index" );
		$textNode = $dom->createElement(
			'text',
			// URL encoding (and later decoding) if required due to
			// strings containing # not being written otherwise.
			urlencode( $utteranceString ) );
		$utteranceElement->appendChild( $textNode );
		$audioNode = $dom->createElement( 'audio' );
		$utteranceElement->appendChild( $audioNode );
		return $utteranceElement;
	}
}
