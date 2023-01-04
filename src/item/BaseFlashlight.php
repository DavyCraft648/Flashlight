<?php

namespace DavyCraft648\Flashlight\item;

use customiesdevs\customies\item\CreativeInventoryInfo;
use customiesdevs\customies\item\ItemComponentsTrait;
use DavyCraft648\Flashlight\Main;
use DavyCraft648\Flashlight\Session;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemUseResult;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;

abstract class BaseFlashlight extends \pocketmine\item\Durable implements \customiesdevs\customies\item\ItemComponents{
	use ItemComponentsTrait;

	private bool $light = false;
	private int $remainingLightTime = 0;

	public function __construct(ItemIdentifier $identifier, string $name = "Unknown"){
		parent::__construct($identifier, $name);
		$this->setUnbreakable(Main::getInstance()->getBehaviour() === Main::BEHAVIOUR_HORROR_2);
		$this->initComponent("flashlight", new CreativeInventoryInfo(CreativeInventoryInfo::CATEGORY_EQUIPMENT));
		$this->allowOffHand();
	}

	public function getMaxDurability() : int{
		return Main::getInstance()->getBehaviour() === Main::BEHAVIOUR_SURVIVAL ? 5 : 3;
	}

	public function getMaxStackSize() : int{
		return 1;
	}

	public function isLight() : bool{
		return $this->light;
	}

	public function setLight(bool $light = true) : BaseFlashlight{
		$this->light = $light;
		return $this;
	}

	public function getRemainingLightTime() : int{
		return $this->remainingLightTime;
	}

	public function setRemainingLightTime(int $remainingLightTime) : BaseFlashlight{
		$this->remainingLightTime = $remainingLightTime;
		if($remainingLightTime <= 0){
			$this->remainingLightTime = 0;
			$this->setLight(false);
		}
		return $this;
	}

	public function switchMode(Player $player) : ItemUseResult{
		if($this->isLight()){
			$this->setLight(false);
			Session::get($player)->disuseFlashlight();
			return ItemUseResult::SUCCESS();
		}
		$this->setLight();
		if($this->remainingLightTime === 0){
			$this->applyDamage(1);
			$this->setRemainingLightTime(Main::getInstance()->getBehaviour() === Main::BEHAVIOUR_HORROR ? 3600 : 4200);
		}
		if(!$this->isBroken()){
			Session::get($player)->useFlashlight();
		}
		return ItemUseResult::SUCCESS();
	}

	protected function deserializeCompoundTag(CompoundTag $tag) : void{
		parent::deserializeCompoundTag($tag);
		$this->setRemainingLightTime($tag->getInt("RemainingLightTime", $this->remainingLightTime));
		$this->setLight($tag->getByte("isLight", $this->light ? 1 : 0) === 1);
	}

	protected function serializeCompoundTag(CompoundTag $tag) : void{
		parent::serializeCompoundTag($tag);
		$this->light ? $tag->setByte("isLight", 1) : $tag->removeTag("isLight");
		$this->remainingLightTime !== 0 ? $tag->setInt("RemainingLightTime", $this->remainingLightTime) : $tag->removeTag("RemainingLightTime");
	}
}
