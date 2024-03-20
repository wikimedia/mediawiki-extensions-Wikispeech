<?php

namespace MediaWiki\Wikispeech\Tests\Unit;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */
use HashConfig;
use InvalidArgumentException;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Wikispeech\SpeechoidConnector;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Wikispeech\SpeechoidConnector
 */
class SpeechoidConnectorTest extends MediaWikiUnitTestCase {

	/** @var HashConfig */
	private $config;

	/** @var HttpRequestFactory|MockObject */
	private $requestFactory;

	/** @var SpeechoidConnector|MockObject */
	private $speechoidConnector;

	protected function setUp(): void {
		$this->requestFactory = $this->createMock( HttpRequestFactory::class );
		$this->config = new HashConfig();
		$this->config->set( 'WikispeechSpeechoidResponseTimeoutSeconds', null );
		$this->config->set( 'WikispeechSpeechoidUrl', 'speechoid.url' );
		$this->config->set( 'WikispeechSymbolSetUrl', 'symbolset.url' );
		$this->config->set( 'WikispeechSpeechoidHaproxyQueueUrl', 'haproxy.speechoid.url' );
		$this->config->set( 'WikispeechSpeechoidHaproxyStatsUrl', 'haproxy.stats.url' );
		$this->speechoidConnector = $this->getMockBuilder( SpeechoidConnector::class )
			->onlyMethods( [ 'findLexiconByLanguage' ] )
			->setConstructorArgs( [
				$this->config,
				$this->requestFactory
			] )
			->getMock();
	}

	public function testSynthesize_textGiven_sendRequestWithTextAsInput() {
		$this->requestFactory
			->method( 'post' )
			->willReturn( '{"speechoid": "response"}' );
		$this->requestFactory
			->expects( $this->once() )
			->method( 'post' )
			->with(
				'haproxy.speechoid.url',
				$this->equalTo( [ 'postData' => [
					'lang' => 'en',
					'voice' => 'en-voice',
					'input' => 'say this'
				] ] )
			);
		$response = $this->speechoidConnector->synthesize(
			'en',
			'en-voice',
			[ 'text' => 'say this' ]
		);
		$this->assertSame( [ 'speechoid' => 'response' ], $response );
	}

	public function testSynthesize_ipaGiven_sendRequestWithIpaAsInputAndIpaAsType() {
		$this->requestFactory
			->method( 'post' )
			->willReturn( '{"speechoid": "response"}' );
		$this->requestFactory
			->expects( $this->once() )
			->method( 'post' )
			->with(
				'haproxy.speechoid.url',
				$this->equalTo( [ 'postData' => [
					'lang' => 'en',
					'voice' => 'en-voice',
					'input' => 'seɪ.ðɪs',
					'input_type' => 'ipa'
				] ] )
			);
		$response = $this->speechoidConnector->synthesize(
			'en',
			'en-voice',
			[ 'ipa' => 'seɪ.ðɪs' ]
		);
		$this->assertSame( [ 'speechoid' => 'response' ], $response );
	}

	public function testSynthesize_textIpaOrSsmlNotInParameters_throwException() {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage(
			'$parameters must contain one of "text", "ipa" or "ssml".'
		);

		$this->speechoidConnector->synthesize(
			'en',
			'en-voice',
			[]
		);
	}

	public function testToIpa_LangugesSymbolsetStringGiven_giveIpa() {
		$this->speechoidConnector
			->method( 'findLexiconByLanguage' )
			->willReturn( 'lexicon-name' );
		$returnMap = [
			'speechoid.url/lexserver/lexicon/info/lexicon-name' => '{"symbolSetName": "target-symbol-set"}',
			'symbolset.url/mapper/map/target-symbol-set/ipa/transcription' => '{"Result": "ipa transcription"}',
		];
		$this->requestFactory
			->method( 'get' )
			->willReturnCallback( static fn ( $url ) => $returnMap[$url] );

		$status = $this->speechoidConnector->toIpa(
			'transcription',
			'en'
		);

		$this->assertTrue( $status->isOk() );
		$this->assertSame( 'ipa transcription', $status->getValue() );
	}

