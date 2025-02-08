<?php

declare(strict_types=1);

namespace Echore\NaturalEntity;

use Echore\NaturalEntity\option\FightOptions;
use Echore\NaturalEntity\option\MovementOptions;
use Echore\NaturalEntity\option\SelectTargetOptions;
use Echore\NaturalEntity\style\IFightingEntity;
use Echore\NaturalEntity\utils\NaturalEntityTimings;
use Echore\NaturalEntity\utils\VectorUtil;
use Echore\Stargazer\ModifiableValue;
use Echore\Stargazer\ModifierSet;
use OutOfRangeException;
use pocketmine\block\Block;
use pocketmine\block\Cobweb;
use pocketmine\block\Lava;
use pocketmine\block\Water;
use pocketmine\entity\animation\Animation;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\Attribute;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\ObjectSet;
use pocketmine\world\format\Chunk;

abstract class NaturalLivingEntity extends Living implements INaturalEntity, IFightingEntity {

	protected TargetSelector $targetSelector;

	protected ?Entity $instanceTarget;

	protected int $postAttackCoolDown;

	protected int $interesting;

	protected float $attackRange;

	protected bool $immobile = false;

	protected Item $heldItem;

	protected Item $heldItemInOffhand;

	protected MobType $mobType;

	private int $targetingTick;

	private bool $heldItemChanged;

	private FightOptions $fightOptions;

	private MovementOptions $movementOptions;

	private SelectTargetOptions $selectTargetOptions;

	private int $selectTargetCycleTick;

	private ?EntityDamageByEntityEvent $lastDamageCauseByPlayer = null;

	private ?int $lastDamageCauseByPlayerTick = null;

	private ?IPathProvider $pathProvider = null;

	private ?int $lastDamageCauseTick = null;

	private ObjectSet $disposeHooks;

	private ObjectSet $destroyCycleHooks;

	private int $lastRepulsionTick = -1;

	private int $broadcastMovementBuffer = 1;

	private array $lastDamageByEntityTicks = [];

	private ?Vector3 $queuedRepulsionVector;

	private ModifiableValue $additionalAttackCoolDown;

