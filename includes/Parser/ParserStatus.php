<?php

namespace MediaWiki\Extension\AbuseFilter\Parser;

class ParserStatus {
	/** @var bool */
	private $result;
	/** @var bool */
	private $warmCache;
	/** @var AFPException|null */
	private $excep;
	/** @var UserVisibleWarning[] */
	private $warnings;

	/**
	 * @param bool $result A generic operation result
	 * @param bool $warmCache Whether we retrieved the AST from cache
	 * @param AFPException|null $excep An exception thrown while parsing, or null if it parsed correctly
	 * @param UserVisibleWarning[] $warnings
	 */
	public function __construct( bool $result, bool $warmCache, ?AFPException $excep, array $warnings ) {
		$this->result = $result;
		$this->warmCache = $warmCache;
		$this->excep = $excep;
		$this->warnings = $warnings;
	}

	/**
	 * @return bool
	 */
	public function getResult() : bool {
		return $this->result;
	}

	/**
	 * @return bool
	 */
	public function getWarmCache() : bool {
		return $this->warmCache;
	}

	/**
	 * @return AFPException|null
	 */
	public function getException() : ?AFPException {
		return $this->excep;
	}

	/**
	 * @return UserVisibleWarning[]
	 */
	public function getWarnings() : array {
		return $this->warnings;
	}
}
