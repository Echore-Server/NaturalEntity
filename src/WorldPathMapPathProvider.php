<?php

declare(strict_types=1);

namespace Echore\NaturalEntity;

use Echore\WorldPathMap\WorldPathMap;
use pocketmine\math\Vector3;

class WorldPathMapPathProvider implements IPathProvider {

	public function __construct(private readonly WorldPathMap $map) {
	}

	public function getNextPosition(Vector3 $from, Vector3 $to): Vector3 {
		return $this->map->fetchNextPosition($from, $to);
	}

	public function isReachable(Vector3 $from, Vector3 $to): bool {
		return $this->map->getPath($to)?->getReachable($from) ?? false;
	}

	public function isAvailable(Vector3 $from, Vector3 $to): bool {
		return $this->map->getPath($to)?->isAvailable($from) ?? false;
	}
}
