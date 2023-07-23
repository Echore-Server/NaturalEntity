<?php

declare(strict_types=1);

namespace Echore\NaturalEntity\utils;

use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\world\sound\LaunchSound;

class ProjectileHelper {

	public static function shootArrow(Position $base, ?Entity $owningEntity, Vector3 $target, float $power, float $damage, bool $critical = true): void {
		$angle = VectorUtil::getAngle($base, $target);
		$dir = VectorUtil::getDirectionVector($angle->x, $angle->y);
		$motion = $dir->multiply($power);
		$projectile = new Arrow(new Location($base->x, $base->y, $base->z, $base->getWorld(), $angle->x, $angle->y), $owningEntity, $critical);
		$projectile->setBaseDamage($damage);
		$projectile->setMotion($motion);

		self::launchProjectile($projectile);
	}

	public static function launchProjectile(Projectile $projectile): void {
		$ev = new ProjectileLaunchEvent($projectile);
		$ev->call();
		if ($ev->isCancelled()) {
			$projectile->close();
		} else {
			$projectile->spawnToAll();
			$projectile->getWorld()->addSound($projectile->getPosition(), new LaunchSound());
		}
	}
}
