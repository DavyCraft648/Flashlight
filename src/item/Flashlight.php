<?php

namespace DavyCraft648\Flashlight\item;

use pocketmine\item\ItemUseResult;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class Flashlight extends BaseFlashlight{
	public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems) : ItemUseResult{
		return $this->switchMode($player);
	}
}
