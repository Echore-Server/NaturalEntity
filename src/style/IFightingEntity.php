<?php

declare(strict_types=1);

namespace Echore\NaturalEntity\style;

use Echore\NaturalEntity\option\FightOptions;

interface IFightingEntity {

	public function onFightUpdate(int $tickDiff = 1): void;

	public function getAimFlexibility(): int;

	public function getFightOptions(): FightOptions;

}
