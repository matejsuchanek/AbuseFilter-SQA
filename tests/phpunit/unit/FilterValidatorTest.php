<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit;

use InvalidArgumentException;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\ChangeTags\ChangeTagValidator;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Extension\AbuseFilter\FilterValidator;
use MediaWiki\Extension\AbuseFilter\Parser\AbuseFilterParser;
use MediaWiki\Extension\AbuseFilter\Parser\AFPException;
use MediaWiki\Extension\AbuseFilter\Parser\ParserFactory;
use MediaWiki\Extension\AbuseFilter\Parser\ParserStatus;
use MediaWikiUnitTestCase;
use Status;
use User;

/**
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\FilterValidator
 * @group Unit
 */
class FilterValidatorTest extends MediaWikiUnitTestCase {

	private static function toBool( string $val ) : bool {
		return $val === 'true';
	}

	private static function getRules( string $val ) : string {
		switch ( $val ) {
			case 'empty':
				return '';
			case 'space':
				return ' ';
			default:
				return $val;
		}
	}

	private static function getActions( string $tags, string $message, bool $isRestr ) : array {
		$actions = [];

		switch ( $tags ) {
			case 'empty':
				$actions['tag'] = [];
				break;
			case 'invalid':
			case 'valid':
				$actions['tag'] = [ $tags ];
				break;
			case 'both':
				$actions['tag'] = [ 'valid', 'invalid' ];
				break;
		}

		switch ( $message ) {
			case 'empty':
				$actions['warn'] = [ '' ];
				break;
			case 'default':
				$actions['warn'] = [ 'abusefilter-warning' ];
				break;
			case 'custom':
				$actions['warn'] = [ 'abusefilter-warning-custom' ];
				break;
		}

		if ( $isRestr ) {
			$actions['restricted'] = [];
		}

		return $actions;
	}

	public function provideCheckAll() {
		$sep = DIRECTORY_SEPARATOR;
		$handle = fopen( __DIR__ . "${sep}..${sep}..${sep}validator_combinations.csv", 'r' );
		if ( $handle === false ) {
			throw new InvalidArgumentException( 'validator_combinations.csv file not found!' );
		}

		// init line
		fgets( $handle );
		while ( $line = fgets( $handle ) ) {
			yield [ rtrim( $line ) ];
		}
		fclose( $handle );
	}

	private function readLine( string $line ) : array {
		$parts = explode( ',', $line );
		[ $rules, $name, $tags, $enabled, $deleted, $global, $message, $canRestr, $isRestr,
			$result ] = $parts;
		$filterObj = MutableFilter::newDefault();
		$filterObj->setRules( self::getRules( $rules ) );
		$filterObj->setName( self::getRules( $name ) );
		$filterObj->setEnabled( self::toBool( $enabled ) );
		$filterObj->setDeleted( self::toBool( $deleted ) );
		$filterObj->setGlobal( self::toBool( $global ) );
		$filterObj->setActions( self::getActions( $tags, $message, self::toBool( $isRestr ) ) );
		return [ $filterObj, self::toBool( $canRestr ), self::toBool( $result ) ];
	}

	/**
	 * @dataProvider provideCheckAll
	 */
	public function testCheckAll( string $line ) {
		[ $filterObj, $canRestricted, $expected ] = $this->readLine( $line );
		$validator = new FilterValidator(
			$this->getTagValidator(),
			$this->getParserFactory(),
			$this->getPermissionManager( $canRestricted ),
			[ 'restricted' ]
		);
		$status = $validator->checkAll(
			$filterObj,
			MutableFilter::newDefault(),
			$this->createMock( User::class )
		);
		$this->assertSame( $expected, $status->isGood() );
	}

	private function getTagValidator() : ChangeTagValidator {
		$tagValidator = $this->createMock( ChangeTagValidator::class );
		$tagValidator->method( 'validateTag' )
			->willReturnCallback( function ( $tag ) {
				return $tag !== 'invalid' ? Status::newGood() : Status::newFatal( 'invalid-tag' );
			} );
		return $tagValidator;
	}

	private function getParserFactory() : ParserFactory {
		$parser = $this->createMock( AbuseFilterParser::class );
		$parser->method( 'checkSyntax' )->willReturnCallback( function ( $expr ) {
				if ( $expr === "valid" ) {
					return new ParserStatus( true, false, null, [] );
				} else {
					return new ParserStatus( false, false, new AFPException( 'message' ), [] );
				}
			} );
		$factory = $this->createMock( ParserFactory::class );
		$factory->method( 'newParser' )->willReturn( $parser );

		return $factory;
	}

	private function getPermissionManager( bool $canRestricted ) : AbuseFilterPermissionManager {
		$permManager = $this->createMock( AbuseFilterPermissionManager::class );
		$permManager->method( 'canEditFilterWithRestrictedActions' )->willReturn( $canRestricted );
		$permManager->method( 'canEditFilter' )->willReturn( true );
		return $permManager;
	}

}
