<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0+
 */

require_once 'CleanedTag.php';

class Cleaner {

	/**
	 * Clean HTML tags from a string.
	 *
	 * Separates any HTML tags from the text.
	 *
	 * @since 0.0.1
	 * @param string $markedUpText Input text that may contain HTML
	 *  tags.
	 * @return array An array of nodes where tags are stored as
	 *  CleanedTags and text nodes as strings.
	 */

	public static function cleanHtml( $markedUpText ) {
		$dom = self::createDomDocument( $markedUpText );
		$tags = self::getTags( $markedUpText );
		// Start adding the nodes that are children of the dummy
		// element. To not add the actual dummy tags, index starts on
		// -1.
		$tagIndex = -1;
		$cleanedContent = [];
		self::addContent(
			$cleanedContent,
			$dom->documentElement->firstChild,
			$markedUpText,
			$tags,
			$tagIndex
		);
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
	 * Extract a list of tags from a string.
	 *
	 * Tags are extracted in the order they appear. This is done using
	 * regex since we need the exact string representation of tags to
	 * get their correct lengths.
	 *
	 * When a start tag is encountered, it's stored as an array
	 * containing the tag string and the start position of the
	 * tag. This array is then added to another array, which holds a
	 * start-end pair of tags.
	 *
	 * When an end tag is encountered, it's stored as an array
	 * containing the tag string and the end position of the tag. This
	 * array is then added to the array containing the corresponding
	 * start tag.
	 *
	 * Empty element tags are added as tag strings.
	 *
	 * @since 0.0.1
	 * @param string $markedUpText The string to extract tags from.
	 * @return array An array containing the found tags.
	 */

	private static function getTags( $markedUpText ) {
		$potentialTagBrackets = [];
		preg_match_all(
			'/[<>]/',
			$markedUpText,
			$potentialTagBrackets,
			PREG_SET_ORDER | PREG_OFFSET_CAPTURE
		);
		$tags = [];
		$startBracket = null;
		foreach ( $potentialTagBrackets as $match ) {
			// $match[0] is an array containing the matched string and it's
			// position.
			$bracketString = $match[0][0];
			if ( $bracketString == '<' ) {
				if ( $startBracket == null ) {
					$startBracket = $match[0];
				}
			} elseif ( $bracketString == '>' && $startBracket != null ) {
				$tagString = substr(
					$markedUpText,
					$startBracket[1],
					$match[0][1] - $startBracket[1] + 1
				);
				$bracketPosition = $startBracket[1];
				$startBracket = null;
				if ( self::isStartTag( $tagString ) ) {
					array_push( $tags, [ [
						'string' => $tagString,
						'position' => $bracketPosition
					] ] );
				} elseif ( self::isEndTag( $tagString ) ) {
					$startTagIndex = self::getCorrespondingStartTagIndex(
						$tags,
						$tagString
					);
					// Add the end tag to the array already containing the
					// start tag.
					array_push(
						$tags[$startTagIndex],
						[
							'string' => $tagString,
							'position' => $bracketPosition
						]
					);
				} elseif ( self::isEmptyElementTag( $tagString ) ) {
					array_push( $tags, $tagString );
				}
			}
		}
		return $tags;
	}

	/**
	 * Test if a string is a start tag.
	 *
	 * @since 0.0.1
	 * @param $tagString The string to test.
	 * @return true if $tagString is a start tag, else false.
	 */

	private static function isStartTag( $tagString ) {
		return substr( $tagString, 0, 2 ) != '</' &&
			substr( $tagString, -2 ) != '/>';
	}

	/**
	 * Test if a string is an end tag.
	 *
	 * @since 0.0.1
	 * @param $tagString The string to test.
	 * @return true if $tagString is an end tag, else false.
	 */

	private static function isEndTag( $tagString ) {
		return substr( $tagString, 0, 2 ) == '</';
	}

	/**
	 * Test if a string is an empty element tag.
	 *
	 * @since 0.0.1
	 * @param $tagString The string to test.
	 * @return true if $tagString is an empty element tag, else false.
	 */

	private static function isEmptyElementTag( $tagString ) {
		return substr( $tagString, -2 ) == '/>';
	}

	/**
	 * Get the index in $tags of the tag that starts the element which
	 * ends with $tagString.
	 *
	 * Traverses $tags backwards and tests if start tags are of the
	 * same type as the one in $tagString.
	 *
	 * @since 0.0.1
	 * @param array $tags Tag array, as returned from getTags().
	 * @param string $tagString the end tag to find start tag for, as
	 *  HTML string.
	 * @return int The index in $tags of the start tag found.
	 */

	private static function getCorrespondingStartTagIndex(
		$tags,
		$tagString
	) {
		for ( $i = count( $tags ) - 1; $i >= 0; $i -- ) {
			$tag = $tags[$i];
			if ( is_array( $tag ) && count( $tag ) == 1 ) {
				// Make sure the tag to test is an array, i.e. a start
				// tag, and that it doesn't have an end tag already.
				$startTagType = self::getTagName( $tag[0]['string'] );
				$endTagType = self::getTagName( $tagString );
				if ( $startTagType == $endTagType ) {
					return $i;
				}
			}
		}
	}

	/**
	 * Get the tag name from a tag string.
	 *
	 * @since 0.0.1
	 * @param string $tagString The tag as string.
	 * @return string The name of the tag in $tagString.
	 */

	private static function getTagName( $tagString ) {
		$nameMatch = null;
		preg_match( '!</?([^ />]+)( />)?!', $tagString, $nameMatch );
		$tagName = $nameMatch[1];
		return $tagName;
	}

	/**
	 * Recursively add content as either CleanedTags or strings.
	 *
	 * Goes through all the child nodes of $node and adds the
	 * corresponding content. If a child is a tag, it's added as a
	 * CleanedTag of the appropriate type (Start, End or Empty). If a
	 * child is a text node, the text is added as a string.
	 *
	 * @since 0.0.1
	 * @param array $content The resulting array of CleanedTags and
	 *  strings.
	 * @param DOMNode $node The top node to add from.
	 * @param string $source The HTML string that DOM is generated
	 *  from. Used for retrieveing element contents.
	 * @param array $tags Tag array, as generated by getTags().
	 * @param int $tagIndex The index of the next tag, from $tags.
	 */

	private static function addContent(
		&$content,
		$node,
		$source,
		$tags,
		&$tagIndex
	) {
		$startTagArray = null;
		$endTagArray = null;
		if ( $tagIndex >= 0 ) {
			// Don't add the dummy tag.
			if ( is_array( $tags[$tagIndex] ) ) {
				// If an item in $tags is an array, it holds arrays
				// for start and end tags.
				$startTagArray = $tags[$tagIndex][0];
				$endTagArray = $tags[$tagIndex][1];
				$cleanedStartTag = self::addStartTag(
					$content,
					$startTagArray['string']
				);
			} else {
				// If the tag is empty, just add it and return, since
				// there can't any child nodes.
				$emptyElementTagString = $tags[$tagIndex];
				self::addEmptyElementTag( $content, $emptyElementTagString );
				return;
			}
		}
		if ( self::matchesRemove( $node ) ) {
			// When a tag is removed, skip forward a number of tags
			// equal to the number of nodes under that tag.
			$tagIndex += self::getNumberOfDescendants( $node );
			$cleanedStartTag->contentLength = self::getContentLength(
				$startTagArray,
				$endTagArray,
				$source
			);
		} else {
			self::addChildren(
				$content,
				$node,
				$source,
				$tags,
				$tagIndex
			);
		}
		if ( $endTagArray != null ) {
			array_push(
				$content,
				new CleanedEndTag( $endTagArray['string'] )
			);
		}
	}

	/**
	 * Add a CleanedStartTag to an array.
	 *
	 * @since 0.0.1
	 * @param array $content The array that the tag representation is
	 *  added to.
	 * @param string $tagString A string representation of a tag.
	 * @return CleanedStartTag The added tag representation.
	 */

	private static function addStartTag( &$content, $tagString ) {
		$cleanedStartTag = new CleanedStartTag( $tagString );
		array_push( $content, $cleanedStartTag );
		return $cleanedStartTag;
	}

	/**
	 * Add an CleanedEmptyElementTag to an array.
	 *
	 * @since 0.0.1
	 * @param array $content The array that the tag representation is
	 *  added to.
	 * @param string $tagString String representation of a tag.
	 */

	private static function addEmptyElementTag( &$content, $tagString ) {
		$cleanedTag = new CleanedEmptyElementTag( $tagString );
		array_push( $content, $cleanedTag );
	}

	/**
	 * Get the number of nodes that are descendants of a given node.
	 *
	 * @since 0.0.1
	 * @param DOMNode $node The node to get number of descendants of.
	 * @return int The number of decendants of $node.
	 */

	private static function getNumberOfDescendants( $node ) {
		if ( !$node->hasChildNodes() ) {
			return 0;
		}
		$numberOfDescendants = 0;
		foreach ( $node->childNodes as $child ) {
			if ( $child->nodeType != XML_TEXT_NODE ) {
				$numberOfDescendants +=
					1 + self::getNumberOfDescendants( $child );
			}
		}
		return $numberOfDescendants;
	}

	/**
	 * Get the length of the element content.
	 *
	 * The element content is the string between the start tag and the
	 * end tag, excluding the tags themselves.
	 *
	 * @since 0.0.1
	 * @param array $startTagArray Array containing string and
	 *  position for the start tag.
	 * @param array $endTagArray Array containing string and
	 *  position for the end tag.
	 * @param string $source The HTML string that DOM is generated
	 *  from. Used for retrieveing element contents.
	 */

	private static function getContentLength(
		$startTagArray,
		$endTagArray,
		$source
	) {
		$elementContentStartPosition =
			$startTagArray['position'] + strlen( $startTagArray['string'] );
		$length = $endTagArray['position'] - $elementContentStartPosition;
		$elementContentString =
			substr( $source, $elementContentStartPosition, $length );
		return strlen( $elementContentString );
	}

	/**
	 * Add content for child nodes to an array.
	 *
	 * @since 0.0.1
	 * @param array $content The array that the children are added to.
	 * @param DOMNode $node Add content for the children of this node.
	 * @param string $source The HTML string that DOM is generated
	 *  from. Used for retrieveing element contents.
	 * @param array $tags Tag array, as generated by getTags().
	 * @param int $tagIndex The index of the next tag, from $tags.
	 */

	private static function addChildren(
		&$content,
		$node,
		$source,
		$tags,
		&$tagIndex
	) {
		foreach ( $node->childNodes as $child ) {
			if ( $child->nodeType == XML_TEXT_NODE ) {
				array_push( $content, $child->textContent );
			} else {
				// Nodes are handled even if their parents are
				// removed, to not get the DOM nodes out of sync with
				// $tags.
				$tagIndex += 1;
				self::addContent(
					$content,
					$child,
					$source,
					$tags,
					$tagIndex
				);
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
}
