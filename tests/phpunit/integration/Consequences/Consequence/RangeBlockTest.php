<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\Consequences\Consequence;

use MediaWiki\Block\BlockUser;
use MediaWiki\Block\BlockUserFactory;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Block\DatabaseBlockStore;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\RangeBlock;
use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;
use MediaWiki\Extension\AbuseFilter\Filter\ExistingFilter;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use MockMessageLocalizer;
use Status;

/**
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\Consequences\Consequence\RangeBlock
 */
class RangeBlockTest extends MediaWikiIntegrationTestCase {

	private const CIDR_LIMIT = [
		'IPv4' => 16,
		'IPv6' => 19,
	];

	private function getParameters( UserIdentity $user ) : Parameters {
		$filter = $this->createMock( ExistingFilter::class );
		$filter->method( 'getID' )->willReturn( 1 );
		$filter->method( 'getName' )->willReturn( 'Range blocking filter' );
		return new Parameters(
			$filter,
			false,
			$user,
			$this->createMock( LinkTarget::class ),
			'edit'
		);
	}

	public function provideExecute() : iterable {
		yield 'IPv4 range block' => [
			'1.2.3.4',
			[
				'IPv4' => 16,
				'IPv6' => 18,
			],
			'1.2.0.0/16',
			true
		];
		yield 'IPv6 range block' => [
			// random IP from https://en.wikipedia.org/w/index.php?title=IPv6&oldid=989727833
			'2001:0db8:0000:0000:0000:ff00:0042:8329',
			[
				'IPv4' => 15,
				'IPv6' => 19,
			],
			'2001:0:0:0:0:0:0:0/19',
			true
		];
		yield 'IPv4 range block constrained by core limits' => [
			'1.2.3.4',
			[
				'IPv4' => 15,
				'IPv6' => 19,
			],
			'1.2.0.0/16',
			true
		];
		yield 'IPv6 range block constrained by core limits' => [
			'2001:0db8:0000:0000:0000:ff00:0042:8329',
			[
				'IPv4' => 16,
				'IPv6' => 18,
			],
			'2001:0:0:0:0:0:0:0/19',
			true
		];
		yield 'failure' => [
			'1.2.3.4',
			self::CIDR_LIMIT,
			'1.2.0.0/16',
			false
		];
	}

	/**
	 * @dataProvider provideExecute
	 * @covers ::__construct
	 * @covers ::execute
	 */
	public function testExecute(
		string $requestIP, array $rangeBlockSize, string $target, bool $result
	) {
		$user = new UserIdentityValue( 1, 'Blocked user', 2 );
		$params = $this->getParameters( $user );
		$filterUser = AbuseFilterServices::getFilterUser();
		$blockUser = $this->createMock( BlockUser::class );
		$blockUser->expects( $this->once() )
			->method( 'placeBlockUnsafe' )
			->willReturn( $result ? Status::newGood() : Status::newFatal( 'error' ) );
		$blockUserFactory = $this->createMock( BlockUserFactory::class );
		$blockUserFactory->expects( $this->once() )
			->method( 'newBlockUser' )
			->with(
				$target,
				$this->anything(),
				'1 week',
				$this->anything(),
				$this->anything()
			)
			->willReturn( $blockUser );

		$rangeBlock = new RangeBlock(
			$params,
			'1 week',
			$blockUserFactory,
			$filterUser,
			new MockMessageLocalizer(),
			$this->createMock( DatabaseBlockStore::class ),
			$rangeBlockSize,
			self::CIDR_LIMIT,
			$requestIP
		);
		$this->assertSame( $result, $rangeBlock->execute() );
	}

	public function testRevert() {
		$filterUser = AbuseFilterServices::getFilterUser();
		$block = new DatabaseBlock( [ 'expiry' => '1 day' ] );
		$block->setTarget( '1.2.3.0/24' );
		$block->setBlocker( $filterUser->getUser() );
		MediaWikiServices::getInstance()->getDatabaseBlockStore()->insertBlock( $block );

		$store = $this->createMock( DatabaseBlockStore::class );
		$store->expects( $this->once() )
			->method( 'deleteBlock' )
			// FIXME ->with( $this->equalTo( $block ) )
			->willReturn( true );

		$rangeBlock = new RangeBlock(
			$this->createMock( Parameters::class ),
			'1 week',
			$this->createMock( BlockUserFactory::class ),
			$filterUser,
			new MockMessageLocalizer(),
			$store,
			[ 'IPv4' => 16, 'IPv6' => 18 ],
			self::CIDR_LIMIT,
			'127.0.0.1'
		);
		$this->assertTrue(
			$rangeBlock->revert(
				[ 'ip' => '1.2.3.4' ],
				self::getTestSysop()->getUser(),
				'reason'
			)
		);
		MediaWikiServices::getInstance()->getDatabaseBlockStore()->deleteBlock( $block );
	}

	public function testRevert_nothingToDo() {
		$store = $this->createMock( DatabaseBlockStore::class );
		$store->expects( $this->never() )->method( 'deleteBlock' );

		$rangeBlock = new RangeBlock(
			$this->createMock( Parameters::class ),
			'1 week',
			$this->createMock( BlockUserFactory::class ),
			AbuseFilterServices::getFilterUser(),
			new MockMessageLocalizer(),
			$store,
			[ 'IPv4' => 16, 'IPv6' => 18 ],
			self::CIDR_LIMIT,
			'127.0.0.1'
		);
		$this->assertFalse(
			$rangeBlock->revert(
				[ 'ip' => '1.2.3.4' ],
				self::getTestSysop()->getUser(),
				'reason'
			)
		);
	}

}