	/**
	 * @return ObjectSet
	 */
	public function getDestroyCycleHooks(): ObjectSet {
		return $this->destroyCycleHooks;
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
			$horizontal = VectorUtil::getAngleDirectionHorizontal($position, $to);
		} else {
			$horizontal = null;
			foreach ([$position] + $position->sidesArray() as $target) {
				foreach ([$to] + $to->sidesArray() as $targetTo) {
					if ($this->pathProvider->isAvailable($target, $targetTo)) {
						$next = $this->pathProvider->getNextPosition($target->floor(), $targetTo->floor());

						$horizontal = VectorUtil::getAngleDirectionHorizontal($position, $next->add(0.5, 0.0, 0.5));
						break 2;
					}
				}
			}

			if ($horizontal === null) {
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

		if ($this->isImmobile()) {
			return;
		}

		$speed = $this->getMovementSpeed();

		if ($sprintSpeed) {
			$speed *= 1.3;
		}

		if (!$this->movementOptions->isIgnoreBlockModifiers()) {
			$speed *= $this->getBlockSpeedModifier($this->getWorld()->getBlock($this->getPosition()));
		}

		$dx = $direction->x * $speed;
		$dz = $direction->y * $speed;

		if ($dx < 0 && $this->motion->x > $dx) {
			$this->motion->x += $dx;
		}

		if ($dx > 0 && $this->motion->x < $dx) {
			$this->motion->x += $dx;
		}

		if ($dz < 0 && $this->motion->z > $dz) {
			$this->motion->z += $dz;
		}

		if ($dz > 0 && $this->motion->z < $dz) {
			$this->motion->z += $dz;
		}
	}

	public function isImmobile(): bool {
		return $this->immobile;
	}

	public function setImmobile(bool $immobile = true): void {
		$this->immobile = $immobile;
	}

	protected function getBlockSpeedModifier(Block $block): float {
		return match ($block::class) {
			Lava::class => 0.15,
			Water::class => 0.2,
			Cobweb::class => 0.1,
			default => 1.0
		};
	}

	public function getMobType(): MobType {
		return $this->mobType;
	}

	public function setMobType(MobType $mobType): void {
		$this->mobType = $mobType;
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

		if ($this->postAttackCoolDown > 0) {
			return false;
		}

		$dist = $this->getEyePos()->distanceSquared($entity->getEyePos());

		if ($dist > $this->getAttackRange() ** 2) {
			return false;
		}

		$this->broadcastAnimation(new ArmSwingAnimation($this));

		$source = $this->postAttack($entity);

		if (!$source->isCancelled()) {
			$this->interesting += 25;
		}

		$this->postAttackCoolDown = $source->getAttackCooldown() + $this->getAdditionalAttackCoolDown()->getFinalFloored();

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

	public function broadcastAnimation(Animation $animation, ?array $targets = null): void {
		NetworkBroadcastUtils::broadcastPackets($targets ?? $this->getViewers(), $animation->encode(), false);
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

	public function getAttackDamage(): float {
		return $this->attributeMap->get(Attribute::ATTACK_DAMAGE)->getValue();
	}

	public function attack(EntityDamageEvent $source): void {
		if ($this->isFlaggedForDespawn() || $this->isClosed()) {
			return;
		}

		if ($source->getCause() === EntityDamageEvent::CAUSE_FALL || $source->getCause() === EntityDamageEvent::CAUSE_SUFFOCATION) {
			$source->cancel();
		}

		if ($source instanceof EntityDamageByEntityEvent && $source->getDamager() !== null) {
			$damager = $source->getDamager();

			if ($damager instanceof Player || $damager?->getOwningEntity() instanceof Player) {
				$this->lastDamageCauseByPlayer = $source;
				$this->lastDamageCauseByPlayerTick = $this->getWorld()->getServer()->getTick();
			}

			$child = $damager?->getOwningEntity();
			if ($child instanceof Player && !$child->isOnline()) {
				return;
			}

			if (($this->lastDamageByEntityTicks[$damager->getId()] ?? 0) - Server::getInstance()->getTick() > 4) {
				$this->lastDamageByEntityTicks[$damager->getId()] = Server::getInstance()->getTick();

				if ($damager !== $this->getInstanceTarget() && $this->canAngryByAttackFrom($damager)) {
					if (is_null($this->getInstanceTarget())) {
						$this->setInstanceTarget($damager, 400);
					} else {
						$this->interesting -= 72;

						if ($this->interesting <= 0) {
							$this->setInstanceTarget($damager, 300);
						}
					}
				} else {
					$this->interesting += 65;
				}
			}
		}

		parent::attack($source);
	}

	public function getInstanceTarget(): ?Entity {
		if ($this->instanceTarget === null) {
			return null;
		}

		if ($this->instanceTarget->isClosed() || !$this->instanceTarget->isAlive()) {
			$this->removeInstanceTarget();
		}

		return $this->instanceTarget;
	}

	public function setInstanceTarget(Entity $entity, int $initialInteresting): void {
		$this->instanceTarget = $entity;
		$this->interesting = $initialInteresting;
		$this->selectTargetCycleTick = 0;
		$this->targetingTick = 0;

		$this->setTargetEntity($entity);
	}

	public function removeInstanceTarget(): void {
		$this->instanceTarget = null;
		$this->interesting = 0;
		$this->targetingTick = 0;

		$this->setTargetEntity(null);
	}

	public function canAngryByAttackFrom(Entity $entity): bool {
		return true;
	}

	public function getAdditionalAttackCoolDown(): ModifiableValue {
		return $this->additionalAttackCoolDown;
	}

	/**
	 * @param EntityDamageEvent|null $type
	 */
	public function setLastDamageCause(?EntityDamageEvent $type): void {
		$this->lastDamageCause = $type;

		if ($type === null) {
			$this->lastDamageCauseTick = null;
		} else {
			$this->lastDamageCauseTick = $this->getWorld()->getServer()->getTick();
		}
	}

	public function getLastDamageCauseTick(): ?int {
		return $this->lastDamageCauseTick;
	}

	public function getLastDamageCauseByPlayer(): ?EntityDamageByEntityEvent {
		return $this->lastDamageCauseByPlayer;
	}

	public function setLastDamageCauseByPlayer(?EntityDamageByEntityEvent $event, ?int $tick = null): void {
		if ($event !== null) {
			$this->lastDamageCauseByPlayerTick = $tick;
			$this->lastDamageCauseByPlayer = $event;
		} else {
			$this->lastDamageCauseByPlayer = null;
			$this->lastDamageCauseByPlayerTick = null;
		}
	}

	public function getInteresting(): int {
		return $this->interesting;
	}

	public function setInteresting(int $interesting): void {
		$this->interesting = $interesting;
	}

	public function setItemInHand(Item $item): void {
		$this->heldItem = $item;
		$this->heldItemChanged = true;
	}

	public function setItemInOffhand(Item $item): void {
		$this->heldItemInOffhand = $item;
		$this->heldItemChanged = true;
	}

	/**
	 * @return int
	 */
	public function getTargetingTick(): int {
		return $this->targetingTick;
	}

	/**
	 * @return ObjectSet
	 */
	public function getDisposeHooks(): ObjectSet {
		return $this->disposeHooks;
	}

	public function onUpdate(int $currentTick): bool {
		$tickDiff = $currentTick - $this->lastUpdate;
		$this->broadcastMovementBuffer -= $tickDiff;

		return parent::onUpdate($currentTick);
	}

	protected function tryChangeMovement(): void {
		parent::tryChangeMovement();
		if ($this->immobile) {
			$this->motion = Vector3::zero();
		}
	}

	protected function destroyCycles(): void {
		parent::destroyCycles();
		unset($this->lastDamageCauseByPlayer, $this->targetSelector, $this->instanceTarget);

		foreach ($this->destroyCycleHooks as $hook) {
			($hook)();
		}
	}

	protected function onDispose(): void {
		parent::onDispose();

		foreach ($this->disposeHooks as $hook) {
			($hook)();
		}
	}

	protected function broadcastMotion(): void {
		NetworkBroadcastUtils::broadcastPackets($this->hasSpawned, [SetActorMotionPacket::create($this->id, $this->getMotion(), tick: 0)], false);
	}

	protected function broadcastMovement(bool $teleport = false): void {
		if (!$teleport) {
			if ($this->broadcastMovementBuffer <= 0) {
				$this->broadcastMovementBuffer = 2;
			} elseif ($this->motion->x <= 0.5 && $this->motion->y <= 0.5 && $this->motion->z <= 0.5) {
				return;
			}
		}

		// crazy hack for rotation bug
		NetworkBroadcastUtils::broadcastPackets($this->hasSpawned, [
			MoveActorAbsolutePacket::create(
				$this->id,
				$this->getOffsetPosition($this->location)->add(
					(lcg_value() - 0.5) * 0.01, // craziest ever in my code. thank you mojang.
					0,
					(lcg_value() - 0.5) * 0.01
				),
				$this->location->pitch,
				$this->location->yaw,
				$this->location->yaw,
				(
					//TODO: We should be setting FLAG_TELEPORT here to disable client-side movement interpolation, but it
					//breaks player teleporting (observers see the player rubberband back to the pre-teleport position while
					//the teleported player sees themselves at the correct position), and does nothing whatsoever for
					//non-player entities (movement is still interpolated). Both of these are client bugs.
					//See https://github.com/pmmp/PocketMine-MP/issues/4394
				($this->onGround ? MoveActorAbsolutePacket::FLAG_GROUND : 0)
				)
			)
		], false);
	}

	protected function initEntity(CompoundTag $nbt): void {
		parent::initEntity($nbt);
		$this->disposeHooks = new ObjectSet();

		$this->movementOptions = new MovementOptions();
		$this->selectTargetOptions = new SelectTargetOptions();
		$this->fightOptions = new FightOptions();
		$this->heldItemChanged = false;
		$this->instanceTarget = null;
		$this->interesting = 0;
		$this->targetingTick = 0;
		$this->targetSelector = $this->getInitialTargetSelector();
		$this->selectTargetCycleTick = 0;
		$this->lastDamageCauseByPlayer = null;
		$this->postAttackCoolDown = 0;
		$this->setAttackRange($this->getInitialAttackRange());
		$this->mobType = $this::getDefaultMobType();
		$this->destroyCycleHooks = new ObjectSet();
		$this->queuedRepulsionVector = null;
		$this->heldItem = VanillaItems::AIR();
		$this->heldItemInOffhand = VanillaItems::AIR();
		// override parent properties
		$this->stepHeight = 1.05;
		$this->additionalAttackCoolDown = new ModifiableValue($this->getInitialAdditionalAttackCoolDown(), ModifierSet::MODE_ADDITION);

		$this->initStyle();
	}

	abstract protected function getInitialTargetSelector(): TargetSelector;

	abstract protected function getInitialAttackRange(): float;

	protected function entityBaseTick(int $tickDiff = 1): bool {
		if ($this->getInstanceTarget() !== null) {
			$this->targetCycle($tickDiff);

			if ($this->interesting <= 0) {
				$this->removeInstanceTarget();
			}
		}

		$this->postAttackCoolDown -= $tickDiff;

		$this->selectTargetCycleTick += $tickDiff;

		NaturalEntityTimings::$repulsion->startTiming();
		$this->processEntityRepulsion();
		NaturalEntityTimings::$repulsion->stopTiming();

		NaturalEntityTimings::$targetSelecting->startTiming();
		if (
			$this->selectTargetOptions->isEnabled() &&
			$this->getInstanceTarget() === null &&
			$this->selectTargetCycleTick > $this->selectTargetOptions->getIntervalTick()
		) {
			$entity = $this->targetSelector->select();

			if ($entity !== null) {
				$this->setInstanceTarget($entity, $this->selectTargetOptions->getInitialInteresting());
			}

			$this->selectTargetCycleTick -= $this->selectTargetOptions->getIntervalTick();
		}
		NaturalEntityTimings::$targetSelecting->stopTiming();

		if ($this->getFightOptions()->isEnabled()) {
			NaturalEntityTimings::$fightUpdate->startTiming();
			$this->onFightUpdate($tickDiff);
			NaturalEntityTimings::$fightUpdate->stopTiming();
		}

		if ($this->heldItemChanged) {
			$this->heldItemChanged = false;
			NetworkBroadcastUtils::broadcastPackets(
				$this->getViewers(),
				[
					MobEquipmentPacket::create(
						$this->getId(),
						ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet($this->heldItem)),
						0,
						0,
						ContainerIds::INVENTORY
					),
					MobEquipmentPacket::create(
						$this->getId(),
						ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet($this->heldItemInOffhand)),
						0,
						0,
						ContainerIds::OFFHAND
					),
				],
				false
			);
		}

		if ($this->queuedRepulsionVector !== null) {
			$this->addMotion($this->queuedRepulsionVector->x, $this->queuedRepulsionVector->y, $this->queuedRepulsionVector->z);
			$this->queuedRepulsionVector = null;
		}

		return parent::entityBaseTick($tickDiff);
	}

	protected function targetCycle(int $tickDiff = 1): void {
		$this->interesting -= $tickDiff;
		$this->targetingTick += $tickDiff;

		if (!$this->canContinueTargeting($this->getInstanceTarget())) {
			$this->interesting = 0;
		}
	}

	public function canContinueTargeting(Entity $entity): bool {
		return ($entity instanceof Living && $entity->getName() !== $this->getName()) && (!($entity instanceof Player) || ($entity->hasFiniteResources() && !$entity->isSpectator()));
	}

	protected function processEntityRepulsion(): void {
		if (!$this->movementOptions->isRepulsionEnabled()) {
			return;
		}

		if ($this->queuedRepulsionVector !== null) {
			return;
		}

		$nextPosition = clone $this->getPosition();

		$diffX = $nextPosition->x - $this->location->x;
		$diffY = $nextPosition->y - $this->location->y;
		$diffZ = $nextPosition->z - $this->location->z;

		$this->optimizedCalculateRepulsion($this->boundingBox->offsetCopy($diffX, $diffY, $diffZ), $this);
	}

	protected function optimizedCalculateRepulsion(AxisAlignedBB $bb, Entity $entity): Vector3 {
		$minX = ((int) floor($bb->minX + 0.001)) >> Chunk::COORD_BIT_SIZE;
		$maxX = ((int) ceil($bb->maxX + 0.001)) >> Chunk::COORD_BIT_SIZE;
		$minZ = ((int) floor($bb->minZ - 0.001)) >> Chunk::COORD_BIT_SIZE;
		$maxZ = ((int) ceil($bb->maxZ + 0.001)) >> Chunk::COORD_BIT_SIZE;

		for ($x = $minX; $x <= $maxX; ++$x) {
			for ($z = $minZ; $z <= $maxZ; ++$z) {
				foreach ($entity->getWorld()->getChunkEntities($x, $z) as $ent) {
					if ($ent !== $entity && $ent->boundingBox->intersectsWith($bb)) {
						if ($ent->canBeCollidedWith() && ($entity->canCollideWith($ent))) {
							$deltaX = $this->location->x - $ent->location->x;
							$deltaZ = $this->location->z - $ent->location->z;
							$f = 1 / max(sqrt($deltaX ** 2 + $deltaZ ** 2), 0.01);
							$kb = new Vector3(
								$deltaX * ($f * 0.2),
								0,
								$deltaZ * ($f * 0.2),
							);
							$this->queuedRepulsionVector = $kb->multiply(0.5);
							if ($ent instanceof NaturalLivingEntity && $ent->queuedRepulsionVector === null && $ent->getMovementOptions()->isRepulsionEnabled()) {
								$ent->queuedRepulsionVector = $this->queuedRepulsionVector->multiply(-1);
							}

							return $this->queuedRepulsionVector;
						}
					}
				}
			}
		}

		$this->queuedRepulsionVector = Vector3::zero();

		return $this->queuedRepulsionVector;
	}

	public function getMovementOptions(): MovementOptions {
		return $this->movementOptions;
	}

	public function getFightOptions(): FightOptions {
		return $this->fightOptions;
	}

	protected function originallyCalculateRepulsion(AxisAlignedBB $bb, Entity $entity): Vector3 {
		$minX = ((int) floor($bb->minX - 1)) >> Chunk::COORD_BIT_SIZE;
		$maxX = ((int) ceil($bb->maxX + 1)) >> Chunk::COORD_BIT_SIZE;
		$minZ = ((int) floor($bb->minZ - 1)) >> Chunk::COORD_BIT_SIZE;
		$maxZ = ((int) ceil($bb->maxZ + 1)) >> Chunk::COORD_BIT_SIZE;

		$repulsion = Vector3::zero();

		for ($x = $minX; $x <= $maxX; ++$x) {
			for ($z = $minZ; $z <= $maxZ; ++$z) {
				foreach ($entity->getWorld()->getChunkEntities($x, $z) as $ent) {
					if ($ent !== $entity && $ent->boundingBox->intersectsWith($bb)) {
						if ($ent->canBeCollidedWith() && ($entity->canCollideWith($ent))) {
							$deltaX = $this->location->x - $ent->location->x;
							$deltaZ = $this->location->z - $ent->location->z;
							$f = 1 / max(sqrt($deltaX ** 2 + $deltaZ ** 2), 0.01);
							$kb = new Vector3(
								$deltaX * ($f * 0.2),
								0,
								$deltaZ * ($f * 0.2),
							);
							$repulsion = $repulsion->addVector($kb->multiply(0.5));
						}
					}
				}
			}
		}

		$this->queuedRepulsionVector = $repulsion;

		return $repulsion;
	}

	protected function checkBlockIntersections(): void {
	}
}
