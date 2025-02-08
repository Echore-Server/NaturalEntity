<?php

declare(strict_types=1);

namespace Echore\NaturalEntity;

use Echore\NaturalEntity\utils\NaturalEntityTimings;
use pocketmine\plugin\PluginBase;


class Main extends PluginBase {

	private static bool $ffiFastMathExists = false;

	public static function isFFIFastMathExists(): bool {
		/** @noinspection PhpFullyQualifiedNameUsageInspection */
		return self::$ffiFastMathExists && \Echore\FastMath\FFIFastMath::isInitialized();
	}

	public static function bench(callable $func): void {
		$start = hrtime(true) / 1e+6;
		for ($x = 0; $x < 1000; $x++) {
			for ($y = 0; $y < 100; $y++) {
				for ($z = 0; $z < 100; $z++) {
					$func($x, $y, $z, $x + 1, $y + 1, $z + 1);
				}
			}
		}
		$end = hrtime(true) / 1e+6;

		$time = round($end - $start, 5);

		echo "$time ms" . PHP_EOL;
	}

	protected function onLoad(): void {
		NaturalEntityTimings::init();
		self::$ffiFastMathExists = class_exists('\Echore\FastMath\FFIFastMath');
		$lib = $this->getResourcePath("lib.so");
		$header = $this->getResourcePath("header.h");

		if (file_exists($lib)) {
			//FFIEntityMovement::init(file_get_contents($header), $lib);
		}
	}
}
