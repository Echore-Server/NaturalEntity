<?php

declare(strict_types=1);

namespace Echore\NaturalEntity\utils;

use pocketmine\entity\Entity;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\world\World;

class VectorUtil {

	public static function getDirectionHorizontal($yaw): Vector3 {
		$x = -sin(deg2rad($yaw));
		$z = cos(deg2rad($yaw));

		$hor = new Vector3($x, 0, $z);

		return $hor->normalize();
	}

	public static function getDirectionVector(float $yaw, float $pitch): Vector3 {
		$y = -sin(deg2rad($pitch));
		$xz = cos(deg2rad($pitch));
		$x = -$xz * sin(deg2rad($yaw));
		$z = $xz * cos(deg2rad($yaw));

		return (new Vector3($x, $y, $z))->normalize();
	}

	public static function getAngle(Vector3|Entity $from, Vector3|Entity $to): Vector2 {
		$from = self::fixPos($from);
		$to = self::fixPos($to);
		$horizontal = sqrt(($to->x - $from->x) ** 2 + ($to->z - $from->z) ** 2);
		$vertical = $to->y - $from->y;
		$pitch = -atan2($vertical, $horizontal) / M_PI * 180; //negative is up, positive is down

		$xDist = $to->x - $from->x;
		$zDist = $to->z - $from->z;
		$yaw = atan2($zDist, $xDist) / M_PI * 180 - 90;

		if ($yaw < 0) {
			$yaw += 360.0;
		}

		return new Vector2($yaw, $pitch);
	}

	public static function fixPos(Vector3|Entity $pos) {
		return ($pos instanceof Entity) ? $pos->getPosition()->asVector3() : $pos;
	}

	public static function distanceToAABB(Vector3 $pos, AxisAlignedBB $aabb): float {
		$distX = max($aabb->minX - $pos->x, 0, $pos->x - $aabb->maxX);
		$distY = max($aabb->minY - $pos->y, 0, $pos->y - $aabb->maxY);
		$distZ = max($aabb->minZ - $pos->z, 0, $pos->z - $aabb->maxZ);

		return sqrt(pow($distX, 2) + pow($distY, 2) + pow($distZ, 2));
	}

	public function getBlocks(World $world, AxisAlignedBB $bb, bool $targetFirst = false): array {
		$minX = (int) floor($bb->minX - 1);
		$minY = (int) floor($bb->minY - 1);
		$minZ = (int) floor($bb->minZ - 1);
		$maxX = (int) floor($bb->maxX + 1);
		$maxY = (int) floor($bb->maxY + 1);
		$maxZ = (int) floor($bb->maxZ + 1);

		$collides = [];

		if ($targetFirst) {
			for ($z = $minZ; $z <= $maxZ; ++$z) {
				for ($x = $minX; $x <= $maxX; ++$x) {
					for ($y = $minY; $y <= $maxY; ++$y) {
						$block = $world->getBlockAt($x, $y, $z);

						return [$block];
					}
				}
			}
		} else {
			for ($z = $minZ; $z <= $maxZ; ++$z) {
				for ($x = $minX; $x <= $maxX; ++$x) {
					for ($y = $minY; $y <= $maxY; ++$y) {
						$block = $world->getBlockAt($x, $y, $z);
						if ($block->collidesWithBB($bb)) {
							$collides[] = $block;
						}
					}
				}
			}
		}

		return $collides;
	}
}
