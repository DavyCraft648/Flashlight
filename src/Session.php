<?php

namespace DavyCraft648\Flashlight;

use customiesdevs\customies\block\CustomiesBlockFactory;
use DavyCraft648\Flashlight\item\BaseFlashlight;
use pocketmine\block\Air;
use pocketmine\block\Block;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Liquid;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\math\VoxelRayTrace;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\VersionInfo;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use function cos;
use function deg2rad;
use function method_exists;
use function sin;
use function time;

class Session{

	/** @var Session[] */
	private static \WeakMap $data;

	public static function get(Player $player) : Session{
		self::$data ??= new \WeakMap();
		return self::$data[$player] ??= new Session($player);
	}

	public static function remove(Player $player) : void{
		if(isset(self::$data[$player])){
			unset(self::$data[$player]);
		}
	}

	public static function getDirectionVector(Location $location) : Vector3{
		$y = -sin(deg2rad($location->pitch));
		$xz = cos(deg2rad($location->pitch));
		$x = -$xz * sin(deg2rad($location->yaw));
		$z = $xz * cos(deg2rad($location->yaw));

		return (new Vector3($x, $y, $z))->normalize();
	}

	public static function tickAll() : void{
		foreach(self::$data ?? [] as $session){
			$session->tick();
		}
	}

	private bool $usingFlashlight = false;
	private int $lastChecked;

	public function __construct(private Player $player){
	}

	public function tick() : void{
		if(!$this->usingFlashlight || !$this->player->isConnected()){
			return;
		}

		$inventory = $this->player->getInventory();
		$hand = $inventory->getItemInHand();
		$offHandInventory = $this->player->getOffHandInventory();
		$offHand = $offHandInventory->getItem(0);
		$remainingHand = 0;
		$remainingOffHand = 0;
		if($hand instanceof BaseFlashlight && $hand->isLight()){
			$hand->setRemainingLightTime($remainingHand = ($hand->getRemainingLightTime() - (time() - $this->lastChecked)));
			$inventory->setItemInHand($hand);
			// $reflection = new \ReflectionMethod($inventory, "internalSetItem");
			// $reflection->setAccessible(true);
			// $reflection->invoke($inventory, $inventory->getHeldItemIndex(), $hand);
		}
		if($offHand instanceof BaseFlashlight && $offHand->isLight()){
			$offHand->setRemainingLightTime($remainingOffHand = ($offHand->getRemainingLightTime() - (time() - $this->lastChecked)));
			$offHandInventory->setItem(0, $offHand);
		}
		if($remainingHand <= 0 && $remainingOffHand <= 0){
			$this->disuseFlashlight();
		}
	}

	public function checkFlashlight(BaseFlashlight $item) : void{
		if($item->isLight() && $item->getRemainingLightTime() > 0){
			$this->useFlashlight();
		}
	}

	public function useFlashlight() : void{
		if($this->usingFlashlight){
			return;
		}

		$this->lastChecked = time();
		$this->usingFlashlight = true;
		$this->updateLight($this->player->getLocation(), $this->player->getLocation());
	}

	public function disuseFlashlight() : void{
		if(!$this->usingFlashlight){
			return;
		}

		$world = $this->player->getWorld();
		foreach(VoxelRayTrace::inDirection($this->player->getLocation()->add(0, $this->player->getEyeHeight(), 0), $this->player->getDirectionVector(), 17) as $vector3){
			$block = $world->getBlockAt($vector3->x, $vector3->y, $vector3->z);
			if($block->getLightFilter() === 15 || $block instanceof Liquid){
				break;
			}
			if(!($block instanceof Air) && VersionInfo::BASE_VERSION[0] === "4"){
				continue;
			}
			self::sendBlockLayers(new BlockPosition($vector3->x, $vector3->y, $vector3->z), $world, method_exists($world, "getBlockAtLayer") ? $world->getBlockAtLayer($vector3->x, $vector3->y, $vector3->z, 1) : VanillaBlocks::AIR());
		}
		$this->usingFlashlight = false;
	}

