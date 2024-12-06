<?php

declare(strict_types=1);

namespace Echore\NaturalEntity;

use pocketmine\entity\Entity;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\world\World;

class MobTypeWorldMapService {

	private static array $map = [];

	private static array $classMap = [];

	private static bool $initialized = false;

	public static function initialize(PluginBase $plugin): void {
		$manager = $plugin->getServer()->getPluginManager();
		$manager->registerEvent(
			PlayerJoinEvent::class,
			function(PlayerJoinEvent $event): void {
				self::onPlayerSpawn($event->getPlayer());
			},
			EventPriority::NORMAL,
			$plugin
		);

		$manager->registerEvent(
			PlayerQuitEvent::class,
			function(PlayerQuitEvent $event): void {
				self::onPlayerDespawn($event->getPlayer());
			},
			EventPriority::NORMAL,
			$plugin
		);

		$manager->registerEvent(
			PlayerJoinEvent::class,
			function(PlayerJoinEvent $event): void {
				self::onPlayerSpawn($event->getPlayer());
			},
			EventPriority::NORMAL,
			$plugin
		);

		$manager->registerEvent(
			EntityTeleportEvent::class,
			function(EntityTeleportEvent $event): void {
				self::onEntityTeleport($event);
			},
			EventPriority::NORMAL,
			$plugin
		);

		$manager->registerEvent(
			EntitySpawnEvent::class,
			function(EntitySpawnEvent $event): void {
				$entity = $event->getEntity();
				if (!$entity instanceof INaturalEntity) {
					return;
				}
				self::onEntitySpawn($entity);
			},
			EventPriority::NORMAL,
			$plugin
		);

		self::$initialized = true;
	}

	public static function onPlayerSpawn(Player $player): void {
		self::internalAdd($player->getWorld()->getFolderName(), MobType::FRIEND, $player);
	}

	private static function internalAdd(string $world, MobType $mobType, Entity $entity): void {
		self::$map[$world][$mobType->name][$entity->getId()] = $entity;
		self::$classMap[$world][$entity::class][$entity->getId()] = $entity;
	}

	public static function onPlayerDespawn(Player $player): void {
		self::internalRemove($player->getWorld()->getFolderName(), MobType::FRIEND, $player);
	}

	private static function internalRemove(string $world, MobType $mobType, Entity $entity): void {
		unset(self::$map[$world][$mobType->name][$entity->getId()]);
		unset(self::$classMap[$world][$entity::class][$entity->getId()]);
	}

	public static function onEntityTeleport(EntityTeleportEvent $event): void {
		$entity = $event->getEntity();
		$from = $event->getFrom()->getWorld()->getFolderName();
		$to = $event->getTo()->getWorld()->getFolderName();
		if ($from === $to) {
			return;
		}
		if ($entity instanceof INaturalEntity) {
			self::internalRemove($from, $entity::getDefaultMobType(), $entity);
			self::internalAdd($to, $entity::getDefaultMobType(), $entity);
		} elseif ($entity instanceof Player) {
			self::internalRemove($from, MobType::FRIEND, $entity);
			self::internalAdd($to, MobType::FRIEND, $entity);
		}
	}

	public static function onEntitySpawn(INaturalEntity&Entity $entity): void {
		self::internalAdd($entity->getWorld()->getFolderName(), $entity::getDefaultMobType(), $entity);
		$entity->getDisposeHooks()->add(fn() => self::onEntityDespawn($entity));
	}

	public static function onEntityDespawn(INaturalEntity&Entity $entity): void {
		self::internalRemove($entity->getWorld()->getFolderName(), $entity::getDefaultMobType(), $entity);
	}

	/**
	 * @return bool
	 */
	public static function isInitialized(): bool {
		return self::$initialized;
	}

	public static function getEntities(World $world, MobType $mobType): array {
		return self::$map[$world->getFolderName()][$mobType->name] ?? [];
	}

	public static function getEntitiesByClass(World $world, string $class): array {
		return self::$classMap[$world->getFolderName()][$class] ?? [];
	}

}
