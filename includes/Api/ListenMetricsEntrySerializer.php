<?php

namespace MediaWiki\Wikispeech\Api;

/**
 * @file
 * @ingroup API
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * @since 0.1.10
 */
class ListenMetricsEntrySerializer {

	/**
	 * @since 0.1.10
	 * @param ListenMetricsEntry $instance
	 * @return array Associative array that can be encoded using json_encode
	 */
	public function serialize( ListenMetricsEntry $instance ): array {
		$array = [
			'timestamp' => $instance->getTimestamp()->getTimestamp( TS_UNIX ),
			'segmentIndex' => $instance->getSegmentIndex(),
			'segmentHash' => $instance->getSegmentHash(),
			'remoteWikiHash' => $instance->getRemoteWikiHash(),
			'consumerUrl' => $instance->getConsumerUrl(),
			'pageTitle' => $instance->getPageTitle(),
			'pageId' => $instance->getPageId(),
			'pageRevisionId' => $instance->getPageRevisionId(),
			'language' => $instance->getLanguage(),
			'voice' => $instance->getVoice(),
			'microsecondsSpent' => $instance->getMicrosecondsSpent(),
			'utteranceSynthesized' => $instance->getUtteranceSynthesized(),
			'millisecondsSpeechInUtterance' => $instance->getMillisecondsSpeechInUtterance(),
			'charactersInSegment' => $instance->getCharactersInSegment(),
		];
		if ( $instance->getId() !== null ) {
			$array['id'] = $instance->getId();
		}
		return $array;
	}
}
