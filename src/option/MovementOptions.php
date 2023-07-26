<?php

declare(strict_types=1);

namespace Echore\NaturalEntity\option;

class MovementOptions {

	private bool $ignoreBlockModifiers;

	private bool $walkEnabled;

	private bool $walkInAir;

	public function __construct() {
		$this->ignoreBlockModifiers = false;
		$this->walkEnabled = true;
		$this->walkInAir = false;
	}

	/**
	 * @return bool
	 */
	public function isWalkInAir(): bool {
		return $this->walkInAir;
	}

	/**
	 * @param bool $walkInAir
	 */
	public function setWalkInAir(bool $walkInAir): void {
		$this->walkInAir = $walkInAir;
	}

	/**
	 * @return bool
	 */
	public function isWalkEnabled(): bool {
		return $this->walkEnabled;
	}

	/**
	 * @param bool $walkEnabled
	 */
	public function setWalkEnabled(bool $walkEnabled): void {
		$this->walkEnabled = $walkEnabled;
	}

	/**
	 * @return bool
	 */
	public function isIgnoreBlockModifiers(): bool {
		return $this->ignoreBlockModifiers;
	}

	/**
	 * @param bool $ignoreBlockModifiers
	 */
	public function setIgnoreBlockModifiers(bool $ignoreBlockModifiers): void {
		$this->ignoreBlockModifiers = $ignoreBlockModifiers;
	}
}
