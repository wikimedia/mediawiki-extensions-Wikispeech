<?php

namespace MediaWiki\Wikispeech\Utterance;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use Config;
use ExternalStoreException;
use FileBackend;
use FSFileBackend;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;
use MWTimestamp;
use Psr\Log\LoggerInterface;
use SwiftFileBackend;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * Keeps track of utterances in persistent layers.
 *
 * Utterance metadata (i.e. segment hash, page id, language, etc) is stored in a database table.
 * Utterance audio is (synthesised voice audio) is stored as an opus file in file backend.
 * Synthesis metadata (tokens, etc) is stored as a JSON file in file backend.
 *
 * (.opus and .json suffixes are added in file backed store although this class is agnostic
 * regarding to the actual data encoding and formats.)
 *
 * @since 0.1.5
 */
class UtteranceStore {

	/** @var string Name of database table that keeps track of utterance metadata. */
	public const UTTERANCE_TABLE = 'wikispeech_utterance';

	/** @var LoggerInterface */
	private $logger;

	/**
	 * Don't use this directly, access @see getFileBackend
	 * @var FileBackend Used to store utterance audio and synthesis metadata.
	 */
	private $fileBackend;

	/**
	 * @var ILoadBalancer
	 */
	private $dbLoadBalancer;

	/** @var string Name of container (sort of path prefix) used for files in backend. */
	private $fileBackendContainerName;

	/** @var Config */
	private $config;

	public function __construct() {
		$this->logger = LoggerFactory::getInstance( 'Wikispeech' );

		// @todo don't create, add as constructor parameter
		// Refer to https://phabricator.wikimedia.org/T264165
		$this->config = MediaWikiServices::getInstance()
			->getConfigFactory()
			->makeConfig( 'wikispeech' );

		$this->fileBackendContainerName = $this->config
			->get( 'WikispeechUtteranceFileBackendContainerName' );
		if ( !$this->fileBackendContainerName ) {
			$this->fileBackendContainerName = "wikispeech-utterances";
			$this->logger->info( __METHOD__ . ': ' .
				'Falling back on container name {containerName}', [
					'containerName' => $this->fileBackendContainerName
			] );
		}

		$this->dbLoadBalancer = MediaWikiServices::getInstance()->getDBLoadBalancer();
	}

	/**
	 * @since 0.1.5
	 * @return FileBackend
	 * @throws ExternalStoreException If defined file backend group does not exists.
	 */
	private function getFileBackend() {
		global $wgUploadDirectory;
		if ( !$this->fileBackend ) {

			/** @var string Name of file backend group in LocalSettings.php to use. */
			$fileBackendName = $this->config->get( 'WikispeechUtteranceFileBackendName' );
			if ( !$fileBackendName ) {
				$fileBackendName = 'wikispeech-backend';
				$fallbackDir = "$wgUploadDirectory/wikispeech_utterances";
				$this->logger->info( __METHOD__ . ': ' .
					'No file backend defined in LocalSettings.php. Falling back ' .
					'on FS storage backend named {name} in {dir}.', [
						'name' => $fileBackendName,
						'dir' => $fallbackDir
				] );
				$this->fileBackend = new FSFileBackend( [
					'name' => $fileBackendName,
					'wikiId' => WikiMap::getCurrentWikiId(),
					'basePath' => $fallbackDir
				] );
			} else {
				$fileBackend = MediaWikiServices::getInstance()
					->getFileBackendGroup()
					->get( $fileBackendName );
				if ( $fileBackend ) {
					$this->fileBackend = $fileBackend;
				} else {
					throw new ExternalStoreException(
						"No file backend group in LocalSettings.php named $fileBackendName."
					);
				}
			}
		}
		return $this->fileBackend;
	}

