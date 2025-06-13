<?php

namespace MediaWiki\Wikispeech\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use ConfigFactory;
use IContextSource;
use InvalidArgumentException;
use LogicException;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Revision\RevisionStore;
use Mediawiki\Title\Title;
use RuntimeException;
use WANObjectCache;

/**
 * @since 0.1.10
 */
class SegmentPageFactory {

	/** @var WANObjectCache */
	private $cache;

	/** @var ConfigFactory */
	private $configFactory;

	/**
	 * Whether or not to use cache for segmentation
	 * @var bool
	 */
	private $useSegmentsCache = true;

	/**
	 * Whether or not to use cache for page revision properties,
	 * i.e. to retrieve page id and title when only supplying a revision id.
	 *
	 * Not turning this on in a situation where consumerUrl is set
	 * will cause one extra http request to the remote wiki
	 * in order to lookup pageId, which is required to create or find
	 * utterances in UtteranceStore.
	 *
	 * @var bool
	 */
	private $useRevisionPropertiesCache = false;

	/**
	 * Will default to an instance of {@link StandardSegmenter} if not set.
	 * @var Segmenter|null
	 */
	private $segmenter = null;

	/**
	 * Will default to config setting WikispeechRemoveTags if not set.
	 * @var string[]|null
	 */
	private $removeTags = null;

	/**
	 * Will default to config setting WikispeechSegmentBreakingTags if not set.
	 * @var string[]|null
	 */
	private $segmentBreakingTags = null;

	/**
	 * Required only when providing page content from a local wiki.
	 * @var IContextSource
	 */
	private $contextSource = null;

	/**
	 * Required only when providing page content from a local wiki.
	 * @var RevisionStore|null
	 */
	private $revisionStore = null;

	/**
	 * Required only when providing page content from a remote wiki.
	 * @var HttpRequestFactory|null
	 */
	private $httpRequestFactory = null;

	/**
	 * Required only when providing page content from a remote wiki.
	 * @var string|null
	 */
	private $consumerUrl = null;

	/**
	 * If true, page id and title is always retrieved from page provider or cache
	 * and be made available in response from segmentPage.
	 *
	 * @var bool
	 */
	private $requirePageRevisionProperties = false;

	/**
	 * @since 0.1.10
	 * @param WANObjectCache $cache
	 * @param ConfigFactory $configFactory
	 */
	public function __construct(
		WANObjectCache $cache,
		ConfigFactory $configFactory
	) {
		$this->cache = $cache;
		$this->configFactory = $configFactory;
	}

	/**
	 * @see SegmentPageFactory::$useSegmentsCache
	 * @since 0.1.10
	 * @param bool $useSegmentsCache
	 * @return SegmentPageFactory $this
	 */
	public function setUseSegmentsCache(
		bool $useSegmentsCache
	): SegmentPageFactory {
		$this->useSegmentsCache = $useSegmentsCache;
		return $this;
	}

	/**
	 * @see SegmentPageFactory::$useRevisionPropertiesCache
	 * @since 0.1.10
	 * @param bool $useRevisionPropertiesCache
	 * @return SegmentPageFactory $this
	 */
	public function setUseRevisionPropertiesCache(
		bool $useRevisionPropertiesCache
	): SegmentPageFactory {
		$this->useRevisionPropertiesCache = $useRevisionPropertiesCache;
		return $this;
	}

	/**
	 * @see SegmentPageFactory::$segmenter
	 * @since 0.1.10
	 * @param Segmenter|null $segmenter
	 * @return SegmentPageFactory $this
	 */
	public function setSegmenter(
		?Segmenter $segmenter
	): SegmentPageFactory {
		$this->segmenter = $segmenter;
		return $this;
	}

	/**
	 * @see SegmentPageFactory::$setSegmenter
	 * @since 0.1.10
	 * @param string $language
	 * @return SegmentPageFactory $this
	 */
	public function setSegmenterByLanguage(
		string $language
	): SegmentPageFactory {
		// @todo lookup segmenter by language
		return $this->setSegmenter( new StandardSegmenter() );
	}

	/**
	 * @see SegmentPageFactory::$removeTags
	 * @since 0.1.10
	 * @param string[]|null $removeTags
	 * @return SegmentPageFactory $this
	 */
	public function setRemoveTags(
		?array $removeTags
	): SegmentPageFactory {
		$this->removeTags = $removeTags;
		return $this;
	}

	/**
	 * @see SegmentPageFactory::$segmentBreakingTags
	 * @since 0.1.10
	 * @param string[]|null $segmentBreakingTags
	 * @return SegmentPageFactory $this
	 */
	public function setSegmentBreakingTags(
		?array $segmentBreakingTags
	): SegmentPageFactory {
		$this->segmentBreakingTags = $segmentBreakingTags;
		return $this;
	}

	/**
	 * @see SegmentPageFactory::$contextSource
	 * @since 0.1.10
	 * @param IContextSource|null $contextSource
	 * @return SegmentPageFactory $this
	 */
	public function setContextSource(
		?IContextSource $contextSource
	): SegmentPageFactory {
		$this->contextSource = $contextSource;
		return $this;
	}

