<?php

namespace MediaWiki\Wikispeech;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MWException;

/**
 * Parses output from HAProxy stats CSV.
 *
 * Known columns:
 *
 * 0. pxname [LFBS]: proxy name
 * 1. svname [LFBS]: service name
 *  (FRONTEND for frontend, BACKEND for backend, any name for server/listener)
 * 2. qcur [..BS]: current queued requests.
 *  For the backend this reports the number queued without a server assigned.
 * 3. qmax [..BS]: max value of qcur
 * 4. scur [LFBS]: current sessions
 * 5. smax [LFBS]: max sessions
 * 6. slim [LFBS]: configured session limit
 * 7. stot [LFBS]: cumulative number of connections
 * 8. bin [LFBS]: bytes in
 * 9. bout [LFBS]: bytes out
 * 10. dreq [LFB.]: requests denied because of security concerns.
 *  - For tcp this is because of a matched tcp-request content rule.
 *  - For http this is because of a matched http-request or tarpit rule.
 * 11. dresp [LFBS]: responses denied because of security concerns.
 *  - For http this is because of a matched http-request rule, or "option checkcache".
 * 12. ereq [LF..]: request errors. Some of the possible causes are:
 *  - early termination from the client, before the request has been sent.
 *  - read error from the client
 *  - client timeout
 *  - client closed connection
 *  - various bad requests from the client.
 *  - request was tarpitted.
 * 13. econ [..BS]: number of requests that encountered an error trying to connect to
 *  a backend server. The backend stat is the sum of the stat for all servers of that backend,
 *  plus any connection errors not associated with a particular server
 *  (such as the backend having no active servers).
 * 14. eresp [..BS]: response errors. srv_abrt will be counted here also.
 *  Some other errors are:
 *  - write error on the client socket (won't be counted for the server stat)
 *  - failure applying filters to the response.
 * 15. wretr [..BS]: number of times a connection to a server was retried.
 * 16. wredis [..BS]: number of times a request was redispatched to another server.
 *  The server value counts the number of times that server was switched away from.
 * 17. status [LFBS]: status (UP/DOWN/NOLB/MAINT/MAINT(via)...)
 * 18. weight [..BS]: total weight (backend), server weight (server)
 * 19. act [..BS]: number of active servers (backend), server is active (server)
 * 20. bck [..BS]: number of backup servers (backend), server is backup (server)
 * 21. chkfail [...S]: number of failed checks. (Only counts checks failed when the server is up.)
 * 22. chkdown [..BS]: number of UP->DOWN transitions. The backend counter counts transitions
 *  to the whole backend being down, rather than the sum of the counters for each server.
 * 23. lastchg [..BS]: number of seconds since the last UP<->DOWN transition
 * 24. downtime [..BS]: total downtime (in seconds). The value for the backend
 *  is the downtime for the whole backend, not the sum of the server downtime.
 * 25. qlimit [...S]: configured maxqueue for the server,
 *  or nothing in the value is 0 (default, meaning no limit)
 * 26. pid [LFBS]: process id (0 for first instance, 1 for second, ...)
 * 27. iid [LFBS]: unique proxy id
 * 28. sid [L..S]: server id (unique inside a proxy)
 * 29. throttle [...S]: current throttle percentage for the server, when slowstart is active,
 *  or no value if not in slowstart.
 * 30. lbtot [..BS]: total number of times a server was selected, either for new sessions,
 *  or when re-dispatching. The server counter is the number of times that server was selected.
 * 31. tracked [...S]: id of proxy/server if tracking is enabled.
 * 32. type [LFBS]: (0=frontend, 1=backend, 2=server, 3=socket/listener)
 * 33. rate [.FBS]: number of sessions per second over last elapsed second
 * 34. rate_lim [.F..]: configured limit on new sessions per second
 * 35. rate_max [.FBS]: max number of new sessions per second
 * 36. check_status [...S]: status of last health check, one of:
 *  UNK     -> unknown
 *  INI     -> initializing
 *  SOCKERR -> socket error
 *  L4OK    -> check passed on layer 4, no upper layers testing enabled
 *  L4TOUT  -> layer 1-4 timeout
 *  L4CON   -> layer 1-4 connection problem, for example
 *  "Connection refused" (tcp rst) or "No route to host" (icmp)
 *  L6OK    -> check passed on layer 6
 *  L6TOUT  -> layer 6 (SSL) timeout
 *  L6RSP   -> layer 6 invalid response - protocol error
 *  L7OK    -> check passed on layer 7
 *  L7OKC   -> check conditionally passed on layer 7, for example 404 with
 *  disable-on-404
 *  L7TOUT  -> layer 7 (HTTP/SMTP) timeout
 *  L7RSP   -> layer 7 invalid response - protocol error
 *  L7STS   -> layer 7 response error, for example HTTP 5xx
 * 37. check_code [...S]: layer5-7 code, if available
 * 38. check_duration [...S]: time in ms took to finish last health check
 * 39. hrsp_1xx [.FBS]: http responses with 1xx code
 * 40. hrsp_2xx [.FBS]: http responses with 2xx code
 * 41. hrsp_3xx [.FBS]: http responses with 3xx code
 * 42. hrsp_4xx [.FBS]: http responses with 4xx code
 * 43. hrsp_5xx [.FBS]: http responses with 5xx code
 * 44. hrsp_other [.FBS]: http responses with other codes (protocol error)
 * 45. hanafail [...S]: failed health checks details
 * 46. req_rate [.F..]: HTTP requests per second over last elapsed second
 * 47. req_rate_max [.F..]: max number of HTTP requests per second observed
 * 48. req_tot [.F..]: total number of HTTP requests received
 * 49. cli_abrt [..BS]: number of data transfers aborted by the client
 * 50. srv_abrt [..BS]: number of data transfers aborted by the server (inc. in eresp)
 * 51. comp_in [.FB.]: number of HTTP response bytes fed to the compressor
 * 52. comp_out [.FB.]: number of HTTP response bytes emitted by the compressor
 * 53. comp_byp [.FB.]: number of bytes that bypassed the HTTP compressor (CPU/BW limit)
 * 54. comp_rsp [.FB.]: number of HTTP responses that were compressed
 * 55. lastsess [..BS]: number of seconds since last session assigned to server/backend
 * 56. last_chk [...S]: last health check contents or textual error
 * 57. last_agt [...S]: last agent check contents or textual error
 * 58. qtime [..BS]: the average queue time in ms over the 1024 last requests
 * 59. ctime [..BS]: the average connect time in ms over the 1024 last requests
 * 60. rtime [..BS]: the average response time in ms over the 1024 last requests (0 for TCP)
 * 61. ttime [..BS]: the average total session time in ms over the 1024 last requests
 *
 * @since 0.1.10
 */