	/**
	 * Retrieves an utterance for a given segment in a page, using a specific
	 * voice and language.
	 *
	 * @since 0.1.5
	 * @param string|null $consumerUrl Remote wiki where page is located, or null if local.
	 * @param int $pageId Mediawiki page ID.
	 * @param string $language ISO-639.
	 * @param string $voice Name of synthesis voice.
	 * @param string $segmentHash Hash of segment representing utterance.
	 * @param bool $omitAudio If true, then no audio is returned.
	 * @return Utterance|null Utterance found, or null if non-existing.
	 */
	public function findUtterance(
		?string $consumerUrl,
		int $pageId,
		string $language,
		string $voice,
		string $segmentHash,
		bool $omitAudio = false
	): ?Utterance {
		$utterance = $this->retrieveUtteranceMetadata(
			$consumerUrl,
			$pageId,
			$language,
			$voice,
			$segmentHash
		);
		if ( !$utterance ) {
			return null;
		}

		// load utterance audio and synthesis metadata
		$utteranceId = $utterance->getUtteranceId();

		// @note We might want to keep this as separate function calls,
		// allowing the user to request when needed, and perhaps
		// pass a stream straight down from file backend to user
		// rather than bouncing it via RAM.
		// Not sure if this is an existing thing in PHP though.

		if ( !$omitAudio ) {
			$audioSrc = $this->audioUrlFactory( $utteranceId );
			try {
				$utterance->setAudio( $this->retrieveFileContents(
					$audioSrc,
					$utteranceId,
					'audio file'
				) );
			} catch ( ExternalStoreException $e ) {
				$this->logger->warning( __METHOD__ . ': ' . $e->getMessage() );
				return null;
			}
		}

		$synthesisMetadataSrc = $this->synthesisMetadataUrlFactory( $utteranceId );
		try {
			$utterance->setSynthesisMetadata( $this->retrieveFileContents(
				$synthesisMetadataSrc,
				$utteranceId,
				'synthesis metadata file'
			) );
		} catch ( ExternalStoreException $e ) {
			$this->logger->warning( __METHOD__ . ': ' . $e->getMessage() );
			return null;
		}

		return $utterance;
	}

	/**
	 * Retrieves the utterance metadata from the database for a given segment in a page,
	 * using a specific voice and language.
	 *
	 * @since 0.1.5
	 * @param string|null $consumerUrl Remote wiki where page is located, or null if local.
	 * @param int $pageId Mediawiki page ID.
	 * @param string $language ISO-639.
	 * @param string $voice Name of synthesis voice.
	 * @param string $segmentHash Hash of segment representing utterance.
	 * @return Utterance|null Utterance or null if not found in database
	 */
	public function retrieveUtteranceMetadata(
		?string $consumerUrl,
		int $pageId,
		string $language,
		string $voice,
		string $segmentHash
	): ?Utterance {
		$remoteWikiHash = self::evaluateRemoteWikiHash( $consumerUrl );
		$dbr = $this->dbLoadBalancer->getConnection( DB_REPLICA );
		$row = $dbr->selectRow( self::UTTERANCE_TABLE, [
			'wsu_utterance_id',
			'wsu_remote_wiki_hash',
			'wsu_page_id',
			'wsu_lang',
			'wsu_voice',
			'wsu_seg_hash',
			'wsu_date_stored'
		], [
			'wsu_remote_wiki_hash' => $remoteWikiHash,
			'wsu_page_id' => $pageId,
			'wsu_lang' => $language,
			'wsu_voice' => $voice,
			'wsu_seg_hash' => $segmentHash
		], __METHOD__, [
			'ORDER BY date_stored DESC',
		] );
		if ( !$row ) {
			return null;
		}
		$utterance = new Utterance(
			intval( $row->wsu_utterance_id ),
			$row->wsu_remote_wiki_hash === null ? null : strval( $row->wsu_remote_wiki_hash ),
			intval( $row->wsu_page_id ),
			strval( $row->wsu_lang ),
			strval( $row->wsu_voice ),
			strval( $row->wsu_seg_hash ),
			MWTimestamp::getInstance( $row->wsu_date_stored )
		);
		return $utterance;
	}

	/**
	 * Retrieve the file contents from the backend.
	 *
	 * @since 0.1.5
	 * @param string $src
	 * @param int $utteranceId
	 * @param string $type
	 * @return mixed File contents
	 * @throws ExternalStoreException
	 */
	public function retrieveFileContents( $src, $utteranceId, $type ) {
		$content = $this->getFileBackend()->getFileContents( [
			'src' => $src
		] );
		if ( $content == FileBackend::CONTENT_FAIL ) {
			// @note Consider queuing job to flush inconsistencies from database.
			throw new ExternalStoreException(
				"Inconsistency! Database contains utterance with ID $utteranceId " .
				"that does not exist as $type named $src in file backend." );
		}
		return $content;
	}