	/**
	 * @see SegmentPageFactory::$revisionStore
	 * @since 0.1.10
	 * @param RevisionStore|null $revisionStore
	 * @return SegmentPageFactory $this
	 */
	public function setRevisionStore(
		?RevisionStore $revisionStore
	): SegmentPageFactory {
		$this->revisionStore = $revisionStore;
		return $this;
	}

	/**
	 * @see SegmentPageFactory::$httpRequestFactory
	 * @since 0.1.10
	 * @param HttpRequestFactory|null $httpRequestFactory
	 * @return SegmentPageFactory $this
	 */
	public function setHttpRequestFactory(
		?HttpRequestFactory $httpRequestFactory
	): SegmentPageFactory {
		$this->httpRequestFactory = $httpRequestFactory;
		return $this;
	}

	/**
	 * @see SegmentPageFactory::$consumerUrl
	 * @since 0.1.10
	 * @param string|null $consumerUrl
	 * @return SegmentPageFactory $this
	 */
	public function setConsumerUrl(
		?string $consumerUrl
	): SegmentPageFactory {
		$this->consumerUrl = $consumerUrl;
		return $this;
	}

	/**
	 * If true, page id and title is always retrieved from page provider or cache
	 * and be made available in response from segmentPage.
	 *
	 * @since 0.1.10
	 * @param bool $requirePageRevisionProperties
	 * @return SegmentPageFactory $this
	 */
	public function setRequirePageRevisionProperties(
		bool $requirePageRevisionProperties
	): SegmentPageFactory {
		$this->requirePageRevisionProperties = $requirePageRevisionProperties;
		return $this;
	}

	/**
	 * @since 0.1.10
	 * @return PageProvider
	 */
	protected function pageProviderFactory(): PageProvider {
		if ( $this->consumerUrl ) {
			if ( $this->httpRequestFactory === null ) {
				throw new LogicException( '$httpRequestFactory is null!' );
			}
			return new RemoteWikiPageProvider( $this->consumerUrl, $this->httpRequestFactory );
		} else {
			if ( $this->contextSource === null ) {
				throw new LogicException( '$contextSource is null!' );
			}
			if ( $this->revisionStore === null ) {
				throw new LogicException( '$revisionStore is null!' );
			}
			return new LocalWikiPageProvider( $this->contextSource, $this->revisionStore );
		}
	}

	/**
	 * Loads revision properties
	 * from cache (if set to use)
	 * or via pageProvider (if not using or missing in cache).
	 *
	 * @since 0.1.10
	 * @param PageProvider $pageProvider
	 * @param int $revisionId
	 * @return PageRevisionProperties
	 */
	protected function loadPageRevisionProperties(
		PageProvider $pageProvider,
		int $revisionId
	): PageRevisionProperties {
		$cacheKey = $this->pageRevisionPropertiesCacheKeyFactory( $pageProvider, $revisionId );
		// Lookup title and page id given the revision id.
		if ( $this->useRevisionPropertiesCache ) {
			$revisionProperties = $this->cache->get( $cacheKey );
		} else {
			$revisionProperties = false;
		}
		if ( $revisionProperties === false ) {
			$revisionProperties = $pageProvider->loadPageRevisionProperties( $revisionId );
			if ( $this->useRevisionPropertiesCache ) {
				$this->cache->set( $cacheKey, $revisionProperties );
			}
		}
		return $revisionProperties;
	}

