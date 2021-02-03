<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\Api;

use ApiTestCase;
use MediaWiki\Extension\AbuseFilter\Parser\AbuseFilterParser;
use MediaWiki\Extension\AbuseFilter\Parser\ParserFactory;
use MediaWiki\Extension\AbuseFilter\Parser\ParserStatus;

/**
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\Api\EvalExpression
 * @group medium
 */
class EvalExpressionTest extends ApiTestCase {

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
		$this->setExpectedApiException( 'apierror-abusefilter-canteval', 'permissiondenied' );

		$this->setService( ParserFactory::SERVICE_NAME, $this->getFactory() );

		$this->doApiRequest( [
			'action' => 'abusefilterevalexpression',
			'expression' => 'sampleExpression',
		], null, null, self::getTestUser()->getUser() );
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute_error() {
		$this->setExpectedApiException( 'abusefilter-tools-syntax-error' );
		$expression = 'sampleExpression';
		$status = new ParserStatus( false, false, null, [] );
		$parser = $this->createMock( AbuseFilterParser::class );
		$parser->method( 'checkSyntax' )->with( $expression )
			->willReturn( $status );
		$this->setService( ParserFactory::SERVICE_NAME, $this->getFactory( $parser ) );

		$this->doApiRequest( [
			'action' => 'abusefilterevalexpression',
			'expression' => $expression,
		], null, null, self::getTestSysop()->getUser() );
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute_Ok() {
		$expression = 'sampleExpression';
		$status = new ParserStatus( true, false, null, [] );
		$parser = $this->createMock( AbuseFilterParser::class );
		$parser->method( 'checkSyntax' )->with( $expression )
			->willReturn( $status );
		$parser->expects( $this->once() )->method( 'evaluateExpression' )
			->willReturn( 'output' );
		$this->setService( ParserFactory::SERVICE_NAME, $this->getFactory( $parser ) );

		$result = $this->doApiRequest( [
			'action' => 'abusefilterevalexpression',
			'expression' => $expression,
			'prettyprint' => false,
		], null, null, self::getTestSysop()->getUser() );

		$this->assertArrayEquals(
			[
				'abusefilterevalexpression' => [
					'result' => "'output'"
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
	public function testExecute_OkAndPrettyPrint() {
		$expression = 'sampleExpression';
		$status = new ParserStatus( true, false, null, [] );
		$parser = $this->createMock( AbuseFilterParser::class );
		$parser->method( 'checkSyntax' )->with( $expression )
			->willReturn( $status );
		$parser->expects( $this->once() )->method( 'evaluateExpression' )
			->willReturn( [ 'value1', 2 ] );
		$this->setService( ParserFactory::SERVICE_NAME, $this->getFactory( $parser ) );

		$result = $this->doApiRequest( [
			'action' => 'abusefilterevalexpression',
			'expression' => $expression,
			'prettyprint' => true,
		], null, null, self::getTestSysop()->getUser() );

		$this->assertArrayEquals(
			[
				'abusefilterevalexpression' => [
					'result' => "[\n\t0 => 'value1',\n\t1 => 2\n]"
				]
			],
			$result[0],
			false,
			true
		);
	}
}
