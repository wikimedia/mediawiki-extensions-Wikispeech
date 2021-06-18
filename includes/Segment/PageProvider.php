<?php

namespace MediaWiki\Wikispeech\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use Title;

/**
 * Handler for loading page content and properties.
 * This can either be the local wiki via the MediaWiki core API {@link LocalWikiPageProvider}
 * or a remote wiki via HTTP API {@link RemoteWikiPageProvider} when client supplies a consumer-url.
 *
 * @since 0.1.10
 */
interface PageProvider {

	/**
	 * @since 0.1.10
	 * @return string
	 */
	public function getCachedSegmentsKeyComponents(): string;

	/**
	 * Loads title and pageId given a revision id.
	 *
	 * @since 0.1.10
	 * @param int $revisionId
	 * @return PageRevisionProperties
	 */
	public function loadPageRevisionProperties( int $revisionId ): PageRevisionProperties;

	/**
	 * Loads display title, page content and fetched revision id.
	 *
	 * @since 0.1.10
	 * @param Title $title
	 */
	public function loadData( Title $title ): void;

	/**
	 * Revision id of the fetched page.
	 *
	 * @since 0.1.10
	 * @return int|null
	 */
	public function getRevisionId(): ?int;

	/**
	 * @since 0.1.10
	 * @return string|null
	 */
	public function getPageContent(): ?string;

	/**
	 * @since 0.1.10
	 * @return string|null
	 */
	public function getDisplayTitle(): ?string;

}
