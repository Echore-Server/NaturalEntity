<?php

declare(strict_types=1);

namespace Echore\NaturalEntity;

use Echore\NaturalEntity\option\FightOptions;
use Echore\NaturalEntity\option\MovementOptions;
use Echore\NaturalEntity\option\SelectTargetOptions;
use Echore\NaturalEntity\style\IFightingEntity;
use Echore\NaturalEntity\utils\VectorUtil;
use OutOfRangeException;
use pocketmine\block\Block;
use pocketmine\block\Cobweb;
use pocketmine\block\Lava;
use pocketmine\block\Water;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\Attribute;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\EntityEventBroadcaster;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\timings\Timings;
use pocketmine\utils\ObjectSet;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;

abstract class NaturalLivingEntity extends Living implements INaturalEntity, IFightingEntity {

	protected TargetSelector $targetSelector;

	protected ?Entity $instanceTarget;

	protected int $postAttackCoolDown;

	protected int $interesting;

	protected float $attackRange;

	protected bool $immobile = false;

	protected Item $heldItem;

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

	/**
	 * @return ObjectSet
	 */
	public function getDestroyCycleHooks(): ObjectSet {
		return $this->destroyCycleHooks;
	}

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
			foreach ([$position] + $position->sidesArray() as $target) {
				foreach ([$to] + $to->sidesArray() as $targetTo) {
					if ($this->pathProvider->isAvailable($target, $targetTo)) {
						$next = $this->pathProvider->getNextPosition($target->floor(), $targetTo->floor());

						$horizontal = VectorUtil::getDirectionHorizontal(VectorUtil::getAngle($position, $next->add(0.5, 0.0, 0.5))->x);
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

		$dist = $this->getEyePos()->distance($entity->getEyePos());

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

	public function getAttackDamage(): float {
		return $this->attributeMap->get(Attribute::ATTACK_DAMAGE)->getValue();
	}

	public function attack(EntityDamageEvent $source): void {
		if ($source instanceof EntityDamageByEntityEvent) {
			$damager = $source->getDamager();

			if ($damager instanceof Player || $damager?->getOwningEntity() instanceof Player) {
				$this->lastDamageCauseByPlayer = $source;
				$this->lastDamageCauseByPlayerTick = $this->getWorld()->getServer()->getTick();
			}

			$child = $damager?->getOwningEntity();
			if ($child instanceof Player && !$child->isOnline()) {
				return;
			}

			if ($damager !== $this->getInstanceTarget() && $this->canAngryByAttackFrom($damager)) {
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

	protected function move(float $dx, float $dy, float $dz): void {
		$this->blocksAround = null;

		Timings::$entityMove->startTiming();
		Timings::$entityMoveCollision->startTiming();

		$wantedX = $dx;
		$wantedY = $dy;
		$wantedZ = $dz;

		if ($this->keepMovement) {
			$this->boundingBox->offset($dx, $dy, $dz);
		} else {
			$this->ySize *= self::STEP_CLIP_MULTIPLIER;

			$moveBB = clone $this->boundingBox;

			assert(abs($dx) <= 20 && abs($dy) <= 20 && abs($dz) <= 20, "Movement distance is excessive: dx=$dx, dy=$dy, dz=$dz");

			$list = $this->getWorld()->getCollisionBoxes($this, $moveBB->addCoord($dx, $dy, $dz), false);

			foreach ($list as $bb) {
				$dy = $bb->calculateYOffset($moveBB, $dy);
			}

			$moveBB->offset(0, $dy, 0);

			$fallingFlag = ($this->onGround || ($dy != $wantedY && $wantedY < 0));

			foreach ($list as $bb) {
				$dx = $bb->calculateXOffset($moveBB, $dx);
			}

			$moveBB->offset($dx, 0, 0);

			foreach ($list as $bb) {
				$dz = $bb->calculateZOffset($moveBB, $dz);
			}

			$moveBB->offset(0, 0, $dz);

			if ($this->stepHeight > 0 && $fallingFlag && ($wantedX != $dx || $wantedZ != $dz)) {
				$cx = $dx;
				$cy = $dy;
				$cz = $dz;
				$dx = $wantedX;
				$dy = $this->stepHeight;
				$dz = $wantedZ;

				$stepBB = clone $this->boundingBox;

				$list = $this->getWorld()->getCollisionBoxes($this, $stepBB->addCoord($dx, $dy, $dz), false);
				foreach ($list as $bb) {
					$dy = $bb->calculateYOffset($stepBB, $dy);
				}

				$stepBB->offset(0, $dy, 0);

				foreach ($list as $bb) {
					$dx = $bb->calculateXOffset($stepBB, $dx);
				}

				$stepBB->offset($dx, 0, 0);

				foreach ($list as $bb) {
					$dz = $bb->calculateZOffset($stepBB, $dz);
				}

				$stepBB->offset(0, 0, $dz);

				$reverseDY = -$dy;
				foreach ($list as $bb) {
					$reverseDY = $bb->calculateYOffset($stepBB, $reverseDY);
				}
				$dy += $reverseDY;
				$stepBB->offset(0, $reverseDY, 0);

				if (($cx ** 2 + $cz ** 2) >= ($dx ** 2 + $dz ** 2)) {
					$dx = $cx;
					$dy = $cy;
					$dz = $cz;
				} else {
					$moveBB = $stepBB;
					$this->ySize += $dy;
				}
			}

			$this->boundingBox = $moveBB;
		}
		Timings::$entityMoveCollision->stopTiming();

		$this->location = new Location(
			($this->boundingBox->minX + $this->boundingBox->maxX) / 2,
			$this->boundingBox->minY - $this->ySize,
			($this->boundingBox->minZ + $this->boundingBox->maxZ) / 2,
			$this->location->world,
			$this->location->yaw,
			$this->location->pitch
		);

		$this->getWorld()->onEntityMoved($this);
		// $this->checkBlockIntersections();
		$this->checkGroundState($wantedX, $wantedY, $wantedZ, $dx, $dy, $dz);
		$postFallVerticalVelocity = $this->updateFallState($dy, $this->onGround);

		$this->motion = $this->motion->withComponents(
			$wantedX != $dx ? 0 : null,
			$postFallVerticalVelocity ?? ($wantedY != $dy ? 0 : null),
			$wantedZ != $dz ? 0 : null
		);

		//TODO: vehicle collision events (first we need to spawn them!)

		Timings::$entityMove->stopTiming();
	}

	protected function initEntity(CompoundTag $nbt): void {
		parent::initEntity($nbt);
		$this->optimizeArmorListener();
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

		$this->heldItem = VanillaItems::AIR();
		// override parent properties
		$this->stepHeight = 1.05;

		$this->initStyle();
	}

	private function optimizeArmorListener(): void {
		$this->armorInventory->getListeners()->clear();

		$this->armorInventory->getListeners()->add(
			new CallbackInventoryListener(
				function(Inventory $inventory, int $slot, Item $oldItem): void {
					$newItem = $inventory->getItem($slot);

					if (!$newItem->equals($oldItem, checkCompound: false)) {
						NetworkBroadcastUtils::broadcastEntityEvent(
							$this->getViewers(),
							fn(EntityEventBroadcaster $broadcaster, array $recipients) => $broadcaster->onMobArmorChange($recipients, $this)
						);
					}
				},
				function(Inventory $inventory, array $oldContents): void {
					NetworkBroadcastUtils::broadcastEntityEvent(
						$this->getViewers(),
						fn(EntityEventBroadcaster $broadcaster, array $recipients) => $broadcaster->onMobArmorChange($recipients, $this)
					);
				}
			)
		);
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

		$this->processEntityRepulsion();

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

		if ($this->getFightOptions()->isEnabled()) {
			$this->onFightUpdate($tickDiff);
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
					)
				]
			);
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
		$nextPosition = clone $this->getPosition();

		$diffX = $nextPosition->x - $this->location->x;
		$diffY = $nextPosition->y - $this->location->y;
		$diffZ = $nextPosition->z - $this->location->z;

		$tick = Server::getInstance()->getTick();

		if ($tick - $this->lastRepulsionTick <= 1) {
			return;
		}

		$repulsion = $this->simplifiedCalculateRepulsion($this->boundingBox->offsetCopy($diffX, $diffY, $diffZ), $this);

		$success = true;
		if ($repulsion->x === 0 && $repulsion->y === 0 && $repulsion->z === 0) {
			$success = false;
		}

		if ($success) {
			$this->lastRepulsionTick = -1;
		} else {
			$this->lastRepulsionTick = $tick;
		}

		$this->addMotion($repulsion->x, $repulsion->y, $repulsion->z);
	}

	protected function simplifiedCalculateRepulsion(AxisAlignedBB $bb, Entity $entity): Vector3 {
		$minX = ((int) floor($bb->minX - 2)) >> Chunk::COORD_BIT_SIZE;
		$maxX = ((int) floor($bb->maxX + 2)) >> Chunk::COORD_BIT_SIZE;
		$minZ = ((int) floor($bb->minZ - 2)) >> Chunk::COORD_BIT_SIZE;
		$maxZ = ((int) floor($bb->maxZ + 2)) >> Chunk::COORD_BIT_SIZE;

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

		return $repulsion;
	}

	public function getFightOptions(): FightOptions {
		return $this->fightOptions;
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

		return $this->simplifiedGetCollidingEntities($this->boundingBox->offsetCopy($diffX, $diffY, $diffZ), $this);
	}
}
