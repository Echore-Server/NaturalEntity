<?php

declare(strict_types=1);

namespace Echore\NaturalEntity;

use Echore\NaturalEntity\option\MovementOptions;
use Echore\NaturalEntity\option\SelectTargetOptions;
use Echore\NaturalEntity\style\IFightingEntity;
use Echore\NaturalEntity\utils\VectorUtil;
use Lyrica0954\SmartEntity\SmartEntity;
use OutOfRangeException;
use pocketmine\block\Block;
use pocketmine\block\Cobweb;
use pocketmine\block\Lava;
use pocketmine\block\Water;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\Attribute;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\math\VoxelRayTrace;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\world\Position;

abstract class NaturalLivingEntity extends Living implements INaturalEntity, IFightingEntity {

	protected TargetSelector $targetSelector;

	protected ?Entity $instanceTarget;

	protected int $postAttackCoolDown;

	protected int $interesting;

	protected float $attackRange;

	private int $targetBlockSolidTriggerTick = 0;

	private MovementOptions $movementOptions;

	private SelectTargetOptions $selectTargetOptions;

	private int $selectTargetCycleTick;

	private ?EntityDamageByEntityEvent $lastDamageCauseByPlayer = null;

	private ?int $lastDamageCauseByPlayerTick = null;

	private ?IPathProvider $pathProvider = null;

	public function getMovementOptions(): MovementOptions {
		return $this->movementOptions;
	}

	public function getPathProvider(): ?IPathProvider {
		return $this->pathProvider;
	}

	public function setPathProvider(?IPathProvider $pathProvider): void {
		$this->pathProvider = $pathProvider;
	}

	public function getLastDamageCauseByPlayerTick(): ?int {
		return $this->lastDamageCauseByPlayerTick;
	}

	/**
	 * @return SelectTargetOptions
	 */
	public function getSelectTargetOptions(): SelectTargetOptions {
		return $this->selectTargetOptions;
	}

	public function getTargetSelector(): TargetSelector {
		return $this->targetSelector;
	}

	public function walkTo(Vector3 $to, bool $sprintSpeed): ?Vector2 {
		if (!$this->movementOptions->isWalkEnabled()) {
			return null;
		}

		if (!$this->movementOptions->isWalkInAir() && !$this->isOnGround()) {
			return null;
		}

		$position = $this->getPosition()->add(0, 0.5, 0);

		if ($this->pathProvider?->isReachable($position, $to) ?? true) {
			$horizontal = VectorUtil::getDirectionHorizontal(VectorUtil::getAngle($position, $to)->x);
		} else {
			$horizontal = null;
			foreach (array_merge([$position], $position->sidesArray()) as $target) {
				foreach (array_merge([$to], $to->sidesArray()) as $targetTo) {
					if ($this->pathProvider->isAvailable($target, $targetTo)) {
						$next = $this->pathProvider->getNextPosition($target->floor(), $targetTo->floor());

						$horizontal = VectorUtil::getDirectionHorizontal(VectorUtil::getAngle($position, $next->add(0.5, 0.0, 0.5))->x);
						break 2;
					}
				}
			}

			if (is_null($horizontal)) {
				return null;
			}
		}


		$this->walk($v = new Vector2($horizontal->x, $horizontal->z), $sprintSpeed);

		return $v;
	}

	public function walk(Vector2 $direction, bool $sprintSpeed): void {
		if (!$this->movementOptions->isWalkEnabled()) {
			return;
		}

		if (!$this->movementOptions->isWalkInAir() && !$this->isOnGround()) {
			return;
		}

		$speed = $this->getMovementSpeed();

		if ($sprintSpeed) {
			$speed *= 1.3;
		}

		if (!$this->movementOptions->isIgnoreBlockModifiers()) {
			$speed *= $this->getBlockSpeedModifier($this->getWorld()->getBlock($this->getPosition()));
		}

		$vector = new Vector3($direction->x * $speed, 0, $direction->y * $speed);

		if ($vector->x < 0 && $this->motion->x > $vector->x) {
			$this->addMotion($vector->x, 0, 0);
		}

		if ($vector->x > 0 && $this->motion->x < $vector->x) {
			$this->addMotion($vector->x, 0, 0);
		}

		if ($vector->z < 0 && $this->motion->z > $vector->z) {
			$this->addMotion(0, 0, $vector->z);
		}

		if ($vector->z > 0 && $this->motion->z < $vector->z) {
			$this->addMotion(0, 0, $vector->z);
		}
	}

	protected function getBlockSpeedModifier(Block $block): float {
		return match ($block::class) {
			Lava::class => 0.15,
			Water::class => 0.2,
			Cobweb::class => 0.1,
			default => 1.0
		};
	}

