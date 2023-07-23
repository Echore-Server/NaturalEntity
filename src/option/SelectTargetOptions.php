<?php

namespace Echore\NaturalEntity\option;

class SelectTargetOptions {

	private int $intervalTick;

	private bool $enabled;

	private int $initialInteresting;

	private bool $selectInvisible;

	/**
	 */
	public function __construct() {
		$this->intervalTick = 60;
		$this->enabled = true;
		$this->initialInteresting = 400;
		$this->selectInvisible = false;
	}

	/**
	 * @return bool
	 */
	public function isSelectInvisible(): bool {
		return $this->selectInvisible;
	}

	/**
	 * @param bool $selectInvisible
	 */
	public function setSelectInvisible(bool $selectInvisible): void {
		$this->selectInvisible = $selectInvisible;
	}

	/**
	 * @return int
	 */
	public function getIntervalTick(): int {
		return $this->intervalTick;
	}

	/**
	 * @param int $intervalTick
	 */
	public function setIntervalTick(int $intervalTick): void {
		$this->intervalTick = $intervalTick;
	}

	/**
	 * @return bool
	 */
	public function isEnabled(): bool {
		return $this->enabled;
	}

	/**
	 * @param bool $enabled
	 */
	public function setEnabled(bool $enabled): void {
		$this->enabled = $enabled;
	}

	/**
	 * @return int
	 */
	public function getInitialInteresting(): int {
		return $this->initialInteresting;
	}

	/**
	 * @param int $initialInteresting
	 */
	public function setInitialInteresting(int $initialInteresting): void {
		$this->initialInteresting = $initialInteresting;
	}


}
