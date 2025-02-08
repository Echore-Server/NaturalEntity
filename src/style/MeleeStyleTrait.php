<?php

declare(strict_types=1);

namespace Echore\NaturalEntity\style;

use Echore\NaturalEntity\INaturalEntity;
use Echore\NaturalEntity\utils\VectorUtil;
use pocketmine\entity\Living;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;

trait MeleeStyleTrait {

	private int $aimTick = 0;

	public function onFightUpdate(int $tickDiff = 1): void {
		/**
		 * @var INaturalEntity&IFightingEntity&Living $this
		 */

		if ($this->getInstanceTarget() === null) {
			return;
		}

		$this->aimTick += $tickDiff;

		if ($this->getPathProvider() !== null) {
			$resultDirection = $this->walkTo($this->getInstanceTarget()->getPosition()->add(0, 0.5, 0), true);
			$horizontal = null;
		} else {
			if (!$this->isImmobile() && $this->getMovementOptions()->isWalkEnabled() && ($this->getMovementOptions()->isWalkInAir() || $this->isOnGround())) {
				$position = $this->getPosition()->add(0, 0.5, 0);
				$horizontal = VectorUtil::getAngleHorizontal($position, $this->getInstanceTarget()->getPosition()->add(0, 0.5, 0));
				$resultDirection3 = VectorUtil::getDirectionHorizontal($horizontal->x);
				$this->walk($resultDirection = new Vector2($resultDirection3->x, $resultDirection3->z), true);
			} else {
				$horizontal = null;
				$resultDirection = null;
			}
		}

		if ($this->aimTick >= $this->getAimFlexibility()) {
			$this->aimTick -= $this->getAimFlexibility();

			if ($horizontal === null && $resultDirection !== null) {
				$horizontal = VectorUtil::getAngleHorizontal($this->getPosition(), $this->getPosition()->addVector(new Vector3($resultDirection->x, 0, $resultDirection->y)));
			}

			if ($horizontal !== null) {
				$this->setRotation($horizontal->x, 0);
			}
		}

		$this->tryAttackEntity($this->getInstanceTarget());
	}

	public function initStyle(): void {

	}
}
