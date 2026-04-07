<?php

namespace MediaWiki\Wikispeech\Segment\PartOfContent;

use DOMElement;
use MediaWiki\Wikispeech\Segment\SegmentContent;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * Base class for part of content.
 *
 * A part of content (POC) is any piece of the content that is not just text
 * and therefore should be handled in a particular way. Mostly this means adding
 * extra text to a segment to announce its presence. Sometimes this can include
 * parameters describing the POC.
 *
 * @since 0.1.15
 */
abstract class PartOfContent extends SegmentContent {
	/**
	 * Create a part of content if an element matches its criteria.
	 *
	 * @since 0.1.15
	 * @param DOMElement $element
	 * @return self | null
	 */
	abstract public static function fromElement( DOMElement $element ): self|null;
}
