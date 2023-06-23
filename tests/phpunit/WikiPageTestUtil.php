<?php

namespace MediaWiki\Wikispeech\Tests;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use CommentStoreComment;
use FatalError;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\RevisionSlotsUpdate;
use MWException;
use TestUserRegistry;
use Title;
use WikiPage;
use WikitextContent;

class WikiPageTestUtil {

	/** @var WikiPage[] */
	private static $pagesAdded = [];

	/**
	 * Create a new or updates an existing page.
	 *
	 * @since 0.1.5
	 * @param string|Title $title
	 * @param string $content
	 * @param int $namespace Default is NS_MAIN.
	 * @return WikiPage
	 * @throws MWException If failed to add page, or page already exists.
	 */
	public static function addPage(
		$title,
		$content,
		$namespace = NS_MAIN
	) {
		if ( is_string( $title ) ) {
			$title = Title::newFromText( $title, $namespace );
		}
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		if ( $page->exists() ) {
			throw new MWException( 'Page already exists: ' . $title );
		}
		$wikiTextContent = new WikiTextContent( $content );
		$testUser = TestUserRegistry::getImmutableTestUser()->getUser();
		$pageUpdater = $page->newPageUpdater( $testUser );
		$pageUpdater->setContent( SlotRecord::MAIN, $wikiTextContent );
		$pageUpdater->saveRevision( CommentStoreComment::newUnsavedComment( '' ) );
		if ( !$pageUpdater->wasSuccessful() ) {
			throw new MWException( 'Failed to create page: ' . $pageUpdater->getStatus() );
		}
		self::$pagesAdded[] = $page;
		return $page;
	}

	/**
	 * Edit an existing page.
	 *
	 * @since 0.1.5
	 * @param WikiPage $page
	 * @param string $content
	 * @throws MWException If page does not exist, failed to edit, or a to null-change was executed.
	 */
	public static function editPage(
		$page,
		$content
	) {
		if ( !$page->exists() ) {
			throw new MWException( 'Page does not exist: ' . $page->getTitle() );
		}
		$wikiTextContent = new WikiTextContent( $content );
		$slotsUpdate = new RevisionSlotsUpdate();
		$slotsUpdate->modifyContent( SlotRecord::MAIN, $wikiTextContent );
		$testUser = TestUserRegistry::getImmutableTestUser()->getUser();
		$pageUpdater = $page->newPageUpdater( $testUser, $slotsUpdate );
		$pageUpdater->setContent( SlotRecord::MAIN, $wikiTextContent );
		$pageUpdater->saveRevision( CommentStoreComment::newUnsavedComment( '' ) );
		if ( !$pageUpdater->wasSuccessful() && !$pageUpdater->isUnchanged() ) {
			throw new MWException( 'Failed to edit page: ' . $pageUpdater->getStatus() );
		}
	}

	/**
	 * @throws MWException
	 * @throws FatalError
	 */
	public static function removeCreatedPages() {
		$testUser = TestUserRegistry::getImmutableTestUser()->getUser();
		foreach ( self::$pagesAdded as $page ) {
			if ( $page->exists() ) {
				$page->doDeleteArticleReal( "testing done.", $testUser );
			}
		}
		self::$pagesAdded = [];
	}
}