class HaproxyStatusParser {

	/**
	 * @var array string column name => string[] rows
	 * Use column name rather than column index to be compatible with future changes in HAProxy.
	 * The array value per column name is a list of all rows values for that column,
	 * e.g. data is organized so they can be extracted by column and row,
	 * not by row and columns as in, for example, a relational database.
	 */
	private $valuesByColumnName;

	/** @var int */
	private $numberOfRows;

	/**
	 * @since 0.1.10
	 * @param string $input CSV to be parsed
	 */
	public function __construct( string $input ) {
		$values = [];
		$numberOfRows = 0;
		$parsedHeaders = false;
		$rows = str_getcsv( $input, "\n" );
		$columns = [];
		foreach ( $rows as $row ) {
			$csv = str_getcsv( $row );
			if ( !$parsedHeaders ) {
				if ( $csv[0] === '# pxname' ) {
					$csv[0] = 'pxname';
				}
				foreach ( $csv as $columnName ) {
					$values[$columnName] = [];
					$columns[] = $columnName;
				}
				$parsedHeaders = true;
			} else {
				foreach ( $csv as $index => $columnValue ) {
					// Phan is confused by this $columns[$index] array accessor.
					// @phan-suppress-next-line PhanTypeInvalidDimOffset
					$values[$columns[$index]][] = $columnValue;
				}
				$numberOfRows++;
			}
		}
		$this->valuesByColumnName = $values;
		$this->numberOfRows = $numberOfRows;
	}