	/**
	 * Convenience function to build segmenter
	 * which is immediately invoked to segment page
	 * after parsing DOM of the HTML
	 * supplied by a page provider that is constructed from the factory fields,
	 * possibly loading extra properties required by invoker,
	 * and possibly bypassing all of above invocation using caches.
	 *
	 * $title XOR $revisionId must be provided.
	 *
	 * @since 0.1.10
	 * @param Title|null $title
	 * @param int|null $revisionId
	 * @return SegmentPageResponse
	 * @throws InvalidArgumentException If $title xor $revisionId is not provided.
	 */
	public function segmentPage(
		?Title $title,
		?int $revisionId
	): SegmentPageResponse {
		$pageProvider = $this->pageProviderFactory();

		$segmentPageResponse = new SegmentPageResponse();

		$revisionProperties = null;

		if ( $title === null && $revisionId !== null ) {
			// Lookup title and page id given the revision id.
			$revisionProperties = $this->loadPageRevisionProperties( $pageProvider, $revisionId );
			$segmentPageResponse->setTitle( $revisionProperties->getTitle() );
			$segmentPageResponse->setPageId( $revisionProperties->getPageId() );
			$segmentPageResponse->setRevisionId( $revisionId );
		} elseif ( $title !== null && $revisionId === null ) {
			// Set user supplied title.
			$segmentPageResponse->setTitle( $title );
		} else {
			throw new InvalidArgumentException( '$title xor $revisionId must be provided.' );
		}

		$config = $this->configFactory->makeConfig( 'wikispeech' );
		$removeTags = $this->removeTags ?? $config->get( 'WikispeechRemoveTags' );
		$segmentBreakingTags = $this->segmentBreakingTags ?? $config->get( 'WikispeechSegmentBreakingTags' );

		$segmenter = $this->segmenter ?? new StandardSegmenter();

		if ( $this->useSegmentsCache && $revisionId !== null ) {
			$segments = $this->cache->get(
				$this->segmentedPageCacheKeyFactory(
					$removeTags,
					$segmentBreakingTags,
					$pageProvider,
					$revisionId
				)
			);
		} else {
			$segments = false;
		}
		if ( $segments === false ) {
			$segmentPageResponseTitle = $segmentPageResponse->getTitle();
			if ( $segmentPageResponseTitle === null ) {
				throw new LogicException( 'Title is null!' );
			}
			$pageProvider->loadData( $segmentPageResponseTitle );

			$displayTitle = $pageProvider->getDisplayTitle();
			if ( $displayTitle === null ) {
				throw new RuntimeException( 'Display title not loaded!' );
			}
			$pageContent = $pageProvider->getPageContent();
			if ( $pageContent === null ) {
				throw new RuntimeException( 'Page content not loaded!' );
			}
			$providedRevisionId = $pageProvider->getRevisionId();
			if ( $providedRevisionId === null ) {
				throw new RuntimeException( 'Revision id not loaded!' );
			}

			if ( $revisionId !== null
				&& $revisionId !== $providedRevisionId
			) {
				throw new OutdatedOrInvalidRevisionException( 'An outdated or invalid revision id was provided' );
			}

			$cleanedText = $this->cleanHtmlDom(
				$displayTitle,
				$pageContent,
				$removeTags,
				$segmentBreakingTags
			);

			$segments = new SegmentList( $segmenter->segmentSentences( $cleanedText ) );

			if ( $this->useSegmentsCache ) {
				$this->cache->set(
				// use revision as stated by page provider, not as provided by invoker.
					$this->segmentedPageCacheKeyFactory(
						$removeTags,
						$segmentBreakingTags,
						$pageProvider,
						$providedRevisionId
					),
					$segments,
					WANObjectCache::TTL_HOUR
				);
			}
			$segmentPageResponse->setRevisionId( $providedRevisionId );
		}

		$segmentPageResponse->setSegments( $segments );

		if ( $this->requirePageRevisionProperties && $revisionProperties === null ) {
			$segmentPageResponseRevisionId = $segmentPageResponse->getRevisionId();
			if ( $segmentPageResponseRevisionId === null ) {
				throw new LogicException( 'Revision id is null!' );
			}
			$revisionProperties = $this->loadPageRevisionProperties(
				$pageProvider,
				$segmentPageResponseRevisionId
			);
			$segmentPageResponse->setTitle( $revisionProperties->getTitle() );
			$segmentPageResponse->setPageId( $revisionProperties->getPageId() );
		}

		return $segmentPageResponse;
	}

	/**
	 * @param string[] $removeTags
	 * @param string[] $segmentBreakingTags
	 * @param PageProvider $pageProvider
	 * @param int|null $revisionId
	 * @return string
	 */
	private function segmentedPageCacheKeyFactory(
		array $removeTags,
		array $segmentBreakingTags,
		PageProvider $pageProvider,
		?int $revisionId
	): string {
		$cacheKeyComponents = [
			get_class( $this ),
			get_class( $pageProvider ),
			$revisionId,
			var_export( $removeTags, true ),
			implode( '-', $segmentBreakingTags ),
			$pageProvider->getCachedSegmentsKeyComponents()
		];
		return $this->cache->makeKey(
			'Wikispeech.segments',
			...$cacheKeyComponents
		);
	}

	/**
	 * @param PageProvider $pageProvider
	 * @param int $revisionId
	 * @return string
	 */
	private function pageRevisionPropertiesCacheKeyFactory(
		PageProvider $pageProvider,
		int $revisionId
	): string {
		$cacheKeyComponents = [
			get_class( $this ),
			get_class( $pageProvider ),
			$revisionId,
			$pageProvider->getCachedSegmentsKeyComponents()
		];
		return $this->cache->makeKey(
			'Wikispeech.pageRevisionProperties',
			...$cacheKeyComponents
		);
	}

	/**
	 * This method exists due to need for test mocking.
	 *
	 * @see Cleaner::cleanHtmlDom()
	 * @since 0.1.10
	 * @param string $displayTitle
	 * @param string $pageContent
	 * @param string[] $removeTags
	 * @param string[] $segmentBreakingTags
	 * @return array
	 * @throws LogicException
	 */
	protected function cleanHtmlDom(
		string $displayTitle,
		string $pageContent,
		array $removeTags,
		array $segmentBreakingTags
	): array {
		$cleaner = new Cleaner( $removeTags, $segmentBreakingTags );
		return $cleaner->cleanHtmlDom( $displayTitle, $pageContent );
	}
}
