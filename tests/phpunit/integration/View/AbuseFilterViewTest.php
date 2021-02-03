<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\View;

use IContextSource;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesFactory;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWiki\Extension\AbuseFilter\SpecsFormatter;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesBlobStore;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewRevert;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserFactory;
use MediaWikiIntegrationTestCase;
use PermissionsError;
use UserBlockedError;

class AbuseFilterViewTest extends MediaWikiIntegrationTestCase {

	public function testAbuseFilterViewRevert_noPermissions() {
		$this->expectException( PermissionsError::class );
		$context = $this->createMock( IContextSource::class );
		$context->method( 'getUser' )->willReturn( self::getTestUser()->getUser() );
		$view = new AbuseFilterViewRevert(
			$this->createMock( UserFactory::class ),
			AbuseFilterServices::getPermissionManager(),
			$this->createMock( FilterLookup::class ),
			$this->createMock( ConsequencesFactory::class ),
			$this->createMock( VariablesBlobStore::class ),
			$this->createMock( SpecsFormatter::class ),
			$context,
			$this->createMock( LinkRenderer::class ),
			'AbuseFilter',
			[]
		);
		$view->show();
	}

	public function testAbuseFilterViewRevert_blocked() {
		$this->expectException( UserBlockedError::class );
		$user = self::getTestUser( [ 'sysop' ] )->getUser();
		$block = new DatabaseBlock( [ 'expiry' => '1 day' ] );
		$block->setTarget( $user );
		$block->setBlocker( self::getTestSysop()->getUser() );
		MediaWikiServices::getInstance()->getDatabaseBlockStore()->insertBlock( $block );

		$context = $this->createMock( IContextSource::class );
		$context->method( 'getUser' )->willReturn( $user );

		$view = new AbuseFilterViewRevert(
			$this->createMock( UserFactory::class ),
			AbuseFilterServices::getPermissionManager(),
			$this->createMock( FilterLookup::class ),
			$this->createMock( ConsequencesFactory::class ),
			$this->createMock( VariablesBlobStore::class ),
			$this->createMock( SpecsFormatter::class ),
			$context,
			$this->createMock( LinkRenderer::class ),
			'AbuseFilter',
			[]
		);
		$view->show();
	}

}
