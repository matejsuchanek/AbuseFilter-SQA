<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\Api;

use ApiTestCase;
use MediaWiki\Extension\AbuseFilter\Parser\AbuseFilterParser;
use MediaWiki\Extension\AbuseFilter\Parser\AFPUserVisibleException;
use MediaWiki\Extension\AbuseFilter\Parser\ParserFactory;
use MediaWiki\Extension\AbuseFilter\Parser\ParserStatus;
use MediaWiki\Extension\AbuseFilter\Parser\UserVisibleWarning;

/**
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\Api\CheckSyntax
 * @group medium
 */
class CheckSyntaxTest extends ApiTestCase {

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
		$this->setExpectedApiException( 'apierror-abusefilter-cantcheck', 'permissiondenied' );

		$this->setService( ParserFactory::SERVICE_NAME, $this->getFactory() );

		$this->doApiRequest( [
			'action' => 'abusefilterchecksyntax',
			'filter' => 'sampleFilter',
		], null, null, self::getTestUser()->getUser() );
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute_Ok() {
		$input = 'sampleFilter';
		$status = new ParserStatus( true, false, null, [] );
		$parser = $this->createMock( AbuseFilterParser::class );
		$parser->method( 'checkSyntax' )->with( $input )
			->willReturn( $status );
		$this->setService( ParserFactory::SERVICE_NAME, $this->getFactory( $parser ) );

		$result = $this->doApiRequest( [
			'action' => 'abusefilterchecksyntax',
			'filter' => $input,
		], null, null, self::getTestSysop()->getUser() );

		$this->assertArrayEquals(
			[ 'abusefilterchecksyntax' => [ 'status' => 'ok' ] ],
			$result[0],
			false,
			true
		);
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute_OkAndWarnings() {
		$input = 'sampleFilter';
		$warnings = [
			new UserVisibleWarning( 'exception-1', 3, [] ),
			new UserVisibleWarning( 'exception-2', 8, [ 'param' ] ),
		];
		$status = new ParserStatus( true, false, null, $warnings );
		$parser = $this->createMock( AbuseFilterParser::class );
		$parser->method( 'checkSyntax' )->with( $input )
			->willReturn( $status );
		$this->setService( ParserFactory::SERVICE_NAME, $this->getFactory( $parser ) );

		$result = $this->doApiRequest( [
			'action' => 'abusefilterchecksyntax',
			'filter' => $input,
		], null, null, self::getTestSysop()->getUser() );

		$this->assertArrayEquals(
			[
				'abusefilterchecksyntax' => [
					'status' => 'ok',
					'warnings' => [
						[
							'message' => wfMessage(
								'abusefilter-warning-exception-1',
								3
							)->text(),
							'character' => 3,
						],
						[
							'message' => wfMessage(
								'abusefilter-warning-exception-2',
								8,
								'param'
							)->text(),
							'character' => 8,
						],
					]
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
		$input = 'sampleFilter';
		$exception = new AFPUserVisibleException( 'error-id', 4, [] );
		$status = new ParserStatus( false, false, $exception, [] );
		$parser = $this->createMock( AbuseFilterParser::class );
		$parser->method( 'checkSyntax' )->with( $input )
			->willReturn( $status );
		$this->setService( ParserFactory::SERVICE_NAME, $this->getFactory( $parser ) );

		$result = $this->doApiRequest( [
			'action' => 'abusefilterchecksyntax',
			'filter' => $input,
		], null, null, self::getTestSysop()->getUser() );

		$this->assertArrayEquals(
			[
				'abusefilterchecksyntax' => [
					'status' => 'error',
					'message' => wfMessage(
						'abusefilter-exception-error-id',
						4
					)->text(),
					'character' => 4
				]
			],
			$result[0],
			false,
			true
		);
	}
}
