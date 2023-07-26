<?php

declare(strict_types=1);

namespace Echore\NaturalEntity;

use pocketmine\player\Player;

class DefaultTargetSelector {

	public static function hostile(INaturalEntity $entity, bool $includePlayer): TargetSelector {
		$selector = new TargetSelector($entity);

		$selector->setGroupWeight(MobType::FRIEND, 100);
		$selector->setGroupWeight(MobType::NEUTRAL, 40);

		if ($includePlayer) {
			$selector->setEntityWeight(Player::class, 100);
		}

		return $selector;
	}

	public static function friend(INaturalEntity $entity): TargetSelector {
		$selector = new TargetSelector($entity);

		$selector->setGroupWeight(MobType::HOSTILE, 100);

		return $selector;
	}

	public static function neutral(INaturalEntity $entity): TargetSelector {
		return new TargetSelector($entity);
	}

}
