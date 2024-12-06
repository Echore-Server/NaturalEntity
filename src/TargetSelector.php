<?php

declare(strict_types=1);

namespace Echore\NaturalEntity;

use LogicException;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\player\Player;
use RuntimeException;

class TargetSelector {

	/**
	 * @var INaturalEntity&Entity
	 */
	protected INaturalEntity $naturalEntity;

	/**
	 * @var array<class-string<Entity>, int>
	 */
	protected array $targets;

	protected array $groups;

	public function __construct(INaturalEntity $entity) {
		if (!$entity instanceof Living) {
			throw new RuntimeException("Entity must implements INaturalEntity and extends Living");
		}

		$this->naturalEntity = $entity;
		$this->targets = [];
		$this->groups = [];
	}

	/**
	 * @param class-string<Entity> $entityClass
	 * @param int $choiceWeight
	 * @param bool $override
	 * @return void
	 */
	public function setEntityWeight(string $entityClass, int $choiceWeight, bool $override = false): void {
		if (!$override && isset($this->targets[$entityClass])) {
			throw new RuntimeException("Already registered target");
		}

		if ($choiceWeight <= 0) {
			unset($this->targets[$entityClass]);

			return;
		}

		$this->targets[$entityClass] = $choiceWeight;
	}

	public function setGroupWeight(MobType $type, int $choiceWeight): void {
		$this->groups[$type->name] = $choiceWeight;
	}

	public function select(): ?Entity {
		$range = $this->naturalEntity->getTargetingRange() / 2;
		$bb = $this->naturalEntity->getBoundingBox()->expandedCopy(
			$range,
			$range,
			$range
		);

		$start = microtime(true);
		if (MobTypeWorldMapService::isInitialized() && $this->shouldUseFriendlyOptimizedSelection()) {
			$needsDistanceCheck = true;
			$entities = MobTypeWorldMapService::getEntities($this->naturalEntity->getWorld(), MobType::FRIEND);
			$ar = $this->targets;
			unset($ar[Player::class]);
			foreach ($ar as $entityClass => $weight) {
				$entities += MobTypeWorldMapService::getEntitiesByClass($this->naturalEntity->getWorld(), $entityClass);
			}
		} else {
			$needsDistanceCheck = false;
			$entities = $this->naturalEntity->getWorld()->getNearbyEntities(
				$bb,
				$this->naturalEntity
			);
		}
		$time = microtime(true) - $start;

		$rounded = round($time * 1000, 5);
		var_dump("time: {$rounded}ms");
		$weights = [];
		$instances = [];
		foreach (
			$entities as $entity
		) {
			if ($entity->isClosed() || !$entity->isAlive()) {
				continue;
			}

			if ($needsDistanceCheck && !$entity->getBoundingBox()->intersectsWith($bb)) {
				continue;
			}

			if (!$this->naturalEntity->getSelectTargetOptions()->isSelectInvisible() && $entity->isInvisible()) {
				continue;
			}

			$weight = $this->getWeight($entity);

			if ($weight <= 0) {
				continue;
			}

			$instances[$entity::class] ??= [];
			$instances[$entity::class][] = $entity;
			$weights[$entity::class] ??= 0;
			$weights[$entity::class] += $weight;
		}

		if (count($weights) === 0) {
			return null;
		}

		$entityClass = $this->getChoice($weights);

		if ($entityClass === null) {
			return null;
		}

		return $instances[$entityClass][array_rand($instances[$entityClass] ?? throw new LogicException("Instance not found"))];
	}

	public function shouldUseFriendlyOptimizedSelection(): bool {
		return (count($this->groups) === 1 && isset($this->groups[MobType::FRIEND->name]));
	}

	public function getWeight(Entity $entity): int {
		$entityClass = $entity::class;
		if (isset($this->targets[$entityClass])) {
			return $this->targets[$entityClass];
		}

		if (is_a($entityClass, INaturalEntity::class, true)) {
			/**
			 * @var INaturalEntity&Entity $entity
			 */
			return $this->groups[$entity->getMobType()->name] ?? 0;
		}

		return 0;
	}

	/**
	 * @param (Entity|INaturalEntity)[] $choices
	 * @return string|null
	 */
	private function getChoice(array $targets): ?string {
		if (count($targets) === 0) {
			return null;
		}

		$sum = (int) array_sum($targets);

		if ($sum < 0) {
			throw new LogicException("Sum < 0, this should not be happen");
		}

		if ($sum === 0) {
			return null;
		}

		asort($targets, SORT_NUMERIC);

		$rand = mt_rand(1, $sum);

		foreach ($targets as $key => $value) {
			$rand -= $value;
			if ($rand <= 0) {
				return $key;
			}
		}

		throw new LogicException("This should not be happen");
	}
}
