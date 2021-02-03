<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\Consequences\Consequence;

use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Degroup;
use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;
use MediaWiki\Extension\AbuseFilter\Filter\ExistingFilter;
use MediaWiki\Extension\AbuseFilter\FilterUser;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use MessageLocalizer;
use MockMessageLocalizer;

/**
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Degroup
 * @covers ::__construct
 */
class DegroupTest extends MediaWikiIntegrationTestCase {

	private function getParameters( UserIdentity $user ) : Parameters {
		$filter = $this->createMock( ExistingFilter::class );
		$filter->method( 'getID' )->willReturn( 1 );
		$filter->method( 'getName' )->willReturn( 'Degrouping filter' );
		return new Parameters(
			$filter,
			false,
			$user,
			$this->createMock( LinkTarget::class ),
			'edit'
		);
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute() {
		$user = new UserIdentityValue( 1, 'Degrouped user', 2 );
		$params = $this->getParameters( $user );
		$userGroupManager = $this->createMock( UserGroupManager::class );
		$userGroupManager->method( 'listAllImplicitGroups' )
			->willReturn( [ '*', 'user' ] );
		$userGroupManager->expects( $this->once() )
			->method( 'removeUserFromGroup' )
			->with( $user, 'sysop' );
		$filterUser = AbuseFilterServices::getFilterUser();

		$degroup = new Degroup(
			$params,
			VariableHolder::newFromArray(
				[ 'user_groups' => [ '*', 'user', 'sysop' ] ]
			),
			$userGroupManager,
			$filterUser,
			new MockMessageLocalizer()
		);
		$this->assertTrue( $degroup->execute() );
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute_noGroups() {
		$user = new UserIdentityValue( 1, 'Degrouped user', 2 );
		$params = $this->getParameters( $user );
		$userGroupManager = $this->createMock( UserGroupManager::class );
		$userGroupManager->method( 'listAllImplicitGroups' )
			->willReturn( [ '*', 'user' ] );
		$userGroupManager->expects( $this->never() )
			->method( 'removeUserFromGroup' );

		$degroup = new Degroup(
			$params,
			VariableHolder::newFromArray(
				[ 'user_groups' => [ '*', 'user' ] ]
			),
			$userGroupManager,
			$this->createMock( FilterUser::class ),
			$this->createMock( MessageLocalizer::class )
		);
		$this->assertFalse( $degroup->execute() );
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute_variableNotSet() {
		$user = new UserIdentityValue( 1, 'Degrouped user', 2 );
		$params = $this->getParameters( $user );
		$userGroupManager = $this->createMock( UserGroupManager::class );
		$userGroupManager->method( 'listAllImplicitGroups' )
			->willReturn( [ '*', 'user' ] );
		$userGroupManager->method( 'getUserEffectiveGroups' )
			->with( $user )
			->willReturn( [ '*', 'user', 'sysop' ] );
		$userGroupManager->expects( $this->once() )
			->method( 'removeUserFromGroup' )
			->with( $user, 'sysop' );
		$filterUser = AbuseFilterServices::getFilterUser();

		$degroup = new Degroup(
			$params,
			new VariableHolder(),
			$userGroupManager,
			$filterUser,
			new MockMessageLocalizer()
		);
		$this->assertTrue( $degroup->execute() );
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute_anonymous() {
		$user = new UserIdentityValue( 0, 'Anonymous user', 1 );
		$params = $this->getParameters( $user );
		$userGroupManager = $this->createMock( UserGroupManager::class );
		$userGroupManager->expects( $this->never() )->method( $this->anything() );
		$filterUser = $this->createMock( FilterUser::class );
		$filterUser->expects( $this->never() )->method( $this->anything() );

		$degroup = new Degroup(
			$params,
			$this->createMock( VariableHolder::class ),
			$userGroupManager,
			$filterUser,
			$this->createMock( MessageLocalizer::class )
		);
		$this->assertFalse( $degroup->execute() );
	}

	public function provideRevert() : array {
		return [
			[ true, [ '*', 'user', 'sysop' ] ],
			[ true, [ '*', 'user', 'canceled', 'sysop' ] ],
			[ false, [ '*', 'user', 'sysop' ], [ 'sysop' ] ],
			[ false, [ '*', 'user', 'canceled' ] ],
		];
	}

	/**
	 * @covers ::revert
	 * @dataProvider provideRevert
	 */
	public function testRevert( bool $success, array $hadGroups, array $hasGroups = [] ) {
		$user = new UserIdentityValue( 1, 'Degrouped user', 2 );
		$params = $this->getParameters( $user );
		$userGroupManager = $this->createMock( UserGroupManager::class );
		$userGroupManager->method( 'listAllImplicitGroups' )
			->willReturn( [ '*', 'user' ] );
		$userGroupManager->method( 'getUserGroups' )
			->with( $user )
			->willReturn( $hasGroups );
		$userGroupManager->method( 'addUserToGroup' )
			->willReturnCallback( function ( $_, $group ) use ( $hasGroups ) {
				return $group === 'sysop';
			} );
		$degroup = new Degroup(
			$params,
			new VariableHolder(),
			$userGroupManager,
			$this->createMock( FilterUser::class ),
			$this->createMock( MessageLocalizer::class )
		);

		$info = [
			'vars' => VariableHolder::newFromArray(
				[ 'user_groups' => $hadGroups ]
			)
		];
		$performer = $this->getTestUser()->getUser();
		$this->assertSame(
			$success,
			$degroup->revert( $info, $performer, 'reason' )
		);
	}

}
