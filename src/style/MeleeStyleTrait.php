<?php

declare(strict_types=1);

namespace Echore\NaturalEntity\style;

use Echore\NaturalEntity\INaturalEntity;
use Echore\NaturalEntity\utils\VectorUtil;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;

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

		$resultDirection = $this->walkTo($this->getInstanceTarget()->getPosition()->add(0, 0.5, 0), true);

		if (!is_null($resultDirection)) {
			$horizontal = VectorUtil::getAngle($this->getPosition(), $this->getPosition()->addVector(new Vector3($resultDirection->x, 0, $resultDirection->y)));
			$this->setRotation($horizontal->x, 0);
		}

		$this->tryAttackEntity($this->getInstanceTarget());
	}

}
