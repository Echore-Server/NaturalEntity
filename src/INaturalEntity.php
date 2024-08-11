<?php

declare(strict_types=1);

namespace Echore\NaturalEntity;

use Closure;
use Echore\NaturalEntity\option\MovementOptions;
use Echore\NaturalEntity\option\SelectTargetOptions;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\utils\ObjectSet;

interface INaturalEntity {

	public static function getDefaultMobType(): MobType;

	public function getMobType(): MobType;

	public function setMobType(MobType $mobType): void;

	public function setImmobile(bool $immobile = true): void;

	public function isImmobile(): bool;

	public function initStyle(): void;

	/**
	 * @return ObjectSet<Closure>
	 */
	public function getDisposeHooks(): ObjectSet;

	public function getMovementOptions(): MovementOptions;

	public function getSelectTargetOptions(): SelectTargetOptions;

	public function setItemInHand(Item $item): void;

	public function getPostAttackCoolDown(): int;

	public function setPostAttackCoolDown(int $coolDown): void;

	public function getFollowRange(): float;

	public function getTargetingRange(): float;

	public function getTargetSelector(): TargetSelector;

	public function getInstanceTarget(): ?Entity;

	public function getDestroyCycleHooks(): ObjectSet;

	public function setInstanceTarget(Entity $entity, int $initialInteresting): void;

	public function removeInstanceTarget(): void;

	public function getLastDamageCauseTick(): ?int;

	public function getLastDamageCauseByPlayerTick(): ?int;

	public function getLastDamageCauseByPlayer(): ?EntityDamageByEntityEvent;

	public function setLastDamageCauseByPlayer(?EntityDamageByEntityEvent $event, ?int $tick = null): void;

	public function getInteresting(): int;

	public function setInteresting(int $interesting): void;

	public function walkForward(bool $sprintSpeed = true): void;

	public function walkBackward(): void;

	public function walk(Vector2 $direction, bool $sprintSpeed): void;

	public function tryAttackEntity(Entity $entity): bool;

	public function getAdditionalAttackCoolDown(): int;

	public function postAttack(Entity $entity): EntityDamageByEntityEvent;

	public function getAttackRange(): float;

	public function setAttackRange(float $range): void;

	public function getPathProvider(): ?IPathProvider;

	public function setPathProvider(?IPathProvider $pathProvider): void;

	public function getTargetingTick(): int;

	public function walkTo(Vector3 $to, bool $sprintSpeed): ?Vector2;
}
