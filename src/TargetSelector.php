<?php

declare(strict_types=1);

namespace Echore\NaturalEntity;

use LogicException;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
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

		$this->targets[$entityClass] = $choiceWeight;
	}

	public function setGroupWeight(MobType $type, int $choiceWeight): void {
		$this->groups[$type->name] = $choiceWeight;
	}

	public function select(): ?Entity {
		$range = $this->naturalEntity->getTargetingRange() / 2;

		$choices = [];
		$instances = [];
		foreach (
			$this->naturalEntity->getWorld()->getNearbyEntities(
				$this->naturalEntity->getBoundingBox()->expandedCopy(
					$range,
					$range,
					$range
				),
				$this->naturalEntity
			) as $entity
		) {
			if ($entity->isClosed() || !$entity->isAlive()) {
				continue;
			}

			if (!$this->naturalEntity->getSelectTargetOptions()->isSelectInvisible() && $entity->isInvisible()) {
				continue;
			}

			$instances[$entity::class] ??= [];
			$instances[$entity::class][] = $entity;
			$choices[] = $entity::class;
		}

		if (count($choices) === 0) {
			return null;
		}

		$entityClass = $this->getChoice($choices);

		if (is_null($entityClass)) {
			return null;
		}

		return $instances[$entityClass][array_rand($instances[$entityClass] ?? throw new LogicException("Instance not found"))];
	}

	/**
	 * @param (class-string<Entity|INaturalEntity>)[] $choices
	 * @return string|null
	 */
	private function getChoice(array $choices): ?string {
		if (count($choices) === 0) {
			return null;
		}

		$targets = [];

		foreach ($choices as $entityClass) {
			if (!$this->isSelectable($entityClass)) {
				continue;
			}

			$targets[$entityClass] ??= 0;
			$targets[$entityClass] += $this->getWeight($entityClass);
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

	public function isSelectable(string $entityClass): bool {
		return $this->getWeight($entityClass) > 0;
	}

	public function getWeight(string $entityClass): int {
		if (isset($this->targets[$entityClass])) {
			return $this->targets[$entityClass];
		}

		if (is_a($entityClass, INaturalEntity::class, true)) {
			return $this->groups[$entityClass::getMobType()->name] ?? 0;
		}

		return 0;
	}
}
