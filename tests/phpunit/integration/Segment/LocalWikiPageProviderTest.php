<?php

namespace MediaWiki\Wikispeech\Tests\Integration\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use ConfigFactory;
use HashBagOStuff;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Wikispeech\Segment\CleanedText;
use MediaWiki\Wikispeech\Segment\Segment;
use MediaWiki\Wikispeech\Segment\SegmentPageFactory;
use MediaWiki\Wikispeech\Tests\WikiPageTestUtil;
use MediaWikiIntegrationTestCase;
use RequestContext;
use Title;
use WANObjectCache;

/**
 * @group Database
 * @covers \MediaWiki\Wikispeech\Segment\LocalWikiPageProvider
 */
class LocalWikiPageProviderTest extends MediaWikiIntegrationTestCase {

	/** @var \WANObjectCache */
	private $cache;

	protected function setUp(): void {
		parent::setUp();
		$this->cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
	}

	protected function tearDown(): void {
		WikiPageTestUtil::removeCreatedPages();
		parent::tearDown();
	}

	public function testSegmentPage_contentContainsSentences_giveTitleAndContent() {
		$titleString = 'Page';
		$content = 'Sentence 1. Sentence 2. Sentence 3.';
		WikiPageTestUtil::addPage( $titleString, $content );
		$title = Title::newFromText( $titleString );
		$expectedSegments = [
			new Segment(
				[ new CleanedText( 'Page', '//h1/span/text()' ) ],
				0,
				3,
				'cd2c3fb786ef2a8ba5430f54cde3d468c558647bf0fd777b437e8138e2348e01'
			),
			new Segment(
				[ new CleanedText( 'Sentence 1.', './div/p/text()' ) ],
				0,
				10,
				'76ca3069cee56491f5b2f465c4e9b57b7fb74ebc12eecc0cd6aad965ea7e247e'
			),
			new Segment(
				[ new CleanedText( 'Sentence 2.', './div/p/text()' ) ],
				12,
				22,
				'33dc64326df9f4b281fc9d680f89423f3261d1056d857a8263d46f7904a705ac'
			),
			new Segment(
				[ new CleanedText( 'Sentence 3.', './div/p/text()' ) ],
				24,
				34,
				'bae6b55875cd8e8bee3b760773f36a3a25e2d6fa102f168aade3d49f77c34da6'
			)
		];
		$segmentPageFactory = new SegmentPageFactory(
			$this->cache,
			$this->createMock( ConfigFactory::class )
		);
		$segments = $segmentPageFactory
			->setUseRevisionPropertiesCache( false )
			->setUseSegmentsCache( false )
			->setRemoveTags( [] )
			->setSegmentBreakingTags( [] )
			->setContextSource( new RequestContext() )
			->setRevisionStore( $this->createMock( RevisionStore::class ) )
			->segmentPage(
				$title,
				null
			)->getSegments()->getSegments();
		$this->assertEquals( $expectedSegments, $segments );
	}

	public function testSegmentPage_setDisplayTitle_segmentDisplayTitle() {
		$titleString = 'Title';
		$content = '{{DISPLAYTITLE:title}}Some content text.';
		WikiPageTestUtil::addPage( $titleString, $content );
		$title = Title::newFromText( $titleString );
		$expectedSegments = [
			new Segment(
				[ new CleanedText( 'title', '//h1/text()' ) ],
				0,
				4,
				'1ec72b6861fee9926d828a734ddbd533a1eb1a983d42acec571720deb2b92018'
			),
			new Segment(
				[ new CleanedText( 'Some content text.', './div/p/text()' ) ],
				0,
				17,
				'3eb8e91dc31a98b63aebe35a1229364deced3f3abbc26eb09fe67394e5cd5c0f'
			)
		];
		$segmentPageFactory = new SegmentPageFactory(
			$this->cache,
			$this->createMock( ConfigFactory::class )
		);
		$segments = $segmentPageFactory
			->setUseRevisionPropertiesCache( false )
			->setUseSegmentsCache( false )
			->setRemoveTags( [] )
			->setSegmentBreakingTags( [] )
			->setContextSource( new RequestContext() )
			->setRevisionStore( $this->createMock( RevisionStore::class ) )
			->segmentPage(
				$title,
				null
			)->getSegments()->getSegments();
		$this->assertEquals( $expectedSegments, $segments );
	}

}
