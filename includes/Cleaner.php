<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * Used for cleaning text with HTML markup. The cleaned text is used
 * as input for `Segmenter`.
 *
 * @since 0.0.1
 */

class Cleaner {
	/**
	 * An array of tags that should be removed completely during cleaning.
	 *
	 * @var array $removeTags
	 */

	private $removeTags;

	/**
	 * An array of tags that should add a segment break during cleaning.
	 *
	 * @var array $segmentBreakingTags
	 */

	private $segmentBreakingTags;

	/**
	 * An array of `CleanedText`s and `SegmentBreak`s.
	 *
	 * @var array $cleanedContent
	 */

	private $cleanedContent;

	/**
	 * @param array|null $removeTags An array of tags that should be removed
	 *  completely during cleaning.
	 * @param array|null $segmentBreakingTags An array of `CleanedText`s and
	 *  `SegmentBreak`s.
	 */
	function __construct( $removeTags, $segmentBreakingTags ) {
		if ( $removeTags == null ) {
			$removeTags = [];
		}
		if ( $segmentBreakingTags == null ) {
			$segmentBreakingTags = [];
		}
		$this->removeTags = $removeTags;
		$this->segmentBreakingTags = $segmentBreakingTags;
	}

	/**
	 * Clean HTML tags from a string.
	 *
	 * Separates any HTML tags from the text.
	 *
	 * @since 0.0.1
	 * @param string $markedUpText Input text that may contain HTML
	 *  tags.
	 * @return array An array of `CleanedText`s and `SegmentBreak`s
	 *  representing text nodes.
	 */
	public function cleanHtml( $markedUpText ) {
		$dom = self::createDomDocument( $markedUpText );
		$xpath = new DOMXPath( $dom );
		// Only add elements below the dummy element. These are the
		// elements from the original HTML.
		$top = $xpath->evaluate( '/meta/dummy' )->item( 0 );
		$this->cleanedContent = [];
		$this->addContent( $top );
		// Remove any segment break at the start or end of the array,
		// since they won't do anything.
		if (
			count( $this->cleanedContent ) &&
			$this->cleanedContent[0] instanceof SegmentBreak
		) {
			array_shift( $this->cleanedContent );
		}
		if ( self::lastElement( $this->cleanedContent ) instanceof SegmentBreak ) {
			array_pop( $this->cleanedContent );
		}
		return $this->cleanedContent;
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
		global $wgWikispeechContentWrapperTagName;
		$contentTag = '<' . $wgWikispeechContentWrapperTagName . '>';
		// @codingStandardsIgnoreStart
		$wrappedText = '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/><dummy>' .
			$markedUpText .
			'</dummy></head>';
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
	 * @param DOMNode $node The top node to add from.
	 */
	private function addContent( $node ) {
		if ( !$node instanceof DOMComment && !$this->matchesRemove( $node ) ) {
			foreach ( $node->childNodes as $child ) {
				if (
					!self::lastElement( $this->cleanedContent )
						instanceof SegmentBreak &&
					in_array(
						$child->nodeName,
						$this->segmentBreakingTags
					)
				) {
					// Add segment breaks for start tags specified in
					// the config, unless the previous item is a break
					// or this is the first item.
					array_push( $this->cleanedContent, new SegmentBreak() );
				}
				if ( $child->nodeType == XML_TEXT_NODE ) {
					// Remove the path to the dummy node and instead
					// add "." to match when used with context.
					$path = preg_replace(
						'!^/meta/dummy' . '!',
						'.',
						$child->getNodePath()
					);
					$text = new CleanedText( $child->textContent, $path );
					array_push( $this->cleanedContent, $text );
				} else {
					$this->addContent( $child );
				}
				if (
					!self::lastElement( $this->cleanedContent ) instanceof SegmentBreak &&
					in_array(
						$child->nodeName,
						$this->segmentBreakingTags
					)
				) {
					// Add segment breaks for end tags specified in
					// the config.
					array_push( $this->cleanedContent, new SegmentBreak() );
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
	private function matchesRemove( $node ) {
		if ( !array_key_exists( $node->nodeName, $this->removeTags ) ) {
			// The node name isn't found in the removal list.
			return false;
		}
		$removeCriteria = $this->removeTags[$node->nodeName];
		if ( $removeCriteria === true ) {
			// Node name is found and there are no extra criteria.
			return true;
		} elseif ( is_array( $removeCriteria ) ) {
			// If there are multiple classes for a tag, check if any
			// of them match.
			foreach ( $removeCriteria as $class ) {
				if ( self::nodeHasClass( $node, $class ) ) {
					return true;
				}
			}
		} elseif ( self::nodeHasClass( $node, $removeCriteria ) ) {
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
