<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0+
 */

require_once __DIR__ . '/../../includes/HtmlGenerator.php';
require_once 'Util.php';

class HtmlGeneratorTest extends MediaWikiTestCase {
	public function testCreateUtteranceElement() {
		$segment = [
			'position' => 0,
			'content' => [ 'An utterance.' ],
		];
		$element = Util::call(
			'HtmlGenerator',
			'createUtteranceElement',
			new DOMDocument(),
			$segment,
			0
		);
		$this->assertEquals( 'utterance', $element->nodeName );
		$this->assertEquals( 'utterance-0', $element->getAttribute( 'id' ) );
		$this->assertEquals( 0, $element->getAttribute( 'position' ) );
		$this->assertEquals( 'content', $element->firstChild->nodeName );
		$this->assertEquals(
			'An utterance.',
			$element->firstChild->nodeValue
		);
	}

	public function testCreateUtteranceContainingNumberSign() {
		$segment = [
			'position' => 0,
			'content' => [ 'This is #1.' ]
		];
		$element = Util::call(
			'HtmlGenerator',
			'createUtteranceElement',
			new DOMDocument,
			$segment,
			0
		);
		$this->assertEquals( 'This is #1.', $element->firstChild->nodeValue );
	}

	public function testCreateUtteranceContainingTagBrackets() {
		$segment = [
			'position' => 0,
			'content' => [ 'This is not really a <tag>.' ]
		];
		$element = Util::call(
			'HtmlGenerator',
			'createUtteranceElement',
			new DOMDocument,
			$segment,
			0
		);
		$this->assertEquals(
			'This is not really a <tag>.',
			$element->firstChild->nodeValue
		);
	}

	public function testDontCreateUtterancesHtmlForNoUtterances() {
		$segments = [];
		$html = HtmlGenerator::createUtterancesHtml( $segments );
		$expectedHtml = '';
		$this->assertEquals( $expectedHtml, $html );
	}

	public function testCreateUtterancesMultipleUtterances() {
		$segments = [
			[
				'position' => 0,
				'content' => [ 'Sentence 1.' ]
			],
			[
				'position' => 11,
				'content' => [ ' Sentence 2.' ]
			]

		];
		$actualHtml = HtmlGenerator::createUtterancesHtml( $segments );
		// @codingStandardsIgnoreStart
		$expectedHtml =
			'<utterances hidden=""><utterance id="utterance-0" position="0"><content>Sentence 1.</content></utterance><utterance id="utterance-1" position="11"><content> Sentence 2.</content></utterance></utterances>';
		// @codingStandardsIgnoreEnd
		$this->assertEquals( $expectedHtml, $actualHtml );
	}

	public function testCreateUtterancesContainingRemovedTags() {
		$segments = [
			[
				'position' => 0,
				'content' => [
					'Here is a ',
					Util::createStartTag( '<i>' ),
					'tag',
					new CleanedEndTag( '</i>' )
				]
			]
		];
		$html = HtmlGenerator::createUtterancesHtml( $segments );
		// @codingStandardsIgnoreStart
		$expectedHtml =
			'<utterances hidden=""><utterance id="utterance-0" position="0"><content>Here is a <cleaned-tag>i</cleaned-tag>tag<cleaned-tag>/i</cleaned-tag></content></utterance></utterances>';
		// @codingStandardsIgnoreEnd
		$this->assertEquals( $expectedHtml, $html );
	}
}
