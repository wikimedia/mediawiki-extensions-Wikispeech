<?php

namespace MediaWiki\Wikispeech\Tests\Integration\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use HashBagOStuff;
use HashConfig;
use InvalidArgumentException;
use MediaWiki\Config\Config;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Revision\RevisionStore;
use Mediawiki\Title\Title;
use MediaWiki\Wikispeech\Segment\PageProvider;
use MediaWiki\Wikispeech\Segment\PageRevisionProperties;
use MediaWiki\Wikispeech\Segment\Segmenter;
use MediaWiki\Wikispeech\Segment\SegmentPageFactory;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use RequestContext;
use WANObjectCache;

/**
 * @covers \MediaWiki\Wikispeech\Segment\SegmentPageFactory
 */
class SegmentPageFactoryTest extends MediaWikiUnitTestCase {

	/** @var WANObjectCache */
	private $cache;

	/** @var Config */
	private $config;

	protected function setUp(): void {
		parent::setUp();

		$this->cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );

		$this->config = new HashConfig();
		$this->config->set( 'WikispeechRemoveTags', [ 'default remove' ] );
		$this->config->set( 'WikispeechSegmentBreakingTags', [ 'default break' ] );
	}

	private function createFactory() {
		$factory = new SegmentPageFactory(
			$this->cache,
			$this->config,
			$this->createMock( RevisionStore::class ),
			$this->createMock( HttpRequestFactory::class )
		);
		return $factory;
	}

	private function configureTestFactory( SegmentPageFactory $factory ): SegmentPageFactory {
		$noOpSegmenter = $this->createMock( Segmenter::class );
		$noOpSegmenter
			->method( 'segmentSentences' )
			->willReturn( [] );
		$factory
			->setContextSource( new RequestContext() )
			->setRevisionStore( $this->createMock( RevisionStore::class ) )
			->setSegmenter( $noOpSegmenter );
		return $factory;
	}

	public function testSegmentPage_neitherTitleNorRevisionIdProvided_throwsException() {
		$factory = $this->createFactory();
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( '$title xor $revisionId must be provided.' );
		$this->configureTestFactory( $factory )
			->segmentPage( null, null );
	}

	public function testSegmentPage_bothTitleAndRevisionIdProvided_throwsException() {
		$factory = $this->createFactory();
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( '$title xor $revisionId must be provided.' );
		$this->configureTestFactory( $factory )
			->segmentPage( Title::makeTitle( NS_MAIN, 'Title' ), 1 );
	}

	public function testSegmentPage_noTitleSet_loadProperties() {
		$loadedTitle = Title::makeTitle( NS_MAIN, 'Title' );
		$loadedPageId = 2;
		$revisionId = 1;
		$pageProvider = $this->createMock( PageProvider::class );
		$pageProvider
			->expects( $this->once() )
			->method( 'loadPageRevisionProperties' )
			->with( $revisionId )
			->willReturn( new PageRevisionProperties( $loadedTitle, $loadedPageId ) );
		$pageProvider
			->expects( $this->once() )
			->method( 'getDisplayTitle' )
			->willReturn( 'display title' );
		$pageProvider
			->expects( $this->once() )
			->method( 'getPageContent' )
			->willReturn( 'page content' );
		$pageProvider
			->expects( $this->once() )
			->method( 'getRevisionId' )
			->willReturn( 1 );

		/** @var SegmentPageFactory|MockObject $factory */
		$factory = $this->getMockBuilder( SegmentPageFactory::class )
			->setConstructorArgs( [
				$this->cache,
				$this->config,
				$this->createMock( RevisionStore::class ),
				$this->createMock( HttpRequestFactory::class )
			] )
			->onlyMethods( [ 'pageProviderFactory' ] )
			->getMock();
		$factory
			->expects( $this->once() )
			->method( 'pageProviderFactory' )
			->willReturn( $pageProvider );

		$segmentPageResponse = $this->configureTestFactory( $factory )
			->setUseRevisionPropertiesCache( false )
			->setRequirePageRevisionProperties( false )
			->setSegmentBreakingTags( [] )
			->setRemoveTags( [] )
			->segmentPage(
				null,
				$revisionId
			);

		$this->assertSame( $revisionId, $segmentPageResponse->getRevisionId() );
		$this->assertSame( $loadedTitle, $segmentPageResponse->getTitle() );
		$this->assertSame( $loadedPageId, $segmentPageResponse->getPageId() );
	}

	public function testSegmentPage_titleSetRequireLoad_loadProperties() {
		$title = Title::makeTitle( NS_MAIN, 'Title' );
		$fetchedRevisionId = 1;
		$loadedPageId = 3;
		$pageProvider = $this->createMock( PageProvider::class );
		$pageProvider
			->expects( $this->once() )
			->method( 'loadPageRevisionProperties' )
			->with( $fetchedRevisionId )
			->willReturn( new PageRevisionProperties( $title, $loadedPageId ) );
		$pageProvider
			->expects( $this->once() )
			->method( 'getDisplayTitle' )
			->willReturn( 'display title' );
		$pageProvider
			->expects( $this->once() )
			->method( 'getPageContent' )
			->willReturn( 'page content' );
		$pageProvider
			->expects( $this->once() )
			->method( 'getRevisionId' )
			->willReturn( $fetchedRevisionId );

		/** @var SegmentPageFactory|MockObject $factory */
		$factory = $this->getMockBuilder( SegmentPageFactory::class )
			->setConstructorArgs( [
				$this->cache,
				$this->config,
				$this->createMock( RevisionStore::class ),
				$this->createMock( HttpRequestFactory::class )
			] )
			->onlyMethods( [ 'pageProviderFactory', 'cleanHtmlDom' ] )
			->getMock();
		$factory
			->expects( $this->once() )
			->method( 'pageProviderFactory' )
			->willReturn( $pageProvider );
		$factory
			->expects( $this->once() )
			->method( 'cleanHtmlDom' )
			->willReturn( [] );

		$segmentPageResponse = $this->configureTestFactory( $factory )
			->setUseRevisionPropertiesCache( false )
			->setRequirePageRevisionProperties( true )
			->setRemoveTags( [ 'user remove tag' ] )
			->setSegmentBreakingTags( [ 'user break tag' ] )
			->segmentPage(
				$title,
				null
			);

		$this->assertSame( $fetchedRevisionId, $segmentPageResponse->getRevisionId() );
		$this->assertSame( $title, $segmentPageResponse->getTitle() );
		$this->assertSame( $loadedPageId, $segmentPageResponse->getPageId() );
	}

	public function testSegmentPage_cleanerParametersNotProvided_useConfigDefaults() {
		$title = Title::makeTitle( NS_MAIN, 'Title' );
		$displayTitle = 'display title';
		$pageContent = 'page content';
		$revisionId = null;

		$pageProvider = $this->createMock( PageProvider::class );
		$pageProvider
			->expects( $this->once() )
			->method( 'getDisplayTitle' )
			->willReturn( $displayTitle );
		$pageProvider
			->expects( $this->once() )
			->method( 'getPageContent' )
			->willReturn( $pageContent );
		$pageProvider
			->expects( $this->once() )
			->method( 'getRevisionId' )
			->willReturn( 1 );

		/** @var SegmentPageFactory|MockObject $factory */
		$factory = $this->getMockBuilder( SegmentPageFactory::class )
			->setConstructorArgs( [
				$this->cache,
				$this->config,
				$this->createMock( RevisionStore::class ),
				$this->createMock( HttpRequestFactory::class )
			] )
			->onlyMethods( [ 'cleanHtmlDom', 'pageProviderFactory' ] )
			->getMock();
		$factory
			->expects( $this->once() )
			->method( 'cleanHtmlDom' )
			->with( $displayTitle, $pageContent, [ 'default remove' ], [ 'default break' ] )
			->willReturn( [] );
		$factory
			->expects( $this->once() )
			->method( 'pageProviderFactory' )
			->willReturn( $pageProvider );

		$this->configureTestFactory( $factory )
			->setUseRevisionPropertiesCache( false )
			->setUseSegmentsCache( false )
			->setRemoveTags( null )
			->setSegmentBreakingTags( null )
			->segmentPage(
				$title,
				$revisionId
			);
	}

	public function testSegmentPage_provideCleanerParameters_useProvidedParameters() {
		$title = Title::makeTitle( NS_MAIN, 'Title' );
		$displayTitle = 'display title';
		$pageContent = 'page content';
		$revisionId = null;

		$pageProvider = $this->createMock( PageProvider::class );
		$pageProvider
			->expects( $this->once() )
			->method( 'getDisplayTitle' )
			->willReturn( $displayTitle );
		$pageProvider
			->expects( $this->once() )
			->method( 'getPageContent' )
			->willReturn( $pageContent );
		$pageProvider
			->expects( $this->once() )
			->method( 'getRevisionId' )
			->willReturn( 1 );

		/** @var SegmentPageFactory|MockObject $factory */
		$factory = $this->getMockBuilder( SegmentPageFactory::class )
			->setConstructorArgs( [
				$this->cache,
				$this->config,
				$this->createMock( RevisionStore::class ),
				$this->createMock( HttpRequestFactory::class )
			] )
			->onlyMethods( [ 'cleanHtmlDom', 'pageProviderFactory' ] )
			->getMock();
		$factory
			->expects( $this->once() )
			->method( 'cleanHtmlDom' )
			->with( $displayTitle, $pageContent, [ 'user remove tag' ], [ 'user break tag' ] )
			->willReturn( [] );
		$factory
			->expects( $this->once() )
			->method( 'pageProviderFactory' )
			->willReturn( $pageProvider );

		$this->configureTestFactory( $factory )
			->setUseRevisionPropertiesCache( false )
			->setUseSegmentsCache( false )
			->setRemoveTags( [ 'user remove tag' ] )
			->setSegmentBreakingTags( [ 'user break tag' ] )
			->segmentPage(
				$title,
				$revisionId
			);
	}

	public function testSegmentPage_noSegmentCache_segmentOnEachInvocation() {
		$title = Title::makeTitle( NS_MAIN, 'Title' );
		$pageProvider = $this->createMock( PageProvider::class );
		$pageProvider
			->expects( $this->exactly( 2 ) )
			->method( 'loadData' );
		$pageProvider
			->expects( $this->exactly( 2 ) )
			->method( 'getDisplayTitle' )
			->willReturn( 'display title' );
		$pageProvider
			->expects( $this->exactly( 2 ) )
			->method( 'getPageContent' )
			->willReturn( 'page content' );
		$pageProvider
			->expects( $this->exactly( 2 ) )
			->method( 'getRevisionId' )
			->willReturn( 1 );

		/** @var SegmentPageFactory|MockObject $factory */
		$factory = $this->getMockBuilder( SegmentPageFactory::class )
			->setConstructorArgs( [
				$this->cache,
				$this->config,
				$this->createMock( RevisionStore::class ),
				$this->createMock( HttpRequestFactory::class )
			] )
			->onlyMethods( [ 'pageProviderFactory' ] )
			->getMock();
		$factory
			->expects( $this->exactly( 2 ) )
			->method( 'pageProviderFactory' )
			->willReturn( $pageProvider );

		$segmenter = $this->createMock( Segmenter::class );
		$segmenter
			->expects( $this->exactly( 2 ) )
			->method( 'segmentSentences' )
			->willReturn( [] );

		$this->configureTestFactory( $factory )
			->setSegmenter( $segmenter )
			->setUseRevisionPropertiesCache( false )
			->setRequirePageRevisionProperties( false )
			->setSegmentBreakingTags( [] )
			->setRemoveTags( [] )
			->setUseSegmentsCache( false );

		$segmentPageResponse1 = $factory->segmentPage( $title, null );
		$segmentPageResponse2 = $factory->segmentPage( $title, null );
	}

	public function testSegmentPage_withSegmentCacheAndRevisionId_segmentOnFirstInvocation() {
		$revisionId = 1;
		$loadedTitle = Title::makeTitle( NS_MAIN, 'Title' );
		$loadedPageId = 5;
		$pageProvider = $this->createMock( PageProvider::class );
		$pageProvider
			// this is what tests the cache!
			->expects( $this->once() )
			->method( 'loadData' );
		$pageProvider
			->expects( $this->exactly( 2 ) )
			->method( 'loadPageRevisionProperties' )
			->with( $revisionId )
			->willReturn( new PageRevisionProperties( $loadedTitle, $loadedPageId ) );
		$pageProvider
			->expects( $this->once() )
			->method( 'getDisplayTitle' )
			->willReturn( 'display title' );
		$pageProvider
			->expects( $this->once() )
			->method( 'getPageContent' )
			->willReturn( 'page content' );
		$pageProvider
			->expects( $this->once() )
			->method( 'getRevisionId' )
			->willReturn( $revisionId );

		/** @var SegmentPageFactory|MockObject $factory */
		$factory = $this->getMockBuilder( SegmentPageFactory::class )
			->setConstructorArgs( [
				$this->cache,
				$this->config,
				$this->createMock( RevisionStore::class ),
				$this->createMock( HttpRequestFactory::class )
			] )
			->onlyMethods( [ 'pageProviderFactory' ] )
			->getMock();
		$factory
			->expects( $this->exactly( 2 ) )
			->method( 'pageProviderFactory' )
			->willReturn( $pageProvider );

		$segmenter = $this->createMock( Segmenter::class );
		$segmenter
			->expects( $this->once() )
			->method( 'segmentSentences' )
			->willReturn( [] );

		$this->configureTestFactory( $factory )
			->setSegmenter( $segmenter )
			->setUseRevisionPropertiesCache( false )
			->setRequirePageRevisionProperties( false )
			->setSegmentBreakingTags( [] )
			->setRemoveTags( [] )
			->setUseSegmentsCache( true );

		$segmentPageResponse1 = $factory->segmentPage( null, $revisionId );
		$segmentPageResponse2 = $factory->segmentPage( null, $revisionId );
	}

	public function testSegmentPage_withSegmentCacheAndTitle_segmentOnEachInvocation() {
		$title = Title::makeTitle( NS_MAIN, 'Title' );
		$loadedRevisionId = 1;
		$loadedPageId = 5;
		$pageProvider = $this->createMock( PageProvider::class );
		$pageProvider
			->expects( $this->exactly( 2 ) )
			->method( 'loadData' );
		$pageProvider
			->expects( $this->once() )
			->method( 'loadPageRevisionProperties' )
			->with( $loadedRevisionId )
			->willReturn( new PageRevisionProperties( $title, $loadedPageId ) );
		$pageProvider
			->expects( $this->exactly( 2 ) )
			->method( 'getDisplayTitle' )
			->willReturn( 'display title' );
		$pageProvider
			->expects( $this->exactly( 2 ) )
			->method( 'getPageContent' )
			->willReturn( 'page content' );
		$pageProvider
			->expects( $this->exactly( 2 ) )
			->method( 'getRevisionId' )
			->willReturn( $loadedRevisionId );

		/** @var SegmentPageFactory|MockObject $factory */
		$factory = $this->getMockBuilder( SegmentPageFactory::class )
			->setConstructorArgs( [
				$this->cache,
				$this->config,
				$this->createMock( RevisionStore::class ),
				$this->createMock( HttpRequestFactory::class )
			] )
			->onlyMethods( [ 'pageProviderFactory' ] )
			->getMock();
		$factory
			->expects( $this->exactly( 3 ) )
			->method( 'pageProviderFactory' )
			->willReturn( $pageProvider );

		$segmenter = $this->createMock( Segmenter::class );
		$segmenter
			->expects( $this->exactly( 2 ) )
			->method( 'segmentSentences' )
			->willReturn( [] );

		$this->configureTestFactory( $factory )
			->setSegmenter( $segmenter )
			->setUseRevisionPropertiesCache( false )
			->setRequirePageRevisionProperties( false )
			->setSegmentBreakingTags( [] )
			->setRemoveTags( [] )
			->setUseSegmentsCache( true );

		$segmentPageResponse1 = $factory->segmentPage( $title, null );
		$segmentPageResponse2 = $factory->segmentPage( $title, null );
		// We need to supply a revision id in order for cache to be picked up!
		$segmentPageResponse3 = $factory->segmentPage( null, $segmentPageResponse2->getRevisionId() );
	}

}
