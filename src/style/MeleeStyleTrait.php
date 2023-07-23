<?php

namespace Echore\NaturalEntity\style;

use Echore\NaturalEntity\INaturalEntity;
use pocketmine\entity\Living;

trait MeleeStyleTrait {

	private int $aimTick = 0;

	public function onFightUpdate(int $tickDiff = 1): void {
		/**
		 * @var INaturalEntity&IFightingEntity&Living $this
		 */

		if (is_null($this->getInstanceTarget())) {
			return;
		}

		$this->aimTick += $tickDiff;

		if ($this->aimTick >= $this->getAimFlexibility()) {
			$this->aimTick -= $this->getAimFlexibility();

			$this->lookAt($this->getInstanceTarget()->getPosition());
			$this->setRotation($this->getLocation()->getYaw(), 0);
		}

		$this->walkForward();

		$this->tryAttackEntity($this->getInstanceTarget());
	}

}
