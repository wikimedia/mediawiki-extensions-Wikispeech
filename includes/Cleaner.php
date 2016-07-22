<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0+
 */

class Cleaner {

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
		libxml_use_internal_errors( true );
		$dom->loadHTML(
			$wrappedText,
			LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED
		);
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
	 * Check if a tag matches criteria for removal.
	 *
	 * The criteria are defined by $wgWikispeechRemoveTags, which is a map
	 * where the keys are tag names. If the value is true, the tag will be
	 * removed. If the value is an array, it defines further criteria,
	 * currently only class name, which needs to match for the tag to be
	 * removed.
	 *
	 * The value may be false, which means the tag won't be removed. This is to
	 * allow overriding default values in LocalSettings.php, but is otherwise
	 * not required.
	 *
	 * @since 0.0.1
	 * @param DOMNode $node The node for the tag to check.
	 * @return bool true if the tag match removal criteria, otherwise false.
	 */

	private static function matchesRemove( $node ) {
		global $wgWikispeechRemoveTags;
		if ( !array_key_exists( $node->nodeName, $wgWikispeechRemoveTags ) ) {
			// The node name isn't found in the removal list.
			return false;
		}
		$removeCriteria = $wgWikispeechRemoveTags[ $node->nodeName ];
		if ( $removeCriteria === true ) {
			// Node name is found and there are no extra criteria.
			return true;
		}
		if ( self::nodeHasClass( $node, $removeCriteria[ 'class' ] ) ) {
			// Node name and class name match.
			return true;
		}
		return false;
	}

	/**
	 * Check if a node has a class attribute, containing a string.
	 *
	 * Since this is for checking HTML tag classes, the class attribute, if
	 * present, is assumed to be a string of substrings, sepparated by spaces.
	 *
	 * @since 0.0.1
	 * @param DOMNode $node The node to check.
	 * @param string $className The name of the class to check for.
	 * @return bool true if the node's class attribute contain $className,
	 * otherwise false.
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
}
