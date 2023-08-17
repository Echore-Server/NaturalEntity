<?php

declare(strict_types=1);

namespace Echore\NaturalEntity\style;

use Echore\NaturalEntity\INaturalEntity;
use Echore\NaturalEntity\utils\ProjectileHelper;
use Echore\NaturalEntity\utils\VectorUtil;
use pocketmine\entity\Attribute;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\Projectile;

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

			$projectile = $this->createProjectile(Location::fromObject($this->getEyePos(), $this->getWorld(), $this->getLocation()->yaw, $this->getLocation()->pitch));

			ProjectileHelper::setProjectileTarget($projectile, $this->getInstanceTarget()->getEyePos(), $this->getLaunchPower());
			$projectile->setBaseDamage($this->getAttributeMap()->get(Attribute::ATTACK_DAMAGE)->getValue());

			ProjectileHelper::launchProjectile(
				$projectile
			);
			$this->setPostAttackCoolDown($this->getAdditionalAttackCoolDown());
		} else {
			$this->walkForward();
		}
	}

	protected function createProjectile(Location $location): Projectile {
		return new Arrow($location, $this, true);
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