	public function testFromIpa_ipaGiven_giveLangugesSymbolsetString() {
		$this->speechoidConnector
			->method( 'findLexiconByLanguage' )
			->willReturn( 'lexicon-name' );

		$returnMap = [
			'speechoid.url/lexserver/lexicon/info/lexicon-name' => '{"symbolSetName": "target-symbol-set"}',
			'symbolset.url/mapper/map/ipa/target-symbol-set/ipa%20transcription' => '{"Result": "transcription"}',
		];
		$this->requestFactory
			->method( 'get' )
			->willReturnCallback( static fn ( $url ) => $returnMap[$url] );

		$status = $this->speechoidConnector->fromIpa(
			'ipa transcription',
			'en'
		);

		$this->assertTrue( $status->isOk() );
		$this->assertSame( 'transcription', $status->getValue() );
	}

	public function testDeleteLexiconEntry_goodLexiconNameAndIdentityGiven_isOK() {
		$this->config->set( 'WikispeechSpeechoidUrl', 'speechoid.url' );
		$this->requestFactory
			->method( 'get' )
			->with( 'speechoid.url/lexserver/lexicon/delete_entry/lexiconName/0' )
			->willReturn(
				"deleted entry id 'identity' from lexicon 'lexiconName'"
			);
		$status = $this->speechoidConnector->deleteLexiconEntry(
			'lexiconName',
			0
		);
		$this->assertTrue( $status->isOK() );
	}

	public function testDeleteLexiconEntry_badLexiconNameGiven_isNotOK() {
		$this->config->set( 'WikispeechSpeechoidUrl', 'speechoid.url' );
		$this->requestFactory
			->method( 'get' )
			->with( 'speechoid.url/lexserver/lexicon/delete_entry/lexiconName/0' )
			->willReturn(
			// phpcs:ignore Generic.Files.LineLength.TooLong
				"couldn't parse lexicon ref : : ParseLexRef: failed to split full lexicon name into two colon separated parts: 'lexiconName'"
			);
		$status = $this->speechoidConnector->deleteLexiconEntry(
			'lexiconName',
			0
		);
		$this->assertFalse( $status->isOK() );
	}

	public function testDeleteLexiconEntry_nonExistingLexiconNameGiven_isNotOK() {
		$this->config->set( 'WikispeechSpeechoidUrl', 'speechoid.url' );
		$this->requestFactory
			->method( 'get' )
			->with( 'speechoid.url/lexserver/lexicon/delete_entry/lexiconName/0' )
			->willReturn(
			// phpcs:ignore Generic.Files.LineLength.TooLong
				"failed to detele entry id '1' in lexicon 'lexiconName' : DBManager.DeleteEntry: no such db 'lexiconName'"
			);
		$status = $this->speechoidConnector->deleteLexiconEntry(
			'lexiconName',
			0
		);
		$this->assertFalse( $status->isOK() );
	}

	public function testDeleteLexiconEntry_nonExistingIdentityGiven_isNotOK() {
		$this->config->set( 'WikispeechSpeechoidUrl', 'speechoid.url' );
		$this->requestFactory
			->method( 'get' )
			->with( 'speechoid.url/lexserver/lexicon/delete_entry/lexiconName/0' )
			->willReturn(
			// phpcs:ignore Generic.Files.LineLength.TooLong
				"failed to detele entry id 'identity' in lexicon 'lexiconName' : dbapi.deleteEntry failed to delete entry with id 'identity' from lexicon 'lexiconName'"
			);
		$status = $this->speechoidConnector->deleteLexiconEntry(
			'lexiconName',
			0
		);
		$this->assertFalse( $status->isOK() );
	}