	public function walkForward(bool $sprintSpeed = true): void {
		$this->walk($this->getDirection2D($this->getLocation()->getYaw()), $sprintSpeed);
	}

	private function getDirection2D(float $yaw): Vector2 {
		$x = -sin(deg2rad($yaw));
		$z = cos(deg2rad($yaw));

		$hor = new Vector2($x, $z);

		return $hor->normalize();
	}

	public function walkBackward(): void {
		$this->walk($this->getDirection2D($this->getLocation()->getYaw())->multiply(-1), false);
	}

	/**
	 * @return int
	 */
	public function getPostAttackCoolDown(): int {
		return $this->postAttackCoolDown;
	}

	/**
	 * @param positive-int $coolDown
	 */
	public function setPostAttackCoolDown(int $coolDown): void {
		$this->postAttackCoolDown = $coolDown;
	}

	public function tryAttackEntity(Entity $entity): bool {
		if ($entity->isClosed() || !$entity->isAlive()) {
			return false;
		}

		$dist = VectorUtil::distanceToAABB($this->getEyePos(), $entity->getBoundingBox());

		if ($dist > $this->getAttackRange()) {
			return false;
		}

		if ($this->postAttackCoolDown > 0) {
			return false;
		}

		$this->broadcastAnimation(new ArmSwingAnimation($this));

		$source = $this->postAttack($entity);

		if (!$source->isCancelled()) {
			$this->interesting += 25;
		}

		$this->postAttackCoolDown = $source->getAttackCooldown() + $this->getAdditionalAttackCoolDown();

		return true;
	}

	public function getAttackRange(): float {
		return $this->attackRange;
	}

	public function setAttackRange(float $range): void {
		if ($range < 0) {
			throw new OutOfRangeException("Attack range must be 0 ~ PHP_INT_MAX");
		}

		$this->attackRange = $range;
	}

	public function postAttack(Entity $entity): EntityDamageByEntityEvent {
		$source = new EntityDamageByEntityEvent(
			$this,
			$entity,
			EntityDamageEvent::CAUSE_ENTITY_ATTACK,
			$this->getAttackDamage(),
			[]
		);

		$entity->attack($source);

		return $source;
	}

	private function getAttackDamage(): float {
		return $this->attributeMap->get(Attribute::ATTACK_DAMAGE)->getValue();
	}

	public function attack(EntityDamageEvent $source): void {
		if ($source instanceof EntityDamageByEntityEvent) {
			$damager = $source->getDamager();

			if ($damager instanceof Player || $damager->getOwningEntity() instanceof Player) {
				$this->lastDamageCauseByPlayer = $source;
				$this->lastDamageCauseByPlayerTick = $this->getWorld()->getServer()->getTick();
			}

			if ($damager !== $this->getInstanceTarget()) {
				if (is_null($this->getInstanceTarget())) {
					$this->setInstanceTarget($damager, 400);
				} else {
					$this->interesting -= 52;

					if ($this->interesting <= 0) {
						$this->setInstanceTarget($damager, 300);
					}
				}
			} else {
				$this->interesting += 45;
			}
		}

		parent::attack($source);
	}

	public function getInstanceTarget(): ?Entity {
		if (is_null($this->instanceTarget)) {
			return null;
		}

		return ($this->instanceTarget->isClosed() || !$this->instanceTarget->isAlive()) ? $this->instanceTarget = null : $this->instanceTarget;
	}

	public function setInstanceTarget(Entity $entity, int $initialInteresting): void {
		$this->instanceTarget = $entity;
		$this->interesting = $initialInteresting;
		$this->selectTargetCycleTick = 0;

		$this->setTargetEntity($entity);
	}

	public function getLastDamageCauseByPlayer(): ?EntityDamageByEntityEvent {
		return $this->lastDamageCauseByPlayer;
	}

	public function getInteresting(): int {
		return $this->interesting;
	}

	public function setInteresting(int $interesting): void {
		$this->interesting = $interesting;
	}

	protected function initEntity(CompoundTag $nbt): void {
		parent::initEntity($nbt);

		$this->movementOptions = new MovementOptions();
		$this->selectTargetOptions = new SelectTargetOptions();
		$this->instanceTarget = null;
		$this->interesting = 0;
		$this->targetSelector = $this->getInitialTargetSelector();
		$this->selectTargetCycleTick = 0;
		$this->lastDamageCauseByPlayer = null;
		$this->postAttackCoolDown = 0;
		$this->setAttackRange($this->getInitialAttackRange());

		// override parent properties
		$this->stepHeight = 1.05;
	}

	abstract protected function getInitialTargetSelector(): TargetSelector;

	abstract protected function getInitialAttackRange(): float;

