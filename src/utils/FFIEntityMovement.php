<?php

declare(strict_types=1);

namespace Echore\NaturalEntity\utils;

use FFI;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;

class FFIEntityMovement {

	private static FFI $ffi;

	public static function init(string $code, string $library): void {
		self::$ffi = FFI::cdef($code, $library);
	}

	public static function parseBoundingBox(FFI\CData $struct): AxisAlignedBB {
		return new AxisAlignedBB(
			$struct->min_x,
			$struct->min_y,
			$struct->min_z,
			$struct->max_x,
			$struct->max_y,
			$struct->max_z
		);
	}

	public static function parseVec3d(FFI\CData $struct): Vector3 {
		return new Vector3(
			$struct->x,
			$struct->y,
			$struct->z
		);
	}

	public static function move(Vector3 $move, AxisAlignedBB $box, array $collidingBoxes, float $stepHeight, bool $onGround, float $ySize): mixed {
		$moveObj = self::createVec3d($move);
		$boxObj = self::createBoundingBox($box);
		$boxCount = count($collidingBoxes);
		if ($boxCount > 0) {
			$boxes = self::$ffi->new("struct BoundingBox[$boxCount]");
			for ($i = 0; $i < $boxCount; $i++) {
				$boxes[$i] = self::createBoundingBox($collidingBoxes[$i]);
			}
		} else {
			$boxes = self::$ffi->new("struct BoundingBox[1]");
		}

		$state = self::createEntityState($onGround, $ySize);

		return self::$ffi->move($moveObj, $boxObj, $boxes, $boxCount, $stepHeight, $state);
	}

	public static function createVec3d(Vector3 $from): FFI\CData {
		$obj = self::$ffi->new("struct Vec3d");
		$obj->x = $from->x;
		$obj->y = $from->y;
		$obj->z = $from->z;

		return $obj;
	}

	public static function createBoundingBox(AxisAlignedBB $box): FFI\CData {
		$obj = self::$ffi->new("struct BoundingBox");
		$obj->min_x = $box->minX;
		$obj->min_y = $box->minY;
		$obj->min_z = $box->minZ;
		$obj->max_x = $box->maxX;
		$obj->max_y = $box->maxY;
		$obj->max_z = $box->maxZ;

		return $obj;
	}

	public static function createEntityState(bool $onGround, float $ySize): FFI\CData {
		$obj = self::$ffi->new("struct EntityState");
		$obj->on_ground = $onGround;
		$obj->y_size = $ySize;

		return $obj;
	}

}
