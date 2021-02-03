<?php

namespace MediaWiki\Extension\AbuseFilter\Consequences\Consequence;

use ManualLogEntry;
use MediaWiki\Block\BlockUserFactory;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Block\DatabaseBlockStore;
use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;
use MediaWiki\Extension\AbuseFilter\FilterUser;
use MediaWiki\Extension\AbuseFilter\GlobalNameUtils;
use MediaWiki\User\UserIdentity;
use MessageLocalizer;
use TitleValue;
use Wikimedia\IPUtils;

/**
 * Consequence that blocks an IP range (retrieved from the current request for both anons and registered users).
 */
class RangeBlock extends BlockingConsequence implements ReversibleConsequence {
	/** @var DatabaseBlockStore */
	private $databaseBlockStore;
	/** @var int[] */
	private $rangeBlockSize;
	/** @var int[] */
	private $blockCIDRLimit;
	/** @var string */
	private $requestIP;

	/**
	 * @param Parameters $parameters
	 * @param string $expiry
	 * @param BlockUserFactory $blockUserFactory
	 * @param FilterUser $filterUser
	 * @param MessageLocalizer $messageLocalizer
	 * @param DatabaseBlockStore $databaseBlockStore
	 * @param array $rangeBlockSize
	 * @param array $blockCIDRLimit
	 * @param string $requestIP
	 */
	public function __construct(
		Parameters $parameters,
		string $expiry,
		BlockUserFactory $blockUserFactory,
		FilterUser $filterUser,
		MessageLocalizer $messageLocalizer,
		DatabaseBlockStore $databaseBlockStore,
		array $rangeBlockSize,
		array $blockCIDRLimit,
		string $requestIP
	) {
		parent::__construct( $parameters, $expiry, $blockUserFactory, $filterUser, $messageLocalizer );
		$this->databaseBlockStore = $databaseBlockStore;
		$this->rangeBlockSize = $rangeBlockSize;
		$this->blockCIDRLimit = $blockCIDRLimit;
		$this->requestIP = $requestIP;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() : bool {
		$type = IPUtils::isIPv6( $this->requestIP ) ? 'IPv6' : 'IPv4';
		$CIDRsize = max( $this->rangeBlockSize[$type], $this->blockCIDRLimit[$type] );
		$blockCIDR = $this->requestIP . '/' . $CIDRsize;

		$target = IPUtils::sanitizeRange( $blockCIDR );
		$status = $this->doBlockInternal(
			$this->parameters->getFilter()->getName(),
			$this->parameters->getFilter()->getID(),
			$target,
			$this->expiry,
			$autoblock = false,
			$preventTalk = false
		);
		return $status->isOK();
	}

	/**
	 * @inheritDoc
	 */
	public function revert( $info, UserIdentity $performer, string $reason ): bool {
		$blocks = array_filter(
			DatabaseBlock::newListFromTarget( null, $info['ip'] ),
			function ( $block ) {
				return $block->getBy() === $this->filterUser->getUser()->getId()
					&& $block->getType() === DatabaseBlock::TYPE_RANGE;
			}
		);
		foreach ( $blocks as $block ) {
			$target = $block->getTarget();
			if ( !$this->databaseBlockStore->deleteBlock( $block ) ) {
				break;
			}
			$logEntry = new ManualLogEntry( 'block', 'unblock' );
			$logEntry->setTarget( new TitleValue(
				NS_USER,
				$target instanceof UserIdentity ? $target->getName() : $target
			) );
			$logEntry->setComment( $reason );
			$logEntry->setPerformer( $performer );
			$logEntry->publish( $logEntry->insert() );
			return true;
		}
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function getMessage(): array {
		$filter = $this->parameters->getFilter();
		return [
			'abusefilter-blocked-display',
			$filter->getName(),
			GlobalNameUtils::buildGlobalName( $filter->getID(), $this->parameters->getIsGlobalFilter() )
		];
	}
}