	/**
	 * Creates an utterance in the database.
	 *
	 * @since 0.1.5
	 * @param string|null $consumerUrl
	 * @param int $pageId Mediawiki page ID.
	 * @param string $language ISO 639.
	 * @param string $voice Name of synthesis voice.
	 * @param string $segmentHash Hash of segment representing utterance.
	 * @param string $audio Utterance audio.
	 * @param string $synthesisMetadata JSON form metadata about the audio.
	 * @return Utterance Inserted utterance.
	 * @throws ExternalStoreException If unable to prepare or create files in file backend.
	 */
	public function createUtterance(
		?string $consumerUrl,
		int $pageId,
		string $language,
		string $voice,
		string $segmentHash,
		string $audio,
		string $synthesisMetadata
	): Utterance {
		$remoteWikiHash = self::evaluateRemoteWikiHash( $consumerUrl );
		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
		$rows = [
			'wsu_remote_wiki_hash' => $remoteWikiHash,
			'wsu_page_id' => $pageId,
			'wsu_lang' => $language,
			'wsu_voice' => $voice,
			'wsu_seg_hash' => $segmentHash,
			'wsu_date_stored' => $dbw->timestamp()
		];
		$dbw->insert( self::UTTERANCE_TABLE, $rows, __METHOD__ );
		$utterance = new Utterance(
			intval( $dbw->insertId() ),
			$remoteWikiHash,
			$pageId,
			$language,
			$voice,
			$segmentHash,
			MWTimestamp::getInstance( $rows['wsu_date_stored'] )
		);

		// create audio file
		$this->storeFile(
			$this->audioUrlFactory( $utterance->getUtteranceId() ),
			$audio,
			'audio file'
		);
		$utterance->setAudio( $audio );

		// create synthesis metadata file
		$this->storeFile(
			$this->synthesisMetadataUrlFactory( $utterance->getUtteranceId() ),
			$synthesisMetadata,
			'synthesis metadata file'
		);
		$utterance->setSynthesisMetadata( $synthesisMetadata );

		$jobQueue = new FlushUtterancesFromStoreByExpirationJobQueue();
		$jobQueue->maybeQueueJob();

		return $utterance;
	}

	/**
	 * Store a file in the backend.
	 *
	 * @since 0.1.5
	 * @param string $fileUrl
	 * @param mixed $content
	 * @param string $type
	 * @throws ExternalStoreException
	 */
	public function storeFile( $fileUrl, $content, $type ) {
		$fileBackend = $this->getFileBackend();
		if ( !$fileBackend->prepare( [
			'dir' => dirname( $fileUrl ),
			'noAccess' => 1,
			'noListing' => 1
		] )->isOK() ) {
			throw new ExternalStoreException( "Failed to prepare $type: $fileUrl." );
		}
		$opts = [
			'dst' => $fileUrl,
			'content' => $content
		];
		if ( $this->isWikispeechUtteranceUseSwiftFileBackendExpiring() &&
			$fileBackend instanceof SwiftFileBackend ) {
			// Mark files in Swift for automatic removal after TTL.
			// See $this->flushUtterances for code that skips forced removal if backend is Swift.
			$opts['headers'] = [
				// number of seconds from now
				'X-Delete-After' => $this->getWikispeechUtteranceTimeToLiveDays() * 60 * 60 * 24
			];
		}
		if ( !$fileBackend->create( $opts )->isOK() ) {
			throw new ExternalStoreException( "Failed to create $type: $fileUrl." );
		}
	}

	/**
	 * Clears database and file backend of utterances older than a given age.
	 *
	 * @since 0.1.5
	 * @param MWTimestamp $expirationDate
	 * @return int Number of utterances flushed.
	 */
	public function flushUtterancesByExpirationDate( $expirationDate ) {
		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
		$results = $dbw->select( self::UTTERANCE_TABLE,
			[ 'wsu_utterance_id' ],
			[ 1 => 'wsu_date_stored <= ' . $expirationDate->getTimestamp( TS_MW ) ],
			__METHOD__
		);
		return $this->flushUtterances( $dbw, $results );
	}

