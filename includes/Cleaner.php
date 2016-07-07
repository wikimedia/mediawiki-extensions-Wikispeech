<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-3.0+
 */

class Cleaner {
	// HTML tags that should be removes altogether. If value is null, there
	// are no further criteria. If the value contains 'class', the tag
	// also needs to have that class to be removed.
	private static $REMOVE_TAGS = [
		'editsection' => null,
		'toc' => null,
		'table' => null,
		'sup' => [ 'class' => 'reference' ],
		'div' => [ 'class' => 'thumb' ],
		'ul' => null,
		'ol' => null
	];

	/**
	 * Clean HTML tags by removing some altogether and keeping content
	 * for some.
	 *
	 * @since 0.0.1
	 * @param string $markedUpText Input text that may contain HTML tags.
	 * @return string The text with HTML tags removed/replaced with
	 * contents.
	 */

	public static function cleanHtml( $markedUpText ) {
		$dom = new DOMDocument();
		// Add encoding information and wrap the input text in a dummy tag
		// to prevent p tags from being added for text nodes.
		// @codingStandardsIgnoreStart
		$wrappedText = '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/><dummy>' . $markedUpText . '</dummy></head>';
		// @codingStandardsIgnoreEnd
		$dom->loadHTML(
			$wrappedText,
			LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED );
		$cleanedText = self::getTextContent( $dom->documentElement );
		return $cleanedText;
	}

	/**
	 * Recursively get the text from a node and its children.
	 *
	 * @since 0.0.1
	 * @param DOMNode $node The top node to get text from.
	 * @return string The cleaned text from the nodes.
	 */

	private static function getTextContent( $node ) {
		$content = '';
		if ( !self::matchesRemove( $node ) ) {
			foreach ( $node->childNodes as $child ) {
				if ( $child->nodeType == XML_TEXT_NODE ) {
					$content .= $child->textContent;
				} else {
					$content .= self::getTextContent( $child );
				}
			}
		}
		return $content;
	}

	/**
	 * Check if a node matches criteria for removal.
	 *
	 * @since 0.0.1
	 * @param DOMNode $node The node to check.
	 * @return bool true if the node match removal criteria, otherwise false.
	 */

	private static function matchesRemove( $node ) {
		if ( !array_key_exists( $node->nodeName, self::$REMOVE_TAGS ) ) {
			// The node name isn't found in the removal list.
			return false;
		}
		$removeCriteria = self::$REMOVE_TAGS[ $node->nodeName ];
		if ( $removeCriteria == null ) {
			// Node name matches and there are no extra criteria.
			return true;
		}
		$classNode = $node->attributes->getNamedItem( 'class' );
		if ( array_key_exists( 'class', $removeCriteria ) &&
			$classNode != null
		) {
			$classString = $classNode->nodeValue;
			$nodeClasses = explode( ' ', $classString );
			if ( in_array( $removeCriteria[ 'class' ], $nodeClasses ) ) {
				// Node name and class name match.
				return true;
			}
		}
		return false;
	}
}
