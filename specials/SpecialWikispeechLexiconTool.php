<?php
/**
 * Wikispeech lexicon tool SpecialPage for Wikispeech extension
 *
 * @file
 * @ingroup Extensions
 */
class SpecialWikispeechLexiconTool extends SpecialPage {
	public function __construct() {
		parent::__construct( 'wikispeechlexicontool' );
	}

	/**
	 * Show the page to the user
	 *
	 * @param string $sub The subpage string argument (if any).
	 */
	public function execute( $sub ) {
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'special-wikispeech-lexicon-tool-title' ) );
		$out->addHelpLink( 'How to become a MediaWiki hacker' );
		$out->addWikiMsg( 'special-wikispeech-lexicon-tool-intro' );
	}

	/**
	 * @see SpecialPage::getGroupName
	 *
	 * @return string
	 */
	protected function getGroupName() {
		return 'wiki';
	}
}