	/**
	 * Clears database and file backend of all utterances for a given page.
	 *
	 * @since 0.1.5
	 * @param string|null $consumerUrl
	 * @param int $pageId Mediawiki page ID.
	 * @return int Number of utterances flushed.
	 */
	public function flushUtterancesByPage(
		?string $consumerUrl,
		int $pageId
	): int {
		$remoteWikiHash = self::evaluateRemoteWikiHash( $consumerUrl );
		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
		$results = $dbw->select( self::UTTERANCE_TABLE,
			[ 'wsu_utterance_id' ],
			[
				'wsu_remote_wiki_hash' => $remoteWikiHash,
				'wsu_page_id' => $pageId
			],
			__METHOD__
		);
		return $this->flushUtterances( $dbw, $results );
	}

	/**
	 * Clears database and file backend of all utterances for a given language and voice.
	 * If no voice is set, then all voices will be removed.
	 *
	 * @since 0.1.5
	 * @param string $language ISO 639.
	 * @param string|null $voice Optional name of synthesis voice to limit flush to.
	 * @return int Number of utterances flushed.
	 */
	public function flushUtterancesByLanguageAndVoice( $language, $voice = null ) {
		$conditions = [
			'wsu_lang' => $language
		];
		if ( $voice != null ) {
			$conditions['wsu_voice'] = $voice;
		}
		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
		$results = $dbw->select( self::UTTERANCE_TABLE,
			[ 'wsu_utterance_id' ], $conditions, __METHOD__
		);
		return $this->flushUtterances( $dbw, $results );
	}

	/**
	 * Flushes utterances listed in a result set containing
	 * at least the wsu_utterance_id column.
	 *
	 * In order for return value to increase, the utterance must have been
	 * successfully deleted in all layers, i.e. utterance metadata database row,
	 * utterance audio and synthesis metadata from file store.
	 * E.g. if the utterance audio file is missing and thus not explicitly removed,
	 * but at the same time we managed to remove the utterance metadata from database
	 * and also removed the synthesis metadata file, this will not count as a
	 * successfully removed utterance. It would however be removed from all layers
	 * and it would also cause an out-of-sync warning in the log.
	 *
	 * @note Consider if database should be flushing within a transaction.
	 *
	 * @since 0.1.5
	 * @param IDatabase $dbw Writable database connection.
	 * @param IResultWrapper $results Result set.
	 * @return int Number of utterances that were successfully flushed in all layers.
	 */
	private function flushUtterances( $dbw, $results ) {
		if ( !$results ) {
			return 0;
		}

		// TTL is set when creating files in Swift, so no need to invoke any delete I/O operations.
		$flushInFileBackend = !(
			$this->isWikispeechUtteranceUseSwiftFileBackendExpiring() &&
			$this->getFileBackend() instanceof SwiftFileBackend
		);

		$successfullyFlushedCounter = 0;
		foreach ( $results as $row ) {
			$utteranceId = $row->wsu_utterance_id;

			// 1. delete in database
			$successfullyDeletedTableRow = $dbw->delete(
				self::UTTERANCE_TABLE,
				[ 'wsu_utterance_id' => $utteranceId ],
				__METHOD__
			);
			if ( !$successfullyDeletedTableRow ) {
				$this->logger->warning( __METHOD__ . ': ' .
					'Failed to delete utterance {utteranceId} from database.', [
						'utteranceId' => $utteranceId
				] );
			} else {
				$this->logger->debug( __METHOD__ . ': ' .
					'Flushed out utterance with id {utteranceId} from database', [
						'utteranceId' => $utteranceId
				] );
			}

			// 2. delete in file store.
			if ( $flushInFileBackend ) {
				$successfullyDeletedAudioFile = $this->deleteFileBackendFile(
					$this->audioUrlFactory( $utteranceId ),
					$utteranceId,
					'audio file'
				);
				$successfullyDeletedSynthesisMetadataFile = $this->deleteFileBackendFile(
					$this->synthesisMetadataUrlFactory( $utteranceId ),
					$utteranceId,
					'synthesis metadata file'
				);
				$successfullyDeletedFiles =
					$successfullyDeletedAudioFile && $successfullyDeletedSynthesisMetadataFile;
			} else {
				// The files were marked for automatic deletion using TTL in the Swift create operation.
				$successfullyDeletedFiles = true;
			}

			if ( $successfullyDeletedTableRow && $successfullyDeletedFiles ) {
				$successfullyFlushedCounter++;
			}
		}

		return $successfullyFlushedCounter;
	}