	/**
	 * @since 0.1.10
	 * @param string $pxname
	 * @param string $svname
	 * @return int
	 * @throws MWException If no such server in parsed data
	 */
	public function findServerRowIndex(
		string $pxname,
		string $svname
	): int {
		for ( $rowIndex = 0; $rowIndex < $this->numberOfRows; $rowIndex++ ) {
			if (
				$this->valuesByColumnName['pxname'][$rowIndex] === $pxname
				&& $this->valuesByColumnName['svname'][$rowIndex] === $svname
			) {
				return $rowIndex;
			}
		}
		throw new MWException( "No server defined with pxname '$pxname' and svname '$svname'." );
	}

	/**
	 * @since 0.1.10
	 * @param string $pxname
	 * @param string $svname
	 * @param string $columnName
	 * @return string values
	 */
	public function findServerColumnValue(
		string $pxname,
		string $svname,
		string $columnName
	): string {
		return $this->getColumnValue(
			$this->findServerRowIndex( $pxname, $svname ),
			$columnName
		);
	}

	/**
	 * @since 0.1.10
	 * @param int $rowIndex
	 * @param string $columnName
	 * @return string value
	 */
	public function getColumnValue(
		int $rowIndex,
		string $columnName
	): string {
		return $this->valuesByColumnName[$columnName][$rowIndex];
	}

	/**
	 * @since 0.1.10
	 * @return int
	 */
	public function getNumberOfRows(): int {
		return $this->numberOfRows;
	}

	/**
	 * Queue is overloaded if there are already the maximum number of current
	 * connections processed by the backend at the same time as the queue
	 * contains more than X connections waiting for their turn,
	 * where X = $overloadedFactor multiplied with
	 * the maximum number of current connections to the backend.
	 *
	 * @since 0.1.10
	 * @param string $frontendPxName
	 * @param string $frontendSvName
	 * @param string $backendPxName
	 * @param string $backendSvName
	 * @param float $overloadedFactor
	 * @return bool Whether or not connection queue is overloaded
	 */
	public function isQueueOverloaded(
		string $frontendPxName,
		string $frontendSvName,
		string $backendPxName,
		string $backendSvName,
		float $overloadedFactor
	): bool {
		$frontendRowIndex = $this->findServerRowIndex( $frontendPxName, $frontendSvName );
		$frontendCurrentSessions = intval( $this->getColumnValue( $frontendRowIndex, 'scur' ) );

		$backendRowIndex = $this->findServerRowIndex( $backendPxName, $backendSvName );
		$backendSessionsLimit = $this->getColumnValue( $backendRowIndex, 'slim' );
		$backendSessionsLimit = intval( $backendSessionsLimit );

		$maximumFrontendCurrentSessions = $backendSessionsLimit * $overloadedFactor;

		return $frontendCurrentSessions >= $maximumFrontendCurrentSessions;
	}

	/**
	 * Counts number of requests that currently could be sent to the queue
	 * and immediately would be passed down to backend.
	 *
	 * If this value is greater than 0, then the next request sent via the queue
	 * will be immediately processed by the backend.
	 *
	 * If this value is less than 1, then the next connection will be queued,
	 * given that the currently processing requests will not have had time to finish by then.
	 *
	 * If this value is less than 1, then the value is the inverse size of the known queue.
	 * Note that the OS on the HAProxy server might be buffering connections in the TCP-stack
	 * and that HAProxy will not be aware of such connections. A negative number might therefor
	 * not represent a perfect count of current connection lined up in the queue.
	 *
	 * The idea with this function is to see if there are available resources that could
	 * be used for pre-synthesis of utterances during otherwise idle time.
	 *
	 * @since 0.1.10
	 * @param string $frontendPxName
	 * @param string $frontendSvName
	 * @param string $backendPxName
	 * @param string $backendSvName
	 * @return int Positive number if available slots, else inverted size of queue.
	 */
	public function getAvailableNonQueuedConnectionSlots(
		string $frontendPxName,
		string $frontendSvName,
		string $backendPxName,
		string $backendSvName
	): int {
		$frontendRowIndex = $this->findServerRowIndex( $frontendPxName, $frontendSvName );
		$frontendCurrentSessions = intval( $this->getColumnValue( $frontendRowIndex, 'scur' ) );

		$backendRowIndex = $this->findServerRowIndex( $backendPxName, $backendSvName );
		$backendSessionsLimit = $this->getColumnValue( $backendRowIndex, 'slim' );
		$backendSessionsLimit = intval( $backendSessionsLimit );

		return $backendSessionsLimit - $frontendCurrentSessions;
	}

}
