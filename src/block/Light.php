<?php

namespace DavyCraft648\Flashlight\block;

use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

class Light extends \pocketmine\block\Transparent{
	public function getLightLevel() : int{
		return 12;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		return false;
	}
}
