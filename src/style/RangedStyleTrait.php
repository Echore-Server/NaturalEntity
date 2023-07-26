<?php

declare(strict_types=1);

namespace Echore\NaturalEntity\style;

use Echore\NaturalEntity\INaturalEntity;
use Echore\NaturalEntity\utils\ProjectileHelper;
use Echore\NaturalEntity\utils\VectorUtil;
use pocketmine\entity\Attribute;
use pocketmine\entity\Living;
use pocketmine\world\Position;

trait RangedStyleTrait {
	private int $aimTick = 0;

	public function onFightUpdate(int $tickDiff = 1): void {
		/**
		 * @var INaturalEntity&IFightingEntity&Living $this
		 */

		if (is_null($this->getInstanceTarget())) {
			return;
		}

		if ($this->aimTick += $tickDiff > $this->getAimFlexibility()) {
			$this->aimTick = 0;

			$this->lookAt($this->getInstanceTarget()->getPosition());
		}

		$dist = VectorUtil::distanceToAABB($this->getEyePos(), $this->getInstanceTarget()->getBoundingBox());
		if ($dist <= $this->getAttackRange()) {
			if ($this->getPosition()->distance($this->getInstanceTarget()->getPosition()) <= 5.0) {
				$this->walkBackward();
			}

			ProjectileHelper::shootArrow(
				Position::fromObject($this->getEyePos(), $this->getWorld()),
				$this,
				$this->getInstanceTarget()->getEyePos(),
				2,
				$this->getAttributeMap()->get(Attribute::ATTACK_DAMAGE)->getValue()
			);
			$this->setPostAttackCoolDown($this->getAdditionalAttackCoolDown());
		} else {
			$this->walkForward();
		}
	}
}
