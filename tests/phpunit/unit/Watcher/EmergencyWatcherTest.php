<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit\Watcher;

use InvalidArgumentException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\AbuseFilter\EchoNotifier;
use MediaWiki\Extension\AbuseFilter\Filter\ExistingFilter;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWiki\Extension\AbuseFilter\FilterProfiler;
use MediaWiki\Extension\AbuseFilter\Watcher\EmergencyWatcher;
use MediaWikiUnitTestCase;
use MWTimestamp;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\Watcher\EmergencyWatcher
 * @group Unit
 */
class EmergencyWatcherTest extends MediaWikiUnitTestCase {

	private const SAVED_TIMESTAMP = '20210101000000';

	public function provideFiltersToThrottle() {
		$sep = DIRECTORY_SEPARATOR;
		$handle = fopen( __DIR__ . "${sep}..${sep}..${sep}..${sep}watcher_combinations.csv", 'r' );
		if ( $handle === false ) {
			throw new InvalidArgumentException( 'watcher_combinations.csv file not found!' );
		}

		// init line
		fgets( $handle );
		while ( $line = fgets( $handle ) ) {
			yield [ rtrim( $line ) ];
		}
		fclose( $handle );
	}

	private static function toBool( string $val ) : bool {
		return $val === 'true';
	}

	private function getFilterProfiler( int $total, int $matches ) : FilterProfiler {
		$profiler = $this->createMock( FilterProfiler::class );
		$profiler->expects( $this->once() )
			->method( 'getGroupProfile' )
			->willReturn( [ 'total' => $total ] );
		$profiler->method( 'getFilterProfile' )
			->willReturn( [ 'matches' => $matches ] );
		return $profiler;
	}

	private function getFilterLookup( bool $throttled ) : FilterLookup {
		$lookup = $this->createMock( FilterLookup::class );
		$lookup->method( 'getFilter' )->with( 1, false )
			->willReturnCallback( function () use ( $throttled ) {
				$filterObj = $this->createMock( ExistingFilter::class );
				$filterObj->method( 'getTimestamp' )->willReturn( self::SAVED_TIMESTAMP );
				$filterObj->method( 'isThrottled' )->willReturn( $throttled );
				return $filterObj;
			} );
		return $lookup;
	}

	private function parseLine( string $line ) : array {
		[
			$seconds, $throttled, $hits, $total, $percentage, $disableAge, $disableCount, $expected
		] = explode( ',', $line );
		$watcher = new EmergencyWatcher(
			$this->getFilterProfiler( (int)$total, (int)$hits ),
			$this->createMock( ILoadBalancer::class ),
			$this->getFilterLookup( self::toBool( $throttled ) ),
			$this->createMock( EchoNotifier::class ),
			new ServiceOptions(
				EmergencyWatcher::CONSTRUCTOR_OPTIONS,
				[
					'AbuseFilterEmergencyDisableAge' => [ 'default' => (int)$disableAge ],
					'AbuseFilterEmergencyDisableCount' => [ 'default' => (int)$disableCount ],
					'AbuseFilterEmergencyDisableThreshold' => [ 'default' => (float)$percentage ],
				]
			)
		);
		return [ $watcher, (int)$seconds, self::toBool( $expected ) ];
	}

	/**
	 * @covers ::getFiltersToThrottle
	 * @dataProvider provideFiltersToThrottle
	 */
	public function testGetFiltersToThrottle( string $line ) {
		[ $watcher, $seconds, $expected ] = $this->parseLine( $line );
		$ts = wfTimestamp( TS_UNIX, self::SAVED_TIMESTAMP ) + $seconds;
		MWTimestamp::setFakeTime( $ts );
		$toThrottle = $watcher->getFiltersToThrottle( [ 1 ], 'default' );
		$this->assertSame(
			$expected ? [ 1 ] : [],
			$toThrottle
		);
	}

	/**
	 * @covers ::__construct
	 */
	public function testConstruct() {
		$watcher = new EmergencyWatcher(
			$this->createMock( FilterProfiler::class ),
			$this->createMock( ILoadBalancer::class ),
			$this->createMock( FilterLookup::class ),
			$this->createMock( EchoNotifier::class ),
			new ServiceOptions(
				EmergencyWatcher::CONSTRUCTOR_OPTIONS,
				[
					'AbuseFilterEmergencyDisableAge' => [
						'default' => 86400,
					],
					'AbuseFilterEmergencyDisableCount' => [
						'default' => 2,
					],
					'AbuseFilterEmergencyDisableThreshold' => [
						'default' => 0.01,
					],
				]
			)
		);
		$this->assertInstanceOf( EmergencyWatcher::class, $watcher );
	}

}
