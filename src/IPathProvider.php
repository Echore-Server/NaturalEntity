<?php

declare(strict_types=1);

namespace Echore\NaturalEntity;

use pocketmine\math\Vector3;

interface IPathProvider {

	public function getNextPosition(Vector3 $from, Vector3 $to): Vector3;

	public function isReachable(Vector3 $from, Vector3 $to): bool;

	public function isAvailable(Vector3 $from, Vector3 $to): bool;
}
