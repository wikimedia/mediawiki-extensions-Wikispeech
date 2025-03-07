<?php

namespace MediaWiki\Wikispeech\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use FormatJson;
use MediaWiki\Http\HttpRequestFactory;
use Mediawiki\Title\Title;

/**
 * @since 0.1.10
 */
class RemoteWikiPageProvider extends AbstractPageProvider {

	/** @var string */
	private $consumerUrl;

	/** @var HttpRequestFactory */
	private $requestFactory;

	/**
	 * @since 0.1.10
	 * @param string $consumerUrl
	 * @param HttpRequestFactory $requestFactory
	 */
	public function __construct(
		string $consumerUrl,
		HttpRequestFactory $requestFactory
	) {
		$this->consumerUrl = $consumerUrl;
		$this->requestFactory = $requestFactory;
	}

	/**
	 * @since 0.1.10
	 * @param int $revisionId
	 * @return PageRevisionProperties
	 * @throws RemoteWikiPageProviderException If unable to get response from remote wiki.
	 */
	public function loadPageRevisionProperties( int $revisionId ): PageRevisionProperties {
		$request = wfAppendQuery(
			$this->consumerUrl . '/api.php',
			[
				'action' => 'parse',
				'format' => 'json',
				'oldid' => $revisionId,
			]
		);
		$responseString = $this->requestFactory->get( $request, [], __METHOD__ );
		if ( $responseString === null ) {
			throw new RemoteWikiPageProviderException( 'Failed getting response from remote wiki' );
		}
		$response = FormatJson::parse( $responseString )->getValue();
		return new PageRevisionProperties(
			Title::newFromTextThrow( $response->parse->title ),
			$response->parse->pageid
		);
	}

	/**
	 * @since 0.1.10
	 * @param Title $title
	 * @throws RemoteWikiPageProviderException If unable to fetch remote wiki page
	 */
	public function loadData( Title $title ): void {
		$request = wfAppendQuery(
			$this->consumerUrl . '/api.php',
			[
				'action' => 'parse',
				'format' => 'json',
				'page' => $title,
				'prop' => 'text|revid|displaytitle'
			]
		);
		$responseString = $this->requestFactory->get( $request, [], __METHOD__ );
		if ( $responseString === null ) {
			throw new RemoteWikiPageProviderException(
				"Failed to get page with title '$title' from consumer on URL $this->consumerUrl."
			);
		}
		$response = FormatJson::parse( $responseString )->getValue();
		$this->setDisplayTitle( $response->parse->displaytitle );
		$this->setPageContent( $response->parse->text->{'*'} );
		$this->setRevisionId( $response->parse->revid );
	}

	/**
	 * @since 0.1.10
	 * @return string
	 */
	public function getCachedSegmentsKeyComponents(): string {
		return $this->consumerUrl;
	}

}
