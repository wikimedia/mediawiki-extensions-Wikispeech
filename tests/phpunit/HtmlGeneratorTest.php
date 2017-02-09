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
			'startOffset' => 0,
			'endOffset' => 12,
			'content' => [ new CleanedText( 'An utterance.', [ 0, 1 ] ) ],
		];
		$utteranceElement = Util::call(
			'HtmlGenerator',
			'createUtteranceElement',
			new DOMDocument(),
			$segment,
			0
		);
		$this->assertEquals( 'utterance', $utteranceElement->nodeName );
		$this->assertEquals(
			'utterance-0',
			$utteranceElement->getAttribute( 'id' )
		);
		$this->assertEquals(
			0,
			$utteranceElement->getAttribute( 'start-offset' )
		);
		$this->assertEquals(
			12,
			$utteranceElement->getAttribute( 'end-offset' )
		);
		$xpath = self::getXpath( $utteranceElement );
		$contentElement = $xpath->evaluate( '/utterance/content' )->item( 0 );
		$this->assertEquals( 'content', $contentElement->nodeName );
		$textElement = $xpath->query( '/utterance/content/text' )->item( 0 );
		$this->assertEquals( 'text', $textElement->nodeName );
		$this->assertEquals( 'An utterance.', $textElement->nodeValue );
		$this->assertEquals( '0,1', $textElement->getAttribute( 'path' ) );
	}

	/**
	 * Create a DOMXPath for an element.
	 *
	 * @since 0.0.1
	 * @param DOMElement $element Element to create the XPath for.
	 * @return DOMXPath The created XPath.
	 */

	private function getXpath( $element ) {
		// Appending the node is needed for XPath functions to work.
		$element->ownerDocument->appendChild( $element );
		return new DOMXPath( $element->ownerDocument );
	}

	public function testCreateUtteranceContainingNumberSign() {
		$segment = [
			'startOffset' => null,
			'endOffset' => null,
			'content' => [ new CleanedText( 'This is #1.', [] ) ]
		];
		$utteranceElement = Util::call(
			'HtmlGenerator',
			'createUtteranceElement',
			new DOMDocument(),
			$segment,
			0
		);
		$xpath = self::getXpath( $utteranceElement );
		$textElement = $xpath->query( '/utterance/content/text' )->item( 0 );
		$this->assertEquals( 'This is #1.', $textElement->nodeValue );
	}

	public function testCreateUtteranceContainingTagBrackets() {
		$segment = [
			'startOffset' => null,
			'endOffset' => null,
			'content' => [ new CleanedText( 'This is not really a <tag>.', [] ) ]
		];
		$utteranceElement = Util::call(
			'HtmlGenerator',
			'createUtteranceElement',
			new DOMDocument(),
			$segment,
			0
		);
		$xpath = self::getXpath( $utteranceElement );
		$textElement = $xpath->query( '/utterance/content/text' )->item( 0 );
		$this->assertEquals(
			'This is not really a <tag>.',
			$textElement->nodeValue
		);
	}

	public function testDontCreateUtterancesHtmlForNoUtterances() {
		$segments = [];
		$utterancesElement = Util::call(
			'HtmlGenerator',
			'createUtterancesElement',
			new DOMDocument(),
			$segments
		);
		$this->assertNull( $utterancesElement );
	}

	public function testCreateUtterancesMultipleUtterances() {
		$segments = [
			[
				'startOffset' => null,
				'endOffset' => null,
				'content' => [ new CleanedText( 'Sentence 1.', [] ) ]
			],
			[
				'startOffset' => null,
				'endOffset' => null,
				'content' => [ new CleanedText( ' Sentence 2.', [] ) ]
			]
		];
		$utterancesElement = Util::call(
			'HtmlGenerator',
			'createUtterancesElement',
			new DOMDocument(),
			$segments
		);
		$utteranceElements =
			$utterancesElement->getElementsByTagName( 'utterance' );
		$this->assertEquals( 2, $utteranceElements->length );
		$this->assertTrue( $utterancesElement->hasAttribute( 'hidden' ) );
	}

	public function testCreateUtterancesContainingCleanedTags() {
		$segment = [
			'startOffset' => null,
			'endOffset' => null,
			'content' => [
				new CleanedText( 'Here is a ', [] ),
				new CleanedTag( '<i>' ),
				new CleanedText( 'tag', [] ),
				new CleanedTag( '</i>', [] )
			]
		];
		$utteranceElement = Util::call(
			'HtmlGenerator',
			'createUtteranceElement',
			new DOMDocument(),
			$segment,
			0
		);
		$xpath = self::getXpath( $utteranceElement );
		$contentChildren = $xpath->evaluate( '/utterance/content/*' );
		$this->assertEquals( 'text', $contentChildren->item( 0 )->nodeName );
		$this->assertEquals(
			'Here is a ',
			$contentChildren->item( 0 )->nodeValue
		);
		$this->assertEquals(
			'cleaned-tag',
			$contentChildren->item( 1 )->nodeName
		);
		$this->assertEquals(
			'i',
			$contentChildren->item( 1 )->nodeValue
		);
		$this->assertEquals( 'text', $contentChildren->item( 2 )->nodeName );
		$this->assertEquals(
			'tag',
			$contentChildren->item( 2 )->nodeValue
		);
		$this->assertEquals(
			'cleaned-tag',
			$contentChildren->item( 3 )->nodeName
		);
		$this->assertEquals(
			'/i',
			$contentChildren->item( 3 )->nodeValue
		);
	}
}
