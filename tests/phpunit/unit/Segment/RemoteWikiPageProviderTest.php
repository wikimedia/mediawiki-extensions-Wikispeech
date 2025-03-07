<?php

namespace MediaWiki\Wikispeech\Tests\Integration\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use ConfigFactory;
use HashBagOStuff;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Revision\RevisionStore;
use Mediawiki\Title\Title;
use MediaWiki\Wikispeech\Segment\CleanedText;
use MediaWiki\Wikispeech\Segment\Segment;
use MediaWiki\Wikispeech\Segment\SegmentPageFactory;
use MediaWikiUnitTestCase;
use RequestContext;
use WANObjectCache;

/**
 * @covers \MediaWiki\Wikispeech\Segment\RemoteWikiPageProvider
 */
class RemoteWikiPageProviderTest extends MediaWikiUnitTestCase {

	/** @var WANObjectCache */
	private $cache;

	protected function setUp(): void {
		parent::setUp();
		$this->cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
	}

	public function testSegmentPage_contentContainsSentences_giveTitleAndContent() {
		$revisionId = 123;
		$titleString = 'Page';
		$content = 'Sentence 1. Sentence 2. Sentence 3.';
		$request = 'https://consumer.url/api.php?action=parse&format=json&page=Page&prop=text%7Crevid%7Cdisplaytitle';
		$httpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$httpRequestFactory
			->expects( $this->once() )
			->method( 'get' )
			->with( $request )
			->willReturn( '{
	"parse": {
		"revid": ' . $revisionId . ',
		"text": {
			"*": "' . $content . '"
		},
		"displaytitle": "' . $titleString . '"
	}
}' );

		$title = Title::makeTitle( NS_MAIN, $titleString );
		$expectedSegments = [
			new Segment(
				[ new CleanedText( 'Page', '//h1/text()' ) ],
				0,
				3,
				'cd2c3fb786ef2a8ba5430f54cde3d468c558647bf0fd777b437e8138e2348e01'
			),
			new Segment(
				[ new CleanedText( 'Sentence 1.', './text()' ) ],
				0,
				10,
				'76ca3069cee56491f5b2f465c4e9b57b7fb74ebc12eecc0cd6aad965ea7e247e'
			),
			new Segment(
				[ new CleanedText( 'Sentence 2.', './text()' ) ],
				12,
				22,
				'33dc64326df9f4b281fc9d680f89423f3261d1056d857a8263d46f7904a705ac'
			),
			new Segment(
				[ new CleanedText( 'Sentence 3.', './text()' ) ],
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
			->setConsumerUrl( 'https://consumer.url' )
			->setHttpRequestFactory( $httpRequestFactory )
			->setRequirePageRevisionProperties( false )
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
