<?php

namespace MediaWiki\Wikispeech\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use DOMComment;
use DOMDocument;
use DOMNode;
use DOMXPath;
use LogicException;

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
	 * @var array
	 */
	private $removeTags;

	/**
	 * An array of tags that should add a segment break during cleaning.
	 *
	 * @var array
	 */
	private $segmentBreakingTags;

	/**
	 * An array of `CleanedText`s and `SegmentBreak`s.
	 *
	 * @var SegmentContent[]
	 */
	private $cleanedContent;

	/**
	 * @param array $removeTags An array of tags that should be
	 *  removed completely during cleaning.
	 * @param array $segmentBreakingTags An array of `CleanedText`s
	 *  and `SegmentBreak`s.
	 */
	public function __construct( $removeTags, $segmentBreakingTags ) {
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
	 * @return SegmentContent[] An array of `CleanedText`s and `SegmentBreak`s
	 *  representing text nodes.
	 */
	public function cleanHtml( $markedUpText ): array {
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
			$this->cleanedContent &&
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
	 * @param string $markedUpText The string to create the
	 *  DOMDocument.
	 * @return DOMDocument The created DOMDocument.
	 */
	private static function createDomDocument( $markedUpText ): DOMDocument {
		$dom = new DOMDocument();
		// Add encoding information and wrap the input text in a dummy
		// tag to prevent p tags from being added for text nodes.
		$wrappedText = '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>' .
			'<dummy>' . $markedUpText . '</dummy></head>';
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
	private function addContent( $node ): void {
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
					$this->cleanedContent[] = new SegmentBreak();
				}
				if ( $child->nodeType == XML_TEXT_NODE ) {
					// Remove the path to the dummy node and instead
					// add "." to match when used with context.
					$path = preg_replace(
						'!^/meta/dummy' . '!',
						'.',
						$child->getNodePath()
					);
					$this->cleanedContent[] = new CleanedText( $child->textContent, $path );
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
					$this->cleanedContent[] = new SegmentBreak();
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
	private function matchesRemove( $node ): bool {
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
	 * separated by spaces.
	 *
	 * @since 0.0.1
	 * @param DOMNode $node The node to check.
	 * @param string $className The name of the class to check for.
	 * @return bool true if the node's class attribute contain
	 *  $className, otherwise false.
	 */
	private static function nodeHasClass( $node, $className ): bool {
		$classNode = $node->attributes->getNamedItem( 'class' );
		if ( $classNode == null ) {
			return false;
		}
		$classString = $classNode->nodeValue;
		$nodeClasses = explode( ' ', $classString );
		return in_array( $className, $nodeClasses );
	}

	/**
	 * Get the last element in an array.
	 *
	 * @since 0.0.1
	 * @param array $array The array to get the last element from.
	 * @return mixed|null The last element in the array, null if array is empty.
	 */
	private static function lastElement( $array ) {
		if ( !count( $array ) ) {
			return null;
		} else {
			return $array[count( $array ) - 1];
		}
	}

	/**
	 * Cleans title and content.
	 *
	 * @since 0.1.10
	 * @param string $displayTitle
	 * @param string $pageContent
	 * @return SegmentContent[] Title and content represented as `CleanedText`s and `SegmentBreak`s
	 * @throws LogicException If segmented title text is not an instance of CleanedText
	 */
	public function cleanHtmlDom(
		string $displayTitle,
		string $pageContent
	): array {
		// Clean HTML.
		$cleanedText = null;
		// Parse latest revision, using parser cache.
		$cleanedText = $this->cleanHtml( $pageContent );
		// Create a DOM for the title to get the Xpath, in case there
		// are elements within the title. This happens e.g. when the
		// title is italicized.
		$dom = new DOMDocument();
		$dom->loadHTML(
			'<h1>' . $displayTitle . '</h1>',
			LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED
		);
		$xpath = new DOMXPath( $dom );
		$titleSegments = [];
		$i = 0;
		foreach ( $this->cleanHtml( $displayTitle ) as $titlePart ) {
			if ( !$titlePart instanceof CleanedText ) {
				throw new LogicException(
					'Segmented title is not an instance of CleanedText!'
				);
			}

			$node = $xpath->evaluate( '//text()' )->item( $i );
			$titlePart->setPath( '/' . $node->getNodePath() );
			$titleSegments[] = $titlePart;
			$titleSegments[] = new SegmentBreak();
			$i++;
		}
		array_pop( $titleSegments );
		if ( $cleanedText ) {
			$cleanedText = array_merge(
				$titleSegments,
				[ new SegmentBreak() ],
				$cleanedText
			);
		} else {
			$cleanedText = $titleSegments;
		}
		return $cleanedText;
	}

}
