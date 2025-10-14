<?php

namespace MediaWiki\Wikispeech;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use IContextSource;
use MediaWiki\Config\Config;
use MediaWiki\Shell\Shell;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Wikispeech\Segment\SegmentPageFactory;
use MediaWiki\Wikispeech\Utterance\UtteranceGenerator;
use RuntimeException;

/**
 * Combines the utterances from a page into a single audio file.
 *
 * @since 0.1.14
 */

class PageFileGenerator {
	/** @var IContextSource */
	private $context;

	/** @var SegmentPageFactory */
	private $segmentPageFactory;

	/** @var UtteranceGenerator */
	private $utteranceGenerator;

	/** @var VoiceHandler */
	private $voiceHandler;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var Config */
	private $config;

	/**
	 * @since 0.1.14
	 * @param IContextSource $context
	 * @param SegmentPageFactory $segmentPageFactory
	 * @param UtteranceGenerator $utteranceGenerator
	 * @param VoiceHandler $voiceHandler
	 * @param TitleFactory $titleFactory
	 * @param Config $config
	 */
	public function __construct(
		IContextSource $context,
		SegmentPageFactory $segmentPageFactory,
		UtteranceGenerator $utteranceGenerator,
		VoiceHandler $voiceHandler,
		TitleFactory $titleFactory,
		Config $config
	) {
		$this->context = $context;
		$this->segmentPageFactory = $segmentPageFactory;
		$this->utteranceGenerator = $utteranceGenerator;
		$this->utteranceGenerator->setContext( $this->context );
		$this->voiceHandler = $voiceHandler;
		$this->titleFactory = $titleFactory;
		$this->config = $config;
	}

	/**
	 * Create an audio file out of the concatenated utternaces from a page.
	 *
	 * @since 0.1.14
	 * @param string $page The name of the page.
	 * @param string $language Language for speech syntesis. TODO: This should
	 *  be retrieved from the page.
	 * @param string|null $consumerUrl The URL of the consumer wiki where the
	 *  page is.
	 * @throws RuntimeException if title is invalid.
	 */
	public function makePageFile( string $page, string $language, ?string $consumerUrl = null ) {
		$this->ensureProgramsRun();

		$title = $this->titleFactory->newFromText( $page );
		if ( !$title ) {
			throw new RuntimeException( 'Invalid title.' );
		}

		[ $segments, $revisionId ] = $this->segmentPage( $title, $consumerUrl );
		$voice = $this->voiceHandler->getDefaultVoice( $language );
		$mergedFile = tmpfile();
		$mergedFilePath = stream_get_meta_data( $mergedFile )['uri'];
		foreach ( $segments as $segment ) {
			$utterance = $this->utteranceGenerator->getUtteranceForRevisionAndSegment(
				$voice,
				$language,
				$revisionId,
				$segment->getHash(),
				$consumerUrl
			);
			$audioData = base64_decode( $utterance['audio'] );
			$writeFailed = fwrite( $mergedFile, $audioData ) == false;
			if ( $writeFailed ) {
				throw new RuntimeException( "Couldn't write to file: '$mergedFilePath'." );
			}
		}

		// We need to decode and then re-encode the combined file to make it
		// a proper Opus file.
		$decodedFile = tmpfile();
		if ( $decodedFile === false ) {
			throw new RuntimeException( "Couldn't create temporary file." );
		}

		$decodedFilePath = stream_get_meta_data( $decodedFile )['uri'];
		$this->runCommand( 'opusdec', '--force-wav', $mergedFilePath, $decodedFilePath );
		$dirPath = $this->config->get( 'UploadDirectory' ) . '/page-audio';
		// Make sure the directory exists.
		if ( !is_dir( $dirPath ) ) {
			$createdDirectory = mkdir( $dirPath );
			if ( !$createdDirectory ) {
				throw new RuntimeException( "Couldn't create directory for page files: '$dirPath'." );
			}
		}
		$reencodedFilePath = "$dirPath/$title.opus";
		$this->runCommand( 'opusenc', $decodedFilePath, $reencodedFilePath );
	}

	/**
	 * Ensure that we can run the programs required.
	 *
	 * Checks both that the general config allows running shell programs and
	 * that the required programs can run.
	 *
	 * @throws RuntimeException if programs can't be run.
	 */
	private function ensureProgramsRun() {
		if ( Shell::isDisabled() ) {
			throw new RuntimeException( 'Shell execution disabled.' );
		}

		// This is just to check that the Opus programs are good to run. The
		// help flag is because running without arguments results in exit
		// code 1.
		$this->runCommand( 'opusdec', '--help' );
		$this->runCommand( 'opusenc', '--help' );
	}

	/**
	 * Segment a page.
	 *
	 * @param Title $title Title of the page.
	 * @param string|null $consumerUrl URL for the consumer wiki, if used.
	 * @throws RuntimeException if page doesn't exist.
	 * @return array First item is an array of `Segment`s. Second item is the
	 *  revision ID.
	 */
	private function segmentPage( Title $title, ?string $consumerUrl ) {
		$this->segmentPageFactory
			->setUseSegmentsCache( true )
			->setUseRevisionPropertiesCache( true )
			->setContextSource( $this->context )
			->setRequirePageRevisionProperties( true );
		if ( $consumerUrl ) {
			$this->segmentPageFactory
				->setConsumerUrl( $consumerUrl );
		}
		if ( !$consumerUrl && !$title->exists() ) {
			throw new RuntimeException( "Page doesn't exist." );
		}

		$response = $this->segmentPageFactory->segmentPage( $title );
		$revisionId = $response->getRevisionId();
		$segments = $response->getSegments()->getSegments();

		return [ $segments, $revisionId ];
	}

	/**
	 * Run a shell command.
	 *
	 * @param string|string[] ...$command Command to run @link Shell::command
	 * @throws RuntimeException If the command fails, i.e. exit code isn't 0.
	 */
	private function runCommand( ...$command ) {
		$command = Shell::command( $command );
		$result = $command->execute();
		if ( $result->getExitCode() !== 0 ) {
			throw new RuntimeException( 'Command failed: ' . $result->getStderr() );
		}
	}
}
