<?php

declare(strict_types=1);

namespace Echore\NaturalEntity\utils;

use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Arrow;
use pocketmine\math\Vector3;
use pocketmine\world\Position;

class ProjectileHelper {

	public static function shootArrow(Position $base, ?Entity $owningEntity, Vector3 $target, float $power, float $damage, bool $critical = true): void {
		$angle = VectorUtil::getAngle($base, $target);
		$dir = VectorUtil::getDirectionVector($angle->x, $angle->y);
		$motion = $dir->multiply($power);
		$projectile = new Arrow(new Location($base->x, $base->y, $base->z, $base->getWorld(), $angle->x, $angle->y), $owningEntity, $critical);
		$projectile->setBaseDamage($damage);
		$projectile->setMotion($motion);

		self::launch($projectile);
	}

	public static function launch(Entity $entity): void {
		$entity->spawnToAll();
	}

	public static function setThrowingTarget(Entity $throwing, Vector3 $target, float $power): void {
		$base = $throwing->getPosition();
		$angle = VectorUtil::getAngle($base, $target);
		$dir = VectorUtil::getDirectionVector($angle->x, $angle->y);
		$motion = $dir->multiply($power);
		$throwing->setMotion($motion);
	}
}
