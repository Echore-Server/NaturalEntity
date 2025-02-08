<?php

declare(strict_types=1);

namespace Echore\NaturalEntity\utils;

use Echore\FastMath\FFIFastMath;
use Echore\NaturalEntity\Main;
use pocketmine\entity\Entity;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\utils\MathHelper;

class VectorUtil {

	public static function getAngleDirectionVector(Vector3 $from, Vector3 $to): Vector3 {
		if (Main::isFFIFastMathExists()) {
			return FFIFastMath::angleDirectionVector($from, $to);
		} else {
			$angle = self::getAngle($from, $to);

			return self::getDirectionVector($angle->x, $angle->y);
		}
	}

	public static function getAngle(Vector3|Entity $from, Vector3|Entity $to): Vector2 {
		$from = self::fixPos($from);
		$to = self::fixPos($to);
		if (Main::isFFIFastMathExists()) {
			return FFIFastMath::angle($from, $to);
		}
		$horizontal = sqrt(($to->x - $from->x) ** 2 + ($to->z - $from->z) ** 2);
		$vertical = $to->y - $from->y;
		$pitch = -atan2($vertical, $horizontal) * MathHelper::RAD_DEG; //negative is up, positive is down

		$xDist = $to->x - $from->x;
		$zDist = $to->z - $from->z;
		$yaw = atan2($zDist, $xDist) * MathHelper::RAD_DEG - 90;

		if ($yaw < 0) {
			$yaw += 360.0;
		}

		return new Vector2($yaw, $pitch);
	}

	public static function fixPos(Vector3|Entity $pos) {
		return ($pos instanceof Entity) ? $pos->getPosition()->asVector3() : $pos;
	}

	public static function getDirectionVector(float $yaw, float $pitch): Vector3 {
		if (Main::isFFIFastMathExists()) {
			return FFIFastMath::directionVector($yaw, $pitch);
		}
		$pitchRad = MathHelper::DEG_RAD * $pitch;
		$yawRad = MathHelper::DEG_RAD * $yaw;
		$y = -MathHelper::sin($pitchRad);
		$xz = MathHelper::cos($pitchRad);
		$x = -$xz * MathHelper::sin($yawRad);
		$z = $xz * MathHelper::cos($yawRad);

		return (new Vector3($x, $y, $z))->normalize();
	}

	public static function getAngleDirectionHorizontal(Vector3 $from, Vector3 $to): Vector3 {
		if (Main::isFFIFastMathExists()) {
			return FFIFastMath::angleDirectionHorizontal($from, $to);
		} else {
			$angle = self::getAngleHorizontal($from, $to);

			return self::getDirectionHorizontal($angle->x);
		}
	}

	public static function getAngleHorizontal(Vector3 $from, Vector3 $to): Vector2 {
		if (Main::isFFIFastMathExists()) {
			return FFIFastMath::angleHorizontal($from, $to);
		}

		$xDist = $to->x - $from->x;
		$zDist = $to->z - $from->z;
		$yaw = atan2($zDist, $xDist) / M_PI * 180 - 90;

		if ($yaw < 0) {
			$yaw += 360.0;
		}

		return new Vector2($yaw, 0);
	}

	public static function getDirectionHorizontal(float $yaw): Vector3 {
		// ffi overhead
		$x = -MathHelper::sin(MathHelper::DEG_RAD * $yaw);
		$z = MathHelper::cos(MathHelper::DEG_RAD * $yaw);

		$hor = new Vector3($x, 0, $z);

		return $hor->normalize();
	}

	public static function distanceToAABB(Vector3 $pos, AxisAlignedBB $aabb): float {
		$distX = max($aabb->minX - $pos->x, 0, $pos->x - $aabb->maxX);
		$distY = max($aabb->minY - $pos->y, 0, $pos->y - $aabb->maxY);
		$distZ = max($aabb->minZ - $pos->z, 0, $pos->z - $aabb->maxZ);

		return sqrt(pow($distX, 2) + pow($distY, 2) + pow($distZ, 2));
	}
}