	protected function entityBaseTick(int $tickDiff = 1): bool {
		if ($this->instanceTarget !== null) {
			$this->targetCycle($tickDiff);

			if ($this->interesting < 0) {
				$this->removeInstanceTarget();
			}
		}

		$this->postAttackCoolDown -= $tickDiff;

		$this->selectTargetCycleTick += $tickDiff;

		$this->processEntityRepulsion();

		if (
			$this->selectTargetOptions->isEnabled() &&
			is_null($this->getInstanceTarget()) &&
			$this->selectTargetCycleTick > $this->selectTargetOptions->getIntervalTick()
		) {
			$entity = $this->targetSelector->select();

			if (!is_null($entity)) {
				$this->setInstanceTarget($entity, $this->selectTargetOptions->getInitialInteresting());
			}

			$this->selectTargetCycleTick -= $this->selectTargetOptions->getIntervalTick();
		}

		$this->onFightUpdate($tickDiff);

		return parent::entityBaseTick($tickDiff);
	}

	protected function targetCycle(int $tickDiff = 1): void {
		$this->interesting -= $tickDiff;
	}

	public function removeInstanceTarget(): void {
		$this->instanceTarget = null;
		$this->interesting = 0;

		$this->setTargetEntity(null);
	}

	protected function processEntityRepulsion(): void {
		$nextPosition = clone $this->getPosition();
		$collidingEntities = $this->getCollidingEntitiesWithDiff($nextPosition);
		if (count($collidingEntities) > 0) {
			$repulsion = [];
			foreach ($collidingEntities as $entity) {
				$deltaX = $this->location->x - $entity->location->x;
				$deltaZ = $this->location->z - $entity->location->z;
				$f = 1 / max(sqrt(pow($deltaX, 2) + pow($deltaZ, 2)), 0.01);
				$kb = new Vector3(
					$deltaX * ($f * 0.2),
					0,
					$deltaZ * ($f * 0.2),
				);
				$repulsion[] = $kb->multiply(0.5);
			}

			$move = Vector3::sum(...$repulsion);

			if (count($repulsion) >= 1) {
				$this->addMotion($move->x, $move->y, $move->z);
			}
		}
	}

	/**
	 * @param Position $pos
	 * @return Entity[]
	 */
	protected function getCollidingEntitiesWithDiff(Position $pos): array {
		$diffX = $pos->x - $this->location->x;
		$diffY = $pos->y - $this->location->y;
		$diffZ = $pos->z - $this->location->z;

		#heavy

		return $this->getWorld()->getCollidingEntities($this->boundingBox->offsetCopy($diffX, $diffY, $diffZ), $this);
	}

	private function walkToPri(Vector3 $to, bool $sprintSpeed): ?Vector2 {
		if (!$this->movementOptions->isWalkEnabled()) {
			return null;
		}

		if (!$this->movementOptions->isWalkInAir() && !$this->isOnGround()) {
			return null;
		}

		$position = $this->getPosition()->add(0, 0.5, 0);

		$danger = false;

		if ($this->getWorld()->getServer()->getTick() - $this->targetBlockSolidTriggerTick < 50) {
			$danger = true;
		}

		$horizontal = VectorUtil::getDirectionHorizontal(VectorUtil::getAngle($position, $to)->x);

		if (!$danger) {
			$block = $this->getWorld()->getBlock($this->getPosition()->subtract(0, 0.5, 0)->addVector($horizontal->multiply(2.0)));

			if (!$block->isSolid()) {
				$this->targetBlockSolidTriggerTick = $this->getWorld()->getServer()->getTick();
				$danger = true;
			}
		}


		if (!$danger) {
			foreach (VoxelRayTrace::betweenPoints($this->getPosition(), $to) as $vec) {
				$targetEyeBlock = $this->getWorld()->getBlock($vec->add(0, 1, 0));

				if ($targetEyeBlock->isSolid()) {
					$this->targetBlockSolidTriggerTick = $this->getWorld()->getServer()->getTick();
					$danger = true;
					break;
				}
			}
		}

		if ($danger) {
			$horizontal = null;
			foreach (array_merge([$position], $position->sidesArray()) as $target) {
				foreach (array_merge([$to], $to->sidesArray()) as $targetTo) {
					if ($this->pathProvider?->isAvailable($target, $targetTo)) {
						$next = $this->pathProvider->getNextPosition($target->floor(), $targetTo->floor());

						$horizontal = VectorUtil::getDirectionHorizontal(VectorUtil::getAngle($position, $next->add(0.5, 0.0, 0.5))->x);
						break 2;
					}
				}
			}
		}


		if (is_null($horizontal)) {
			return null;
		}

		$this->walk($v = new Vector2($horizontal->x, $horizontal->z), $sprintSpeed);

		return $v;
	}
}
