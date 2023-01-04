<?php

namespace DavyCraft648\Flashlight\item;

use customiesdevs\customies\item\CreativeInventoryInfo;
use customiesdevs\customies\item\ItemComponentsTrait;
use pocketmine\item\ItemIdentifier;

class Battery extends \pocketmine\item\Item implements \customiesdevs\customies\item\ItemComponents{
	use ItemComponentsTrait;

	public function __construct(ItemIdentifier $identifier, string $name = "Unknown"){
		parent::__construct($identifier, $name);
		$this->initComponent("battery", new CreativeInventoryInfo(CreativeInventoryInfo::CATEGORY_ITEMS));
		$this->allowOffHand();
	}

	public function getMaxStackSize() : int{
		return 16;
	}
}
