<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0+
 */

/**
 * Used for cleaning text with HTML markup. The cleaned text is used
 * as input for `Segmenter`.
 *
 * @since 0.0.1
 */

class Cleaner {

	/**
	 * Clean HTML tags from a string.
	 *
	 * Separates any HTML tags from the text.
	 *
	 * @since 0.0.1
	 * @param string $markedUpText Input text that may contain HTML
	 *  tags.
	 * @return array An array of `CleanedText`s representing text nodes.
	 */

	public static function cleanHtml( $markedUpText ) {
		$dom = self::createDomDocument( $markedUpText );
		$xpath = new DOMXPath( $dom );
		// Only add elements below the dummy element. These are the
		// elements from the original HTML.
		$top = $xpath->evaluate( '/meta/dummy' )->item( 0 );
		$cleanedContent = [];
		self::addContent(
			$cleanedContent,
			$top
		);
		// Remove any segment break at the start or end of the array,
		// since they won't do anything.
		if (
			count( $cleanedContent ) &&
			$cleanedContent[0] instanceof SegmentBreak
		) {
			array_shift( $cleanedContent );
		}
		if ( self::lastElement( $cleanedContent ) instanceof SegmentBreak ) {
			array_pop( $cleanedContent );
		}
		return $cleanedContent;
	}

	/**
	 * Create a DOMDocument from an HTML string.
	 *
	 * A dummy element is added as top node.
	 *
	 * @since 0.0.1
	 * @param string $markedUpString The string to create the
	 *  DOMDocument.
	 * @return DOMDocument The created DOMDocument.
	 */

	private static function createDomDocument( $markedUpText ) {
		$dom = new DOMDocument();
		// Add encoding information and wrap the input text in a dummy
		// tag to prevent p tags from being added for text nodes.
		// @codingStandardsIgnoreStart
		$wrappedText = '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/><dummy>' . $markedUpText . '</dummy></head>';
		// @codingStandardsIgnoreEnd
		libxml_use_internal_errors( true );
		$dom->loadHTML(
			$wrappedText,
			LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED
		);
		return $dom;
	}

	/**
	 * Recursively add items to the cleaned content.
	 *
	 * Goes through all the child nodes of $node and adds their
	 * content text. Adds segment breaks for appropriate tags.
	 *
	 * @since 0.0.1
	 * @param array $content The resulting array of `CleanedText`s
	 *  and `SegmentBreak`s.
	 * @param DOMNode $node The top node to add from.
	 */

	private static function addContent(
		&$content,
		$node
	) {
		global $wgWikispeechSegmentBreakingTags;
		if ( !self::matchesRemove( $node ) ) {
			foreach ( $node->childNodes as $child ) {
				if (
					!self::lastElement( $content ) instanceof SegmentBreak &&
					in_array(
						$child->nodeName,
						$wgWikispeechSegmentBreakingTags
					)
				) {
					// Add segment breaks for start tags specified in
					// the config, unless the previous item is a
					// break.
					array_push( $content, new SegmentBreak() );
				}
				if ( $child->nodeType == XML_TEXT_NODE ) {
					// Remove the path to the dummy node and instead
					// add "." to match when used with context.
					$path = preg_replace(
						'!^/meta/dummy!',
						'.',
						$child->getNodePath()
					);
					$text = new CleanedText( $child->textContent, $path );
					array_push( $content, $text );
				} else {
					self::addContent(
						$content,
						$child
					);
				}
				if (
					!self::lastElement( $content ) instanceof SegmentBreak &&
					in_array(
						$child->nodeName,
						$wgWikispeechSegmentBreakingTags
					)
				) {
					// Add segment breaks for end tags specified in
					// the config.
					array_push( $content, new SegmentBreak() );
				}
			}
		}
	}

	/**
	 * Check if a node matches criteria for removal.
	 *
	 * The node is compared to the removal criteria from the
	 * configuration, to determine if it should be removed completely.
	 *
	 * @since 0.0.1
	 * @param DOMNode $node The node to check.
	 * @return bool true if the node match removal criteria, otherwise
	 *  false.
	 */

	private static function matchesRemove( $node ) {
		global $wgWikispeechRemoveTags;
		if ( !array_key_exists( $node->nodeName, $wgWikispeechRemoveTags ) ) {
			// The node name isn't found in the removal list.
			return false;
		}
		$removeCriteria = $wgWikispeechRemoveTags[$node->nodeName];
		if ( $removeCriteria === true ) {
			// Node name is found and there are no extra criteria.
			return true;
		}
		if ( self::nodeHasClass( $node, $removeCriteria['class'] ) ) {
			// Node name and class name match.
			return true;
		}
		return false;
	}

	/**
	 * Check if a node has a class attribute, containing a string.
	 *
	 * Since this is for checking HTML tag classes, the class
	 * attribute, if present, is assumed to be a string of substrings,
	 * sepparated by spaces.
	 *
	 * @since 0.0.1
	 * @param DOMNode $node The node to check.
	 * @param string $className The name of the class to check for.
	 * @return bool true if the node's class attribute contain
	 *  $className, otherwise false.
	 */

	private static function nodeHasClass( $node, $className ) {
		$classNode = $node->attributes->getNamedItem( 'class' );
		if ( $classNode == null ) {
			return false;
		}
		$classString = $classNode->nodeValue;
		$nodeClasses = explode( ' ', $classString );
		if ( in_array( $className, $nodeClasses ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Get the last element in an array.
	 *
	 * @since 0.0.1
	 * @param array $array The array to get the last element from.
	 * @return The last element in the array, null if array is empty.
	 */

	private static function lastElement( $array ) {
		if ( !count( $array ) ) {
			return null;
		} else {
			return $array[count( $array ) - 1];
		}
	}
}
