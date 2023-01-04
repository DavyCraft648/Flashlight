<?php

namespace DavyCraft648\Flashlight\item\pm4;

use DavyCraft648\Flashlight\item\BaseFlashlight;
use pocketmine\item\ItemUseResult;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class Flashlight extends BaseFlashlight{
	public function onClickAir(Player $player, Vector3 $directionVector) : ItemUseResult{
		return $this->switchMode($player);
	}
}
