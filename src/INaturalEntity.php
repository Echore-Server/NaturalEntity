<?php

namespace Echore\NaturalEntity;

use Echore\NaturalEntity\option\MovementOptions;
use Echore\NaturalEntity\option\SelectTargetOptions;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\math\Vector2;

interface INaturalEntity {

	public static function getMobType(): MobType;

	public function getMovementOptions(): MovementOptions;

	public function getSelectTargetOptions(): SelectTargetOptions;

	public function getPostAttackCoolDown(): int;

	public function setPostAttackCoolDown(int $coolDown): void;

	public function getFollowRange(): float;

	public function getTargetingRange(): float;

	public function getTargetSelector(): TargetSelector;

	public function getInstanceTarget(): ?Entity;

	public function setInstanceTarget(Entity $entity, int $initialInteresting): void;

	public function removeInstanceTarget(): void;

	public function getLastDamageCauseByPlayer(): ?EntityDamageByEntityEvent;

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
}