	public function testIsQueueOverloaded() {
		$this->config->set( 'WikispeechSpeechoidHaproxyFrontendPxName', 'frontend_1' );
		$this->config->set( 'WikispeechSpeechoidHaproxyFrontendSvName', 'FRONTEND' );
		$this->config->set( 'WikispeechSpeechoidHaproxyBackendPxName', 'backend_1' );
		$this->config->set( 'WikispeechSpeechoidHaproxyBackendSvName', 'server_1' );

		// phpcs:disable
		$this->requestFactory
			->method( 'get' )
			->with( 'haproxy.stats.url/stats;csv;norefresh' )
			->willReturn(
				"# pxname,svname,qcur,qmax,scur,smax,slim,stot,bin,bout,dreq,dresp,ereq,econ,eresp,wretr,wredis,status,weight,act,bck,chkfail,chkdown,lastchg,downtime,qlimit,pid,iid,sid,throttle,lbtot,tracked,type,rate,rate_lim,rate_max,check_status,check_code,check_duration,hrsp_1xx,hrsp_2xx,hrsp_3xx,hrsp_4xx,hrsp_5xx,hrsp_other,hanafail,req_rate,req_rate_max,req_tot,cli_abrt,srv_abrt,comp_in,comp_out,comp_byp,comp_rsp,lastsess,last_chk,last_agt,qtime,ctime,rtime,ttime,agent_status,agent_code,agent_duration,check_desc,agent_desc,check_rise,check_fall,check_health,agent_rise,agent_fall,agent_health,addr,cookie,mode,algo,conn_rate,conn_rate_max,conn_tot,intercepted,dcon,dses,
frontend_1,FRONTEND,,,4,6,2000,177,112516,3918123,0,0,0,,,,,OPEN,,,,,,,,,1,1,0,,,,0,6,0,21,,,,,,,,,,,0,0,0,,,0,0,0,0,,,,,,,,,,,,,,,,,,,,,tcp,,6,21,177,,0,0,
backend_1,server_1,0,0,1,1,2,177,112516,3918123,,0,,0,0,0,0,no check,1,1,0,,,57,,,1,2,1,,133,,2,6,,21,,,,,,,,,,,,,,0,0,,,,,1,,,5,0,0,18,,,,,,,,,,,,,,tcp,,,,,,,,
backend_1,BACKEND,0,5,0,6,200,177,112516,3918123,0,0,,0,0,0,0,UP,1,1,0,,0,57,0,,1,2,0,,133,,1,6,,21,,,,,,,,,,,,,,0,0,0,0,0,0,1,,,5,0,0,18,,,,,,,,,,,,,,tcp,,,,,,,,
stats,FRONTEND,,,2,2,2000,7,8606,79568,0,0,0,,,,,OPEN,,,,,,,,,1,3,0,,,,0,1,0,2,,,,0,5,0,0,5,0,,1,2,11,,,0,0,0,0,,,,,,,,,,,,,,,,,,,,,http,,1,2,7,6,0,0,
" );
		// phpcs:enable

		// csv above means:
		// 4 connections in frontend queue
		// 1 connection in backend
		// 2 connection limit in backend

		// overloaded when 1 connection in frontend queue
		$this->config->set( 'WikispeechSpeechoidHaproxyOverloadFactor', 0.5 );
		$this->assertTrue( $this->speechoidConnector->isQueueOverloaded() );

		// overloaded when 4 connections in frontend queue
		$this->config->set( 'WikispeechSpeechoidHaproxyOverloadFactor', 2 );
		$this->assertTrue( $this->speechoidConnector->isQueueOverloaded() );

		// overloaded when 8 connections in frontend queue
		$this->config->set( 'WikispeechSpeechoidHaproxyOverloadFactor', 4 );
		$this->assertFalse( $this->speechoidConnector->isQueueOverloaded() );
	}

	public function testGetAvailableNonQueuedConnectionSlots_negativeResponse() {
		$this->config->set( 'WikispeechSpeechoidHaproxyFrontendPxName', 'frontend_1' );
		$this->config->set( 'WikispeechSpeechoidHaproxyFrontendSvName', 'FRONTEND' );
		$this->config->set( 'WikispeechSpeechoidHaproxyBackendPxName', 'backend_1' );
		$this->config->set( 'WikispeechSpeechoidHaproxyBackendSvName', 'server_1' );

		// phpcs:disable
		$this->requestFactory
			->method( 'get' )
			->with( 'haproxy.stats.url/stats;csv;norefresh' )
			->willReturn(
				"# pxname,svname,qcur,qmax,scur,smax,slim,stot,bin,bout,dreq,dresp,ereq,econ,eresp,wretr,wredis,status,weight,act,bck,chkfail,chkdown,lastchg,downtime,qlimit,pid,iid,sid,throttle,lbtot,tracked,type,rate,rate_lim,rate_max,check_status,check_code,check_duration,hrsp_1xx,hrsp_2xx,hrsp_3xx,hrsp_4xx,hrsp_5xx,hrsp_other,hanafail,req_rate,req_rate_max,req_tot,cli_abrt,srv_abrt,comp_in,comp_out,comp_byp,comp_rsp,lastsess,last_chk,last_agt,qtime,ctime,rtime,ttime,agent_status,agent_code,agent_duration,check_desc,agent_desc,check_rise,check_fall,check_health,agent_rise,agent_fall,agent_health,addr,cookie,mode,algo,conn_rate,conn_rate_max,conn_tot,intercepted,dcon,dses,
frontend_1,FRONTEND,,,4,6,2000,177,112516,3918123,0,0,0,,,,,OPEN,,,,,,,,,1,1,0,,,,0,6,0,21,,,,,,,,,,,0,0,0,,,0,0,0,0,,,,,,,,,,,,,,,,,,,,,tcp,,6,21,177,,0,0,
backend_1,server_1,0,0,1,1,2,177,112516,3918123,,0,,0,0,0,0,no check,1,1,0,,,57,,,1,2,1,,133,,2,6,,21,,,,,,,,,,,,,,0,0,,,,,1,,,5,0,0,18,,,,,,,,,,,,,,tcp,,,,,,,,
backend_1,BACKEND,0,5,0,6,200,177,112516,3918123,0,0,,0,0,0,0,UP,1,1,0,,0,57,0,,1,2,0,,133,,1,6,,21,,,,,,,,,,,,,,0,0,0,0,0,0,1,,,5,0,0,18,,,,,,,,,,,,,,tcp,,,,,,,,
stats,FRONTEND,,,2,2,2000,7,8606,79568,0,0,0,,,,,OPEN,,,,,,,,,1,3,0,,,,0,1,0,2,,,,0,5,0,0,5,0,,1,2,11,,,0,0,0,0,,,,,,,,,,,,,,,,,,,,,http,,1,2,7,6,0,0,
" );
		// phpcs:enable

		// csv above means:
		// 4 connections in frontend queue
		// 1 connection in backend
		// 2 connection limit in backend

		$this->assertSame( -2, $this->speechoidConnector->getAvailableNonQueuedConnectionSlots() );
	}

	public function testGetAvailableNonQueuedConnectionSlots_zeroResponse() {
		$this->config->set( 'WikispeechSpeechoidHaproxyFrontendPxName', 'frontend_1' );
		$this->config->set( 'WikispeechSpeechoidHaproxyFrontendSvName', 'FRONTEND' );
		$this->config->set( 'WikispeechSpeechoidHaproxyBackendPxName', 'backend_1' );
		$this->config->set( 'WikispeechSpeechoidHaproxyBackendSvName', 'server_1' );

		// phpcs:disable
		$this->requestFactory
			->method( 'get' )
			->with( 'haproxy.stats.url/stats;csv;norefresh' )
			->willReturn(
				"# pxname,svname,qcur,qmax,scur,smax,slim,stot,bin,bout,dreq,dresp,ereq,econ,eresp,wretr,wredis,status,weight,act,bck,chkfail,chkdown,lastchg,downtime,qlimit,pid,iid,sid,throttle,lbtot,tracked,type,rate,rate_lim,rate_max,check_status,check_code,check_duration,hrsp_1xx,hrsp_2xx,hrsp_3xx,hrsp_4xx,hrsp_5xx,hrsp_other,hanafail,req_rate,req_rate_max,req_tot,cli_abrt,srv_abrt,comp_in,comp_out,comp_byp,comp_rsp,lastsess,last_chk,last_agt,qtime,ctime,rtime,ttime,agent_status,agent_code,agent_duration,check_desc,agent_desc,check_rise,check_fall,check_health,agent_rise,agent_fall,agent_health,addr,cookie,mode,algo,conn_rate,conn_rate_max,conn_tot,intercepted,dcon,dses,
frontend_1,FRONTEND,,,2,6,2000,177,112516,3918123,0,0,0,,,,,OPEN,,,,,,,,,1,1,0,,,,0,6,0,21,,,,,,,,,,,0,0,0,,,0,0,0,0,,,,,,,,,,,,,,,,,,,,,tcp,,6,21,177,,0,0,
backend_1,server_1,0,0,2,1,2,177,112516,3918123,,0,,0,0,0,0,no check,1,1,0,,,57,,,1,2,1,,133,,2,6,,21,,,,,,,,,,,,,,0,0,,,,,1,,,5,0,0,18,,,,,,,,,,,,,,tcp,,,,,,,,
backend_1,BACKEND,0,5,0,6,200,177,112516,3918123,0,0,,0,0,0,0,UP,1,1,0,,0,57,0,,1,2,0,,133,,1,6,,21,,,,,,,,,,,,,,0,0,0,0,0,0,1,,,5,0,0,18,,,,,,,,,,,,,,tcp,,,,,,,,
stats,FRONTEND,,,2,2,2000,7,8606,79568,0,0,0,,,,,OPEN,,,,,,,,,1,3,0,,,,0,1,0,2,,,,0,5,0,0,5,0,,1,2,11,,,0,0,0,0,,,,,,,,,,,,,,,,,,,,,http,,1,2,7,6,0,0,
" );
		// phpcs:enable

		// csv above means:
		// 2 connections in frontend queue
		// 2 connection in backend
		// 2 connection limit in backend

		$this->assertSame( 0, $this->speechoidConnector->getAvailableNonQueuedConnectionSlots() );
	}

	public function testGetAvailableNonQueuedConnectionSlots_positiveResponse() {
		$this->config->set( 'WikispeechSpeechoidHaproxyFrontendPxName', 'frontend_1' );
		$this->config->set( 'WikispeechSpeechoidHaproxyFrontendSvName', 'FRONTEND' );
		$this->config->set( 'WikispeechSpeechoidHaproxyBackendPxName', 'backend_1' );
		$this->config->set( 'WikispeechSpeechoidHaproxyBackendSvName', 'server_1' );

		// phpcs:disable
		$this->requestFactory
			->method( 'get' )
			->with( 'haproxy.stats.url/stats;csv;norefresh' )
			->willReturn(
				"# pxname,svname,qcur,qmax,scur,smax,slim,stot,bin,bout,dreq,dresp,ereq,econ,eresp,wretr,wredis,status,weight,act,bck,chkfail,chkdown,lastchg,downtime,qlimit,pid,iid,sid,throttle,lbtot,tracked,type,rate,rate_lim,rate_max,check_status,check_code,check_duration,hrsp_1xx,hrsp_2xx,hrsp_3xx,hrsp_4xx,hrsp_5xx,hrsp_other,hanafail,req_rate,req_rate_max,req_tot,cli_abrt,srv_abrt,comp_in,comp_out,comp_byp,comp_rsp,lastsess,last_chk,last_agt,qtime,ctime,rtime,ttime,agent_status,agent_code,agent_duration,check_desc,agent_desc,check_rise,check_fall,check_health,agent_rise,agent_fall,agent_health,addr,cookie,mode,algo,conn_rate,conn_rate_max,conn_tot,intercepted,dcon,dses,
frontend_1,FRONTEND,,,2,6,2000,177,112516,3918123,0,0,0,,,,,OPEN,,,,,,,,,1,1,0,,,,0,6,0,21,,,,,,,,,,,0,0,0,,,0,0,0,0,,,,,,,,,,,,,,,,,,,,,tcp,,6,21,177,,0,0,
backend_1,server_1,0,0,2,1,4,177,112516,3918123,,0,,0,0,0,0,no check,1,1,0,,,57,,,1,2,1,,133,,2,6,,21,,,,,,,,,,,,,,0,0,,,,,1,,,5,0,0,18,,,,,,,,,,,,,,tcp,,,,,,,,
backend_1,BACKEND,0,5,0,6,200,177,112516,3918123,0,0,,0,0,0,0,UP,1,1,0,,0,57,0,,1,2,0,,133,,1,6,,21,,,,,,,,,,,,,,0,0,0,0,0,0,1,,,5,0,0,18,,,,,,,,,,,,,,tcp,,,,,,,,
stats,FRONTEND,,,2,2,2000,7,8606,79568,0,0,0,,,,,OPEN,,,,,,,,,1,3,0,,,,0,1,0,2,,,,0,5,0,0,5,0,,1,2,11,,,0,0,0,0,,,,,,,,,,,,,,,,,,,,,http,,1,2,7,6,0,0,
" );
		// phpcs:enable

		// csv above means:
		// 2 connections in frontend queue
		// 2 connection in backend
		// 4 connection limit in backend

		$this->assertSame( 2, $this->speechoidConnector->getAvailableNonQueuedConnectionSlots() );
	}

	/**
	 * @dataProvider unparseUrlProvider
	 * @param string $url
	 */
	public function testUnparseUrl_parseUrl_unparsedIsSame( string $url ) {
		/** @var TestingAccessWrapper|SpeechoidConnector $wrapper */
		$wrapper = TestingAccessWrapper::newFromObject( $this->speechoidConnector );
		$parsedUrl = parse_url( $url );
		$unparsedUrl = $wrapper->unparseUrl( $parsedUrl );
		$this->assertSame( $url, $unparsedUrl );
	}

	public static function unparseUrlProvider() {
		return [
			[ 'http://usr:pss@example.com:81/mypath/myfile.html?a=b&b[]=2&b[]=3#myfragment' ],
			[ 'http://localhost:10000' ],
			[ 'http://localhost:10000/' ],
			[ 'http://sv.wikipedia.org/wiki/Main_Page' ],
			[ 'https://sv.wikipedia.org/wiki/Main_Page' ]
		];
	}

	public function testDefaultUrls_onlySpeechoidUrl_defaultSet() {
		$requestFactory = $this->createMock( HttpRequestFactory::class );
		$config = new HashConfig();
		$config->set( 'WikispeechSpeechoidResponseTimeoutSeconds', null );
		$config->set( 'WikispeechSpeechoidUrl', 'http://speechoid.url:10000' );
		$config->set( 'WikispeechSymbolSetUrl', '' );
		$config->set( 'WikispeechSpeechoidHaproxyQueueUrl', '' );
		$config->set( 'WikispeechSpeechoidHaproxyStatsUrl', '' );
		/** @var TestingAccessWrapper|SpeechoidConnector $speechoidConnector */
		$speechoidConnector = TestingAccessWrapper::newFromObject(
			new SpeechoidConnector( $config, $requestFactory )
		);
		$this->assertSame( 'http://speechoid.url:8771', $speechoidConnector->symbolSetUrl );
		$this->assertSame( 'http://speechoid.url:10001', $speechoidConnector->haproxyQueueUrl );
		$this->assertSame( 'http://speechoid.url:10002', $speechoidConnector->haproxyStatsUrl );
	}

}
