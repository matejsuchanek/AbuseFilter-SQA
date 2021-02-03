<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\Api;

use ApiTestCase;
use FormatJson;
use MediaWiki\Extension\AbuseFilter\Parser\AbuseFilterParser;
use MediaWiki\Extension\AbuseFilter\Parser\ParserFactory;
use MediaWiki\Extension\AbuseFilter\Parser\ParserStatus;

/**
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\Api\CheckMatch
 * @group medium
 * @group API
 */
class CheckMatchTest extends ApiTestCase {

	private function getFactory( AbuseFilterParser $parser = null ) : ParserFactory {
		$factory = $this->createMock( ParserFactory::class );
		if ( $parser !== null ) {
			$factory->expects( $this->atLeastOnce() )
				->method( 'newParser' )
				->willReturn( $parser );
		} else {
			$factory->expects( $this->never() )->method( 'newParser' );
		}
		return $factory;
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute_noPermissions() {
		$this->setExpectedApiException( 'apierror-abusefilter-canttest', 'permissiondenied' );

		$this->setService( ParserFactory::SERVICE_NAME, $this->getFactory() );

		$this->doApiRequest( [
			'action' => 'abusefiltercheckmatch',
			'filter' => 'sampleFilter',
			'vars' => FormatJson::encode( [] ),
		], null, null, self::getTestUser()->getUser() );
	}

	public function provideExecuteOk() {
		return [
			[ true ],
			[ false ],
		];
	}

	/**
	 * @dataProvider provideExecuteOk
	 * @covers ::execute
	 */
	public function testExecute_Ok( bool $expected ) {
		$filter = 'sampleFilter';
		$checkStatus = new ParserStatus( true, false, null, [] );
		$resultStatus = new ParserStatus( $expected, false, null, [] );
		$parser = $this->createMock( AbuseFilterParser::class );
		$parser->expects( $this->once() )
			->method( 'checkSyntax' )->with( $filter )
			->willReturn( $checkStatus );
		$parser->expects( $this->once() )
			->method( 'checkConditions' )->with( $filter )
			->willReturn( $resultStatus );
		$this->setService( ParserFactory::SERVICE_NAME, $this->getFactory( $parser ) );

		$result = $this->doApiRequest( [
			'action' => 'abusefiltercheckmatch',
			'filter' => $filter,
			'vars' => FormatJson::encode( [] ),
		], null, null, self::getTestSysop()->getUser() );

		$this->assertArrayEquals(
			[
				'abusefiltercheckmatch' => [
					'result' => $expected
				]
			],
			$result[0],
			false,
			true
		);
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute_error() {
		$this->setExpectedApiException( 'apierror-abusefilter-badsyntax', 'badsyntax' );
		$filter = 'sampleFilter';
		$status = new ParserStatus( false, false, null, [] );
		$parser = $this->createMock( AbuseFilterParser::class );
		$parser->expects( $this->once() )
			->method( 'checkSyntax' )->with( $filter )
			->willReturn( $status );
		$this->setService( ParserFactory::SERVICE_NAME, $this->getFactory( $parser ) );

		$this->doApiRequest( [
			'action' => 'abusefiltercheckmatch',
			'filter' => $filter,
			'vars' => FormatJson::encode( [] ),
		], null, null, self::getTestSysop()->getUser() );
	}

}
