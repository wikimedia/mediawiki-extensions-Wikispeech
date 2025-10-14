<?php

namespace MediaWiki\Wikispeech;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use Maintenance;
use MediaWiki\MediaWikiServices;
use RequestContext;

/** @var string MediaWiki installation path */
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Maintenance script to generate audio files for pages.
 *
 * @since 0.1.14
 */
class GeneratePageFile extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'Wikispeech' );
		$this->addDescription(
			'Generate an audio file from a page.'
			. 'This requires the programs `opusdec` and `opusenc` to be'
			. "installed. See https://opus-codec.org or your OS' repository."
		);
		$this->addOption(
			'page',
			'Name of the page to generate from',
			true,
			true,
			'p'
		);
		$this->addOption(
			'language',
			'Generate using this language.',
			true,
			true,
			'l'
		);
		$this->addOption(
			'consumer',
			'URL to consumer wiki',
			withArg: true,
			shortName: 'c'
		);
	}

	/**
	 * @return bool success
	 */
	public function execute() {
		$context = new RequestContext();
		$generator = new PageFileGenerator(
			$context,
			WikispeechServices::getSegmentPageFactory(),
			WikispeechServices::getUtteranceGenerator(),
			WikispeechServices::getVoiceHandler(),
			MediaWikiServices::getInstance()->getTitleFactory(),
			$this->getConfig()
		);

		$page = $this->getOption( 'page', null );
		$language = $this->getOption( 'language', null );
		$consumerUrl = $this->getOption( 'consumer', null );
		$generator->makePageFile( $page, $language, $consumerUrl );

		return true;
	}
}

/** @var string This class, required to start via Maintenance. */
$maintClass = GeneratePageFile::class;

require_once RUN_MAINTENANCE_IF_MAIN;
