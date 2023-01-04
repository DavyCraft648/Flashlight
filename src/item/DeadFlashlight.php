<?php

namespace DavyCraft648\Flashlight\item;

use customiesdevs\customies\item\CreativeInventoryInfo;
use customiesdevs\customies\item\ItemComponentsTrait;
use pocketmine\item\ItemIdentifier;

class DeadFlashlight extends \pocketmine\item\Item implements \customiesdevs\customies\item\ItemComponents{
	use ItemComponentsTrait;

	public function __construct(ItemIdentifier $identifier, string $name = "Unknown"){
		parent::__construct($identifier, $name);
		$this->initComponent("dead_flashlight", new CreativeInventoryInfo(CreativeInventoryInfo::CATEGORY_EQUIPMENT));
		$this->allowOffHand();
	}

	public function getMaxStackSize() : int{
		return 1;
	}
}
