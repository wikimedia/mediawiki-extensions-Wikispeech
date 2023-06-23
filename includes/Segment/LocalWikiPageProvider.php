<?php

namespace MediaWiki\Wikispeech\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use Title;

/**
 * @since 0.1.10
 */
class LocalWikiPageProvider extends AbstractPageProvider {

	/** @var IContextSource */
	private $context;

	/** @var RevisionStore */
	private $revisionStore;

	/**
	 * @since 0.1.10
	 * @param IContextSource $context
	 * @param RevisionStore $revisionStore
	 */
	public function __construct(
		IContextSource $context,
		RevisionStore $revisionStore
	) {
		$this->context = $context;
		$this->revisionStore = $revisionStore;
	}

	/**
	 * @since 0.1.10
	 * @param int $revisionId
	 * @return PageRevisionProperties
	 * @throws DeletedRevisionException
	 */
	public function loadPageRevisionProperties( int $revisionId ): PageRevisionProperties {
		$revisionRecord = $this->revisionStore->getRevisionById( $revisionId );
		if ( !$revisionRecord || !$revisionRecord->audienceCan(
				RevisionRecord::DELETED_TEXT,
				RevisionRecord::FOR_THIS_USER,
				$this->context->getUser()
			)
		) {
			throw new DeletedRevisionException( 'A deleted revision id was provided' );
		}
		return new PageRevisionProperties(
			Title::newFromLinkTarget( $revisionRecord->getPageAsLinkTarget() ),
			$revisionRecord->getPageId()
		);
	}

	/**
	 * @param Title $title
	 * @since 0.1.10
	 */
	public function loadData( Title $title ): void {
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		$parserOptions = $page->makeParserOptions( $this->context );
		$parserOutput = $page->getParserOutput( $parserOptions );
		$this->setDisplayTitle( $parserOutput->getDisplayTitle() );
		$this->setPageContent( $parserOutput->getText() );
		$this->setRevisionId( $page->getLatest() );
	}

	/**
	 * @since 0.1.10
	 * @return string
	 */
	public function getCachedSegmentsKeyComponents(): string {
		return 'local';
	}

}
