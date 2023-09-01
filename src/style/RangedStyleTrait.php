<?php

declare(strict_types=1);

namespace Echore\NaturalEntity\style;

use Echore\NaturalEntity\INaturalEntity;
use Echore\NaturalEntity\utils\ProjectileHelper;
use Echore\NaturalEntity\utils\VectorUtil;
use pocketmine\entity\Attribute;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\Projectile;
use pocketmine\math\Vector3;

trait RangedStyleTrait {
	protected float $launchPower = 2;

	private int $aimTick = 0;

	public function initStyle(): void {

	}

	public function onFightUpdate(int $tickDiff = 1): void {
		/**
		 * @var INaturalEntity&IFightingEntity&Living $this
		 */

		if (is_null($this->getInstanceTarget())) {
			return;
		}
		$this->aimTick += $tickDiff;

		if ($this->aimTick > $this->getAimFlexibility()) {
			$this->aimTick = 0;

			$this->lookAt($this->getInstanceTarget()->getPosition());
		}

		if ($this->getPostAttackCoolDown() > 0) {
			return;
		}

		$dist = VectorUtil::distanceToAABB($this->getEyePos(), $this->getInstanceTarget()->getBoundingBox());
		if ($dist <= $this->getAttackRange()) {
			if ($this->getPosition()->distance($this->getInstanceTarget()->getPosition()) <= 5.0) {
				$this->walkBackward();
			}

			$this->fire($this->getInstanceTarget());
			$this->setPostAttackCoolDown($this->getAdditionalAttackCoolDown());
		} else {
			$this->walkForward();
		}
	}

	protected function fire(Entity $target): void {
		$entityThrows = $this->createEntityThrows(Location::fromObject($this->getEyePos(), $this->getWorld(), $this->getLocation()->yaw, $this->getLocation()->pitch));

		ProjectileHelper::setThrowingTarget($entityThrows, $this->getTargetPosition($target), $this->getLaunchPower());

		if ($entityThrows instanceof Projectile) {
			$entityThrows->setBaseDamage($this->getAttributeMap()->get(Attribute::ATTACK_DAMAGE)->getValue());
		}

		ProjectileHelper::launch(
			$entityThrows
		);
	}

	protected function createEntityThrows(Location $location): Entity {
		return new Arrow($location, $this, true);
	}

	protected function getTargetPosition(Entity $entity): Vector3 {
		return $entity->getEyePos();
	}

	/**
	 * @return float
	 */
	public function getLaunchPower(): float {
		return $this->launchPower;
	}

	/**
	 * @param float $launchPower
	 */
	public function setLaunchPower(float $launchPower): void {
		$this->launchPower = $launchPower;
	}
}
