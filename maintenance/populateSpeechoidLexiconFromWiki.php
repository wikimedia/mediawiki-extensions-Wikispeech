<?php

namespace MediaWiki\Wikispeech;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\Wikispeech\Lexicon\LexiconSpeechoidStorage;
use MediaWiki\Wikispeech\Lexicon\LexiconWikiStorage;

/** @var string MediaWiki installation path */
$IP = getenv( 'MW_INSTALL_PATH' ) ?: __DIR__ . '/../../..';
require_once "$IP/maintenance/Maintenance.php";

/**
 * Maintenance script to populate speechoid lexicon
 * with entries from wiki.
 *
 * Be aware that you probably need to execute using mwscript, not php,
 * in order to be executed as user www-data, who has access to deleting files.
 *
 * @since 0.1.13
 */
class PopulateSpeechoidLexiconFromWiki extends Maintenance {

	/** @var LexiconWikiStorage */
	public $lexiconWikiStorage;

	/** @var LexiconSpeechoidStorage */
	public $speechoidStorage;

	/** @var User */
	public $user;

	/** @var callable|null Used to override titles in tests */
	public $getAllLexiconTitlesForLanguageCallback = null;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'Wikispeech' );
		$this->addDescription( 'Populate Speechoid storage with entries from local wiki.' );
		$this->addOption( 'user', 'Username to perform edits as', true, true );
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$voices = $this->getConfig()->get( 'WikispeechVoices' );
		$languages = array_keys( $voices );
		sort( $languages );

		$wikiStorage = $this->lexiconWikiStorage ?? WikispeechServices::getLexiconWikiStorage();
		$speechoidStorage = $this->speechoidStorage ?? WikispeechServices::getLexiconSpeechoidStorage();

		$wikiStorage->setUser( $this->getUser() );

		foreach ( $languages as $language ) {
			$this->output( "Language, $language: " );
			$anyUpdated = false;

			$titles = $this->getAllLexiconTitlesForLanguageCallback
			? call_user_func( $this->getAllLexiconTitlesForLanguageCallback, $language )
			: $this->getAllLexiconTitlesForLanguage( $language );

			foreach ( $titles as $title ) {
				// TODO: This breaks if the lexicon key contains a slash (e.g. "F/V").
				// Consider a more robust way of extracting the key from the title.
				$key = explode( '/', $title->getText(), 2 )[1] ?? null;
				if ( !$key ) {
					continue;
				}

				$entry = $wikiStorage->getEntry( $language, $key );
				if ( !$entry ) {
					continue;
				}

				$updated = false;
				$validItems = [];

				foreach ( $entry->getItems() as $item ) {
					$identity = $item->getSpeechoidIdentity();

					if ( $identity === null ) {
						$this->output( "Warning: $language/$key has no Speechoid ID — skipping.\n" );
						continue;
					}

					$speechoidEntry = $speechoidStorage->getEntry( $language, $key );
					if ( !$speechoidEntry || !$speechoidEntry->findItemBySpeechoidIdentity( $identity ) ) {
						$this->output( "Re-creating missing Speechoid entry for $language/$key (ID: $identity)\n" );

						$item->setSpeechoidIdentity( null );

						try {
							$speechoidStorage->createEntryItem( $language, $key, $item );
							$wikiStorage->replaceEntryItem( $language, $key, $item );
							$this->output( "Re-created Speechoid entry for $language/$key\n" );
							$updated = true;
							$anyUpdated = true;
						} catch ( \LogicException $e ) {
							$this->output( "Failed to re-create entry for $key: " . $e->getMessage() . "\n" );
						}
					}

					$validItems[] = $item;
				}

				if ( $updated ) {
					$newEntry = clone $entry;
					$newEntry->setItems( $validItems );
					$wikiStorage->saveLexiconEntryRevision( $language, $key, $newEntry, 'Repaired Speechoid identity' );
					$this->output( "Saved updated wiki entry for $language/$key\n" );
				}
			}

			if ( !$anyUpdated ) {
				$this->output( "No posts to add\n" );
			}
		}
	}

	/**
	 * Helper function to get system user to be able to
	 * save lexicon entry revision
	 *
	 * @return User
	 */
	protected function getUser(): User {
		if ( $this->user !== null ) {
				return $this->user;
		}
		$services = MediaWikiServices::getInstance();
		$username = $this->getOption( 'user' );
		$userIdentity = $services->getUserIdentityLookup()->getUserIdentityByName( $username );
		if ( !$userIdentity ) {
			$this->fatalError(
				"User '$username' not found or invalid. Please provide an existing username with --user."
			);
		}
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
		return $services->getUserFactory()->newFromUserIdentity( $userIdentity );
	}

	/**
	 * Helper function to get all lexicon titles for a specific language
	 *
	 * @param string $language
	 * @return Title[]
	 */
	protected function getAllLexiconTitlesForLanguage( string $language ): array {
		$titleFactory = MediaWikiServices::getInstance()->getTitleFactory();
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

		$prefix = ucfirst( $language ) . '/';
		$like = "$prefix%";

		$res = $dbr->select(
			'page',
			[ 'page_title' ],
			[
				'page_namespace' => NS_PRONUNCIATION_LEXICON,
				"page_title LIKE '$like'"
			],
			__METHOD__
		);

		$titles = [];
		foreach ( $res as $row ) {
			$titles[] = $titleFactory->makeTitle( NS_PRONUNCIATION_LEXICON, $row->page_title );
		}
		return $titles;
	}
}

$maintClass = PopulateSpeechoidLexiconFromWiki::class;
require_once RUN_MAINTENANCE_IF_MAIN;