	/**
	 * @since 0.1.5
	 * @param string $src
	 * @param int $utteranceId
	 * @param string $type
	 * @return bool If successfully deleted
	 */
	private function deleteFileBackendFile( $src, $utteranceId, $type ) {
		$synthesisMetadataFile = [
			'src' => $src
		];
		if ( $this->getFileBackend()->fileExists( $synthesisMetadataFile ) ) {
			if ( !$this->getFileBackend()->delete( $synthesisMetadataFile )->isOK() ) {
				$this->logger->warning( __METHOD__ . ': ' .
					'Unable to delete {type} for utterance with identity {utteranceId}.', [
						'utteranceId' => $utteranceId,
						'type' => $type
				] );
				return false;
			} else {
				$this->getFileBackend()->clean( [ 'dir' => $this->urlPathFactory( $utteranceId ) ] );
			}
		} else {
			$this->logger->warning( __METHOD__ . ': ' .
				'Attempted to delete non existing {type} for utterance {utteranceId}.', [
					'utteranceId' => $utteranceId,
					'type' => $type
			] );
			return false;
		}
		$this->logger->debug( __METHOD__ . ': ' .
			'Flushed out file {src}', [ 'src' => $src ] );
		return true;
	}

	/**
	 * Creates a deterministic path based on utterance identity,
	 * causing no more than 1000 files and 10 subdirectories per directory.
	 * (Actually, 2000 files, as we store both .json and .opus)
	 *
	 * Overloading a directory with files often cause performance problems.
	 *
	 * 1 -> /
	 * 12 -> /
	 * 123 -> /
	 * 1234 -> /1/
	 * 12345 -> /1/2/
	 * 123456 -> /1/2/3/
	 * 1234567 -> /1/2/3/4/
	 *
	 * @since 0.1.5
	 * @param int $utteranceId
	 * @return string Path
	 */
	private function urlPathFactory( $utteranceId ) {
		$path = '/';
		$utteranceIdText = strval( $utteranceId );
		$utteranceIdTextLength = strlen( $utteranceIdText );
		for ( $index = 0; $index < $utteranceIdTextLength - 3; $index++ ) {
			$path .= substr( $utteranceIdText, $index, 1 ) . '/';
		}
		return $path;
	}

	/**
	 * @since 0.1.5
	 * @param int $utteranceId Utterance identity.
	 * @return string url used to access object in file store
	 */
	private function audioUrlPrefixFactory( $utteranceId ) {
		return $this->getFileBackend()->getContainerStoragePath( $this->fileBackendContainerName )
			. $this->urlPathFactory( $utteranceId ) . $utteranceId;
	}

	/**
	 * @since 0.1.5
	 * @param int $utteranceId Utterance identity.
	 * @return string url used to access object in file store
	 */
	private function audioUrlFactory( $utteranceId ) {
		return $this->audioUrlPrefixFactory( $utteranceId ) . '.opus';
	}

	/**
	 * @since 0.1.5
	 * @param int $utteranceId Utterance identity.
	 * @return string url used to access object in file store
	 */
	private function synthesisMetadataUrlFactory( $utteranceId ) {
		return $this->audioUrlPrefixFactory( $utteranceId ) . '.json';
	}

	/**
	 * Removes expired utterance and synthesis metadata from the file backend.
	 *
	 * @since 0.1.7
	 * @param MWTimestamp|null $expiredTimestamp File timestamp <= to this value is orphaned.
	 *  Defaults to config value.
	 * @return int Number of expired files flushed
	 */
	public function flushUtterancesByExpirationDateOnFile( $expiredTimestamp = null ) {
		// @note Either this method, or the job,
		// should probably call `flushUtterancesByExpirationDate`
		// to ensure we are not deleting a bunch of files
		// which were scheduled to be deleted together with their db-entries anyway.

		if ( !$expiredTimestamp ) {
			$expiredTimestamp = $this->getWikispeechUtteranceExpirationTimestamp();
		}
		$fileBackend = $this->getFileBackend();
		return $this->recurseFlushUtterancesByExpirationDateOnFile(
			$fileBackend,
			$this->getFileBackend()
				->getContainerStoragePath( $this->fileBackendContainerName ),
			$expiredTimestamp
		);
	}

