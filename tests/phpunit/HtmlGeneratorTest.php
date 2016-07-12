<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0+
 */

require_once __DIR__ . '/../../includes/HtmlGenerator.php';

class HtmlGeneratorTest extends MediaWikiTestCase {
	public function testGenerateUtterancesHtml() {
		$utterancesStrings = [ 'An utterance.', 'Another utterance.' ];
		$actualHtml = HtmlGenerator::generateUtterancesHtml(
			$utterancesStrings
		);
		// @codingStandardsIgnoreStart
		$expectedHtml = '<utterances hidden=""><utterance id="utterance-0"><text>An utterance.</text><audio></audio></utterance><utterance id="utterance-1"><text>Another utterance.</text><audio></audio></utterance></utterances>';
		// @codingStandardsIgnoreEnd
		$this->assertEquals( $expectedHtml, $actualHtml );
	}

	public function testGenerateUtteranceContainingNumberSign() {
		// @codingStandardsIgnoreStart
		$utterancesStrings = [ 'Blonde on Blonde spawned two singles that were top-twenty hits in the US: "Rainy Day Women #12 & 35" and "I Want You".'
		];
		// @codingStandardsIgnoreEnd
		$actualHtml = HtmlGenerator::generateUtterancesHtml(
			$utterancesStrings
		);
		// @codingStandardsIgnoreStart
		$expectedHtml = '<utterances hidden=""><utterance id="utterance-0"><text>Blonde on Blonde spawned two singles that were top-twenty hits in the US: "Rainy Day Women #12 & 35" and "I Want You".</text><audio></audio></utterance></utterances>';
		// @codingStandardsIgnoreEnd
		$this->assertEquals( $expectedHtml, $actualHtml );
	}

	public function testDontGenerateUtterancesHtmlForNoUtterances() {
		$utterancesStrings = [];
		$actualHtml = HtmlGenerator::generateUtterancesHtml(
			$utterancesStrings
		);
		$expectedHtml = '';
		$this->assertEquals( $expectedHtml, $actualHtml );
	}
}
