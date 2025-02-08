<?php

declare(strict_types=1);

namespace Echore\NaturalEntity\utils;

use pocketmine\timings\TimingsHandler;

class NaturalEntityTimings {

	public static TimingsHandler $targetSelecting;

	public static TimingsHandler $repulsion;

	public static TimingsHandler $fightUpdate;

	public static function init(): void {
		self::$targetSelecting = new TimingsHandler("Target Selecting");
		self::$fightUpdate = new TimingsHandler("Fight Update");
		self::$repulsion = new TimingsHandler("Repulsion");
	}
}