	/**
	 * @since 0.1.7
	 * @param FileBackend $fileBackend
	 * @param string $directory
	 * @param MWTimestamp $expiredTimestamp
	 * @return int Number of expired files flushed
	 */
	private function recurseFlushUtterancesByExpirationDateOnFile(
		$fileBackend,
		$directory,
		$expiredTimestamp
	) {
		$this->logger->debug( __METHOD__ . ': ' .
			'Processing directory {directory}', [ 'directory' => $directory ] );
		$removedFilesCounter = 0;
		$subdirectories = $fileBackend->getDirectoryList( [
			'dir' => $directory,
			'topOnly' => true,
		] );
		if ( $subdirectories ) {
			foreach ( $subdirectories as $subdirectory ) {
				$removedFilesCounter += $this->recurseFlushUtterancesByExpirationDateOnFile(
					$fileBackend,
					$directory . '/' . $subdirectory,
					$expiredTimestamp
				);
			}
		}
		$files = $fileBackend->getFileList( [
			'dir' => $directory,
			'topOnly' => true,
			'adviseStat' => false
		] );
		if ( $files ) {
			foreach ( $files as $file ) {
				$src = [ 'src' => $directory . '/' . $file ];
				$timestamp = new MWTimestamp( $fileBackend->getFileTimestamp( $src ) );
				$this->logger->debug( __METHOD__ . ': ' .
					'Processing file {src} with timestamp {timestamp}', [
					'src' => $file,
					'timestamp' => $timestamp,
					'expiredTimestamp' => $expiredTimestamp
				] );
				if ( $timestamp <= $expiredTimestamp ) {
					if ( $fileBackend->delete( $src )->isOK() ) {
						$removedFilesCounter++;
						$this->logger->debug( __METHOD__ . ': ' .
							'Deleted expired file {file} #{num}', [
								'file' => $file,
								'num' => $removedFilesCounter
							]
						);
					} else {
						$this->logger->warning( __METHOD__ . ': ' .
							'Unable to delete expired file {file}',
							[ 'file' => $file ]
						);
					}
				}
				unset( $timestamp );
			}
		}
		$this->getFileBackend()->clean( [ 'dir' => $directory ] );
		return $removedFilesCounter;
	}

	/**
	 * Calculates historic timestamp on now-WikispeechUtteranceTimeToLiveDays
	 *
	 * @return MWTimestamp Utterance parts with timestamp <= this is expired.
	 */
	public function getWikispeechUtteranceExpirationTimestamp(): MWTimestamp {
		return MWTimestamp::getInstance(
			strtotime( '-' . $this->getWikispeechUtteranceTimeToLiveDays() . 'days' )
		);
	}

	/**
	 * @return int Number of days an utterance is to exist before being flushed out.
	 */
	private function getWikispeechUtteranceTimeToLiveDays(): int {
		return intval( $this->config->get( 'WikispeechUtteranceTimeToLiveDays' ) );
	}

	/**
	 * @return bool
	 */
	private function isWikispeechUtteranceUseSwiftFileBackendExpiring(): bool {
		return $this->config->get( 'WikispeechUtteranceUseSwiftFileBackendExpiring' );
	}

	/**
	 * Used to evaluate hash of gadget consumer URL,
	 * the remote wiki where the page is located.
	 *
	 * Making changes to this function will probably invalidate all existing cached utterances.
	 *
	 * @since 0.1.9
	 * @param string|null $consumerUrl
	 * @return string|null SHA256 message digest
	 */
	public static function evaluateRemoteWikiHash( ?string $consumerUrl ): ?string {
		if ( $consumerUrl === null ) {
			return null;
		}
		$context = hash_init( 'sha256' );
		$urlParts = parse_url( $consumerUrl );
		if ( isset( $urlParts['host'] ) ) {
			hash_update( $context, mb_strtolower( $urlParts['host'] ) );
		}
		if ( isset( $urlParts['port'] ) ) {
			hash_update( $context, strval( $urlParts['port'] ) );
		}
		if ( isset( $urlParts['path'] ) ) {
			hash_update( $context, $urlParts['path'] );
		}
		return hash_final( $context );
	}

}
