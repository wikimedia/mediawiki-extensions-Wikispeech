<?php

namespace MediaWiki\Wikispeech\Tests\Unit;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Wikispeech\HaproxyStatusParser;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Wikispeech\HaproxyStatusParser
 */
class HaproxyStatusParserTest extends MediaWikiUnitTestCase {

	public function testParseCsv_fetchesRowAndColumn() {
		// phpcs:disable
		$input = "# pxname,svname,qcur,qmax,scur,smax,slim,stot,bin,bout,dreq,dresp,ereq,econ,eresp,wretr,wredis,status,weight,act,bck,chkfail,chkdown,lastchg,downtime,qlimit,pid,iid,sid,throttle,lbtot,tracked,type,rate,rate_lim,rate_max,check_status,check_code,check_duration,hrsp_1xx,hrsp_2xx,hrsp_3xx,hrsp_4xx,hrsp_5xx,hrsp_other,hanafail,req_rate,req_rate_max,req_tot,cli_abrt,srv_abrt,comp_in,comp_out,comp_byp,comp_rsp,lastsess,last_chk,last_agt,qtime,ctime,rtime,ttime,agent_status,agent_code,agent_duration,check_desc,agent_desc,check_rise,check_fall,check_health,agent_rise,agent_fall,agent_health,addr,cookie,mode,algo,conn_rate,conn_rate_max,conn_tot,intercepted,dcon,dses,
frontend_1,FRONTEND,,,0,6,2000,177,112516,3918123,0,0,0,,,,,OPEN,,,,,,,,,1,1,0,,,,0,6,0,21,,,,,,,,,,,0,0,0,,,0,0,0,0,,,,,,,,,,,,,,,,,,,,,tcp,,6,21,177,,0,0,
backend_1,server_1,0,0,0,1,1,177,112516,3918123,,0,,0,0,0,0,no check,1,1,0,,,57,,,1,2,1,,133,,2,6,,21,,,,,,,,,,,,,,0,0,,,,,1,,,5,0,0,18,,,,,,,,,,,,,,tcp,,,,,,,,
backend_1,BACKEND,0,5,0,6,200,177,112516,3918123,0,0,,0,0,0,0,UP,1,1,0,,0,57,0,,1,2,0,,133,,1,6,,21,,,,,,,,,,,,,,0,0,0,0,0,0,1,,,5,0,0,18,,,,,,,,,,,,,,tcp,,,,,,,,
stats,FRONTEND,,,2,2,2000,7,8606,79568,0,0,0,,,,,OPEN,,,,,,,,,1,3,0,,,,0,1,0,2,,,,0,5,0,0,5,0,,1,2,11,,,0,0,0,0,,,,,,,,,,,,,,,,,,,,,http,,1,2,7,6,0,0,
";
		// phpcs:enable

		$parser = new HaproxyStatusParser( $input );
		$this->assertSame( 4, $parser->getNumberOfRows() );

		$this->assertSame( 0, $parser->findServerRowIndex( 'frontend_1', 'FRONTEND' ) );
		$this->assertSame( 1, $parser->findServerRowIndex( 'backend_1', 'server_1' ) );
		$this->assertSame( 2, $parser->findServerRowIndex( 'backend_1', 'BACKEND' ) );
		$this->assertSame( 3, $parser->findServerRowIndex( 'stats', 'FRONTEND' ) );

		$this->assertSame(
			'6',
			$parser->findServerColumnValue( 'frontend_1', 'FRONTEND', 'smax' )
		);
	}

}