	public function updateLight(Location $newPos, Location $oldPos) : void{
		if(!$this->usingFlashlight){
			return;
		}

		/** @var Block[] $blocks */
		$blocks = [];
		$world = $this->player->getWorld();
		foreach(VoxelRayTrace::inDirection($oldPos->add(0, $this->player->getEyeHeight(), 0), self::getDirectionVector($oldPos), 16) as $vector3){
			$block = $world->getBlockAt($vector3->x, $vector3->y, $vector3->z);
			if($block->getLightFilter() === 15 || $block instanceof Liquid){ // Todo: Better underwater lighting
				break;
			}
			if(!($block instanceof Air) && VersionInfo::BASE_VERSION[0] === "4"){
				continue;
			}
			$blocks[World::blockHash($vector3->x, $vector3->y, $vector3->z)] = method_exists($world, "getBlockAtLayer") ? $world->getBlockAtLayer($vector3->x, $vector3->y, $vector3->z, 1) : VanillaBlocks::AIR();
		}
		foreach(VoxelRayTrace::inDirection($newPos->add(0, $this->player->getEyeHeight(), 0), self::getDirectionVector($newPos), 16) as $vector3){
			$block = $world->getBlockAt($vector3->x, $vector3->y, $vector3->z);
			if($block->getLightFilter() === 15 || $block instanceof Liquid){
				break;
			}
			if(!($block instanceof Air) && VersionInfo::BASE_VERSION[0] === "4"){
				continue;
			}
			$blocks[$hash = World::blockHash($vector3->x, $vector3->y, $vector3->z)] = isset($blocks[$hash]) ? ((VersionInfo::BASE_VERSION[0] === "5" ? $blocks[$hash]->getTypeId() : $blocks[$hash]->getId()) === (VersionInfo::BASE_VERSION[0] === "5" ? BlockTypeIds::AIR : BlockLegacyIds::AIR) ? (VersionInfo::BASE_VERSION[0] === "5" ? VanillaBlocks::LIGHT()->setLightLevel(12) : CustomiesBlockFactory::getInstance()->get("flashlight:light")) : $blocks[$hash]) : (VersionInfo::BASE_VERSION[0] === "5" ? VanillaBlocks::LIGHT()->setLightLevel(12) : CustomiesBlockFactory::getInstance()->get("flashlight:light"));
		}

		foreach($blocks as $hash => $block){
			World::getBlockXYZ($hash, $x, $y, $z);
			self::sendBlockLayers(new BlockPosition($x, $y, $z), $world, $block);
		}
	}

	private static function sendBlockLayers(BlockPosition $pos, World $world, Block $liquidLayer) : void{
		if(method_exists(RuntimeBlockMapping::class, "sortByProtocol")){
			foreach(RuntimeBlockMapping::sortByProtocol($world->getChunkPlayers($pos->getX() >> Chunk::COORD_BIT_SIZE, $pos->getZ() >> Chunk::COORD_BIT_SIZE)) as $mappingProtocol => $players){
				Server::getInstance()->broadcastPackets($players, [
					UpdateBlockPacket::create($pos, RuntimeBlockMapping::getInstance()->toRuntimeId(VersionInfo::BASE_VERSION[0] === "5" ? $liquidLayer->getStateId() : $liquidLayer->getFullId(), $mappingProtocol), UpdateBlockPacket::FLAG_NETWORK, UpdateBlockPacket::DATA_LAYER_LIQUID)
				]);
			}
			return;
		}
		Server::getInstance()->broadcastPackets($world->getChunkPlayers($pos->getX() >> Chunk::COORD_BIT_SIZE, $pos->getZ() >> Chunk::COORD_BIT_SIZE), [
			UpdateBlockPacket::create($pos, RuntimeBlockMapping::getInstance()->toRuntimeId(VersionInfo::BASE_VERSION[0] === "5" ? $liquidLayer->getStateId() : $liquidLayer->getFullId()), UpdateBlockPacket::FLAG_NETWORK, UpdateBlockPacket::DATA_LAYER_LIQUID)
		]);
	}

	public function isUsingFlashlight() : bool{
		return $this->usingFlashlight;
	}
}
