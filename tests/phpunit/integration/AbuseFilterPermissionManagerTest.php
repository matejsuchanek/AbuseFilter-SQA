<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWikiIntegrationTestCase;

/**
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager
 */
class AbuseFilterPermissionManagerTest extends MediaWikiIntegrationTestCase {

	public function getTestUsers() : array {
		return [
			self::getTestUser(),
			self::getTestUser( [ 'sysop' ] )
		];
	}

	/**
	 * @covers ::canViewPrivateFiltersLogs
	 */
	public function testCanViewPrivateFiltersLogs() {
		[ $user, $sysop ] = $this->getTestUsers();
		$service = AbuseFilterServices::getPermissionManager();
		$this->assertFalse( $service->canViewPrivateFiltersLogs( $user->getUser() ) );
		$this->assertTrue( $service->canViewPrivateFiltersLogs( $sysop->getUser() ) );
	}

	/**
	 * @covers ::canViewPrivateFilters
	 */
	public function testCanViewPrivateFilters() {
		[ $user, $sysop ] = $this->getTestUsers();
		$service = AbuseFilterServices::getPermissionManager();
		$this->assertFalse( $service->canViewPrivateFilters( $user->getUser() ) );
		$this->assertTrue( $service->canViewPrivateFilters( $sysop->getUser() ) );
	}

	/**
	 * @covers ::canRevertFilterActions
	 */
	public function testCanRevertFilterActions() {
		[ $user, $sysop ] = $this->getTestUsers();
		$service = AbuseFilterServices::getPermissionManager();
		$this->assertFalse( $service->canRevertFilterActions( $user->getUser() ) );
		$this->assertTrue( $service->canRevertFilterActions( $sysop->getUser() ) );
	}

}
