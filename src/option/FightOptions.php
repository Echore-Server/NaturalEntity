<?php

declare(strict_types=1);

namespace Echore\NaturalEntity\option;

class FightOptions {

	private bool $enabled;

	public function __construct() {
		$this->enabled = true;
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

}
