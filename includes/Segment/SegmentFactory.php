<?php

namespace MediaWiki\Wikispeech\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use ConfigFactory;
use IContextSource;
use LogicException;
use MediaWiki\Config\Config;
use WANObjectCache;

/**
 * @since 0.1.13
 */

abstract class SegmentFactory {

	/** @var WANObjectCache */
	protected $cache;

	/**
	 * @var Config
	 */
	protected $config;

	/** @var ConfigFactory */
	protected $configFactory;

	/**
	 * Whether or not to use cache for segmentation
	 * @var bool
	 */
	protected $useSegmentsCache = true;

	/**
	 * Will default to an instance of {@link StandardSegmenter} if not set.
	 * @var Segmenter|null
	 */
	protected $segmenter = null;

	/**
	 * Will default to config setting WikispeechRemoveTags if not set.
	 * @var string[]|null
	 */
	protected $removeTags = null;

	/**
	 * Will default to config setting WikispeechSegmentBreakingTags if not set.
	 * @var string[]|null
	 */
	protected $segmentBreakingTags = null;

	/**
	 * Required only when providing page content from a local wiki.
	 * @var IContextSource
	 */
	protected $contextSource = null;

	/**
	 * Required only when providing page content from a remote wiki.
	 * @var string|null
	 */
	protected $consumerUrl = null;

	/**
	 * If true certain tags add extra content that is read before any text.
	 * @var bool
	 */
	protected $partOfContent = false;

	/**
	 * @since 0.1.13
	 * @param WANObjectCache $cache
	 * @param Config $config
	 */
	public function __construct(
		WANObjectCache $cache,
		Config $config
	) {
		$this->cache = $cache;
		$this->config = $config;
	}

	/**
	 * @see SegmentFactory::$useSegmentsCache
	 * @since 0.1.13
	 * @param bool $useSegmentsCache
	 * @return $this
	 */
	public function setUseSegmentsCache( bool $useSegmentsCache ) {
		$this->useSegmentsCache = $useSegmentsCache;
		return $this;
	}

	/**
	 * @see SegmentFactory::$segmenter
	 * @since 0.1.13
	 * @param Segmenter|null $segmenter
	 * @return $this
	 */
	public function setSegmenter( ?Segmenter $segmenter ) {
		$this->segmenter = $segmenter;
		return $this;
	}

	/**
	 * @see SegmentFactory::$contextSource
	 * @since 0.1.13
	 * @param IContextSource|null $contextSource
	 * @return $this
	 */
	public function setContextSource(
		?IContextSource $contextSource
	) {
		$this->contextSource = $contextSource;
		return $this;
	}

	/**
	 * @see SegmentFactory::$consumerUrl
	 * @since 0.1.13
	 * @param string|null $consumerUrl
	 * @return $this
	 */
	public function setConsumerUrl(
		?string $consumerUrl
	) {
		$this->consumerUrl = $consumerUrl;
		return $this;
	}

	/**
	 * @see SegmentFactory::$removeTags
	 * @since 0.1.13
	 * @param string[]|null $removeTags
	 * @return $this
	 */
	public function setRemoveTags(
		?array $removeTags
	) {
		$this->removeTags = $removeTags;
		return $this;
	}

	/**
	 * @see SegmentFactory::$segmentBreakingTags
	 * @since 0.1.13
	 * @param string[]|null $segmentBreakingTags
	 * @return $this
	 */
	public function setSegmentBreakingTags(
		?array $segmentBreakingTags
	) {
		$this->segmentBreakingTags = $segmentBreakingTags;
		return $this;
	}

	/**
	 * @see SegmentFactory::$setSegmenter
	 * @since 0.1.13
	 * @param string $language
	 * @return $this
	 */
	public function setSegmenterByLanguage(
		string $language
	) {
		// @todo lookup segmenter by language
		return $this->setSegmenter( new StandardSegmenter() );
	}

	/**
	 * @see SegmentFactory::$partOfContent
	 * @since 0.1.13
	 * @param bool $partOfContent
	 * @return $this
	 */
	public function setPartOfContent( bool $partOfContent ) {
		$this->partOfContent = $partOfContent;
		return $this;
	}

	/**
	 * This method exists due to need for test mocking.
	 *
	 * @see Cleaner::cleanHtmlDom()
	 * @since 0.1.13
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
		$cleaner = new Cleaner( $removeTags, $segmentBreakingTags, $this->partOfContent );
		return $cleaner->cleanHtmlDom( $displayTitle, $pageContent );
	}

}
