<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\EditBoxBuilder;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner;
use MediaWiki\Extension\AbuseFilter\KeywordsManager;
use MediaWikiIntegrationTestCase;
use MockMessageLocalizer;
use OutputPage;
use User;

/**
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\EditBoxBuilder
 */
class EditBoxBuilderTest extends MediaWikiIntegrationTestCase {

	private function getPermissionManager( $method, $ret ) : AbuseFilterPermissionManager {
		$permManager = $this->createMock( AbuseFilterPermissionManager::class );
		$permManager->method( $method )->willReturn( $ret );
		return $permManager;
	}

	private function getKeywordsManager() : KeywordsManager {
		return new KeywordsManager(
			$this->createMock( AbuseFilterHookRunner::class )
		);
	}

	public function provideTrueFalse() {
		return [
			[ true ],
			[ false ]
		];
	}

	/**
	 * @dataProvider provideTrueFalse
	 * @covers ::buildEditBox
	 */
	public function testBuildEditBox_bareForm( bool $canEdit ) {
		$outputPage = $this->getMockBuilder( OutputPage::class )
			->onlyMethods( [] )
			->disableOriginalConstructor()
			->getMock();
		$builder = new EditBoxBuilder(
			$this->getPermissionManager( 'canEdit', $canEdit ),
			$this->createMock( KeywordsManager::class ),
			false,
			new MockMessageLocalizer(),
			$this->createMock( User::class ),
			$outputPage
		);
		$result = $builder->buildEditBox( '###RULES###', false, false, true );
		$this->assertRegExp( "~<textarea[^>]*>\s*###RULES###\s*</textarea>~", $result );
	}

	public function provideTrueFalse_combined() {
		return [
			[ true, true ],
			[ true, false ],
			[ false, true ],
			[ false, false ]
		];
	}

	/**
	 * @dataProvider provideTrueFalse_combined
	 * @covers ::buildEditBox
	 */
	public function testBuildEditBox_CodeEditor( bool $loaded, bool $canEdit ) {
		$outputPage = $this->getMockBuilder( OutputPage::class )
			->onlyMethods( [ 'addJsConfigVars' ] )
			->disableOriginalConstructor()
			->getMock();
		$outputPage->expects( $this->exactly( $loaded ? 1 : 0 ) )
			->method( 'addJsConfigVars' )
			->with( 'aceConfig', $this->anything() )
			->willReturnCallback( function ( $name, $value ) use ( $canEdit ) {
				$this->assertSame( $canEdit === false, $value['aceReadOnly'] );
			} );
		$builder = new EditBoxBuilder(
			$this->getPermissionManager( 'canEdit', $canEdit ),
			$this->getKeywordsManager(),
			$loaded,
			new MockMessageLocalizer(),
			$this->createMock( User::class ),
			$outputPage
		);
		$result = $builder->buildEditBox( '###RULES###', false, false, true );
		$this->assertRegExp( "~<textarea[^>]*>\s*###RULES###\s*</textarea>~", $result );
		if ( $loaded ) {
			$this->assertStringContainsString( 'wpAceFilterEditor', $result );
		} else {
			$this->assertStringNotContainsString( 'wpAceFilterEditor', $result );
		}
	}

	// TODO
	// $resultDiv added when requested and allowed
	// $resultDiv not added when not allowed
}
