<?php

namespace Echore\NaturalEntity\style;

interface IFightingEntity {

	public function onFightUpdate(int $tickDiff = 1): void;

	public function getAimFlexibility(): int;

}
