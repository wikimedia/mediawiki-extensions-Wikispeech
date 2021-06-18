<?php

namespace MediaWiki\Wikispeech\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * Used for dividing text into segments, that can then be sent to the
 * Speechoid service. Also calculates values for variables that are needed
 * for highlighting.
 *
 * @since 0.1.10
 */
abstract class Segmenter {

	/**
	 * Divide a cleaned content array into segments, one for each sentence.
	 *
	 * @since 0.1.10
	 * @param SegmentContent[] $cleanedContent An array of items returned by `Cleaner::cleanHtml()`.
	 * @return Segment[] An array of segments, each containing the `CleanedText's in that segment.
	 */
	abstract public function segmentSentences( array $cleanedContent ): array;

	/**
	 * Used to evaluate hash of segments, the primary key for stored utterances.
	 *
	 * @since 0.1.10
	 * @param Segment $segment The segment to be evaluated.
	 * @return string SHA256 message digest
	 */
	public function evaluateHash( Segment $segment ): string {
		$context = hash_init( 'sha256' );
		foreach ( $segment->getContent() as $part ) {
			hash_update( $context, $part->getString() );
			hash_update( $context, "\n" );
		}
		return hash_final( $context );
		// Uncommenting below block can be useful during creation of
		// new test cases as you might need to figure out hashes.
		//LoggerFactory::getInstance( 'Segmenter' )
		//	->info( __METHOD__ . ': {segement} : {hash}', [
		//		'segment' => $segment,
		//		'hash' => $hash
		//	] );
	}

}
