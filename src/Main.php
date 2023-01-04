<?php

namespace DavyCraft648\Flashlight;

use customiesdevs\customies\block\CustomiesBlockFactory;
use customiesdevs\customies\item\CustomiesItemFactory;
use DavyCraft648\Flashlight\block\Light;
use DavyCraft648\Flashlight\item\BaseFlashlight;
use DavyCraft648\Flashlight\item\Battery;
use DavyCraft648\Flashlight\item\DeadFlashlight;
use DavyCraft648\Flashlight\item\Flashlight;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\crafting\ExactRecipeIngredient;
use pocketmine\crafting\MetaWildcardRecipeIngredient;
use pocketmine\crafting\ShapedRecipe;
use pocketmine\crafting\ShapelessRecipe;
use pocketmine\crafting\ShapelessRecipeType;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\item\VanillaItems;
use pocketmine\resourcepacks\ZippedResourcePack;
use pocketmine\scheduler\ClosureTask;
use pocketmine\VersionInfo;
use Symfony\Component\Filesystem\Path;
use function array_merge;
use function strtolower;

final class Main extends \pocketmine\plugin\PluginBase{

	public const BEHAVIOUR_SURVIVAL = 0;
	public const BEHAVIOUR_HORROR = 1;
	public const BEHAVIOUR_HORROR_2 = 2;

	private static Main $instance;
	private int $behaviour = self::BEHAVIOUR_SURVIVAL;

	public static function getInstance() : Main{
		return self::$instance;
	}

	public function getBehaviour() : int{
		return $this->behaviour;
	}

	public function setBehaviour(int $behaviour) : Main{
		$this->behaviour = $behaviour;
		return $this;
	}

	protected function onLoad() : void{
		self::$instance = $this;
		$this->saveResource("FlashlightRP.mcpack");
		$newPack = new ZippedResourcePack(Path::join($this->getDataFolder(), "FlashlightRP.mcpack"));
		$rpManager = $this->getServer()->getResourcePackManager();
		$resourcePacks = new \ReflectionProperty($rpManager, "resourcePacks");
		$resourcePacks->setAccessible(true);
		$resourcePacks->setValue($rpManager, array_merge($resourcePacks->getValue($rpManager), [$newPack]));
		$uuidList = new \ReflectionProperty($rpManager, "uuidList");
		$uuidList->setAccessible(true);
		$uuidList->setValue($rpManager, $uuidList->getValue($rpManager) + [strtolower($newPack->getPackId()) => $newPack]);
		$serverForceResources = new \ReflectionProperty($rpManager, "serverForceResources");
		$serverForceResources->setAccessible(true);
		$serverForceResources->setValue($rpManager, true);

		$this->saveDefaultConfig();
		$this->behaviour = match ($this->getConfig()->get("behaviour-mode")) {
			"horror" => self::BEHAVIOUR_HORROR,
			"horror2" => self::BEHAVIOUR_HORROR_2,
			default => self::BEHAVIOUR_SURVIVAL
		};
		// don't hide item for horror2, may cause error in pm5
		CustomiesItemFactory::getInstance()->registerItem(Battery::class, "mj105:battery", "Battery");
		CustomiesItemFactory::getInstance()->registerItem(DeadFlashlight::class, "mj105:dead_flashlight", "Dead Flashlight");
		CustomiesItemFactory::getInstance()->registerItem(VersionInfo::BASE_VERSION[0] === "5" ? Flashlight::class : item\pm4\Flashlight::class, "mj105:flashlight", "Flashlight");
		if(VersionInfo::BASE_VERSION[0] === "4"){ // sadly, pm4 doesn't have light block
			CustomiesBlockFactory::getInstance()->registerBlock(fn(int $id) => new Light(new BlockIdentifier($id, 0), "Light", BlockBreakInfo::indestructible()), "flashlight:light");
		}
	}

	protected function onEnable() : void{
		$pluginManager = $this->getServer()->getPluginManager();
		$pluginManager->registerEvent(PlayerJoinEvent::class, \Closure::fromCallable([$this, "onPlayerJoin"]), EventPriority::MONITOR, $this);
		$pluginManager->registerEvent(PlayerMoveEvent::class, function(PlayerMoveEvent $event) : void{
			Session::get($event->getPlayer())->updateLight($event->getTo(), $event->getFrom());
		}, EventPriority::MONITOR, $this);
		$pluginManager->registerEvent(PlayerQuitEvent::class, function(PlayerQuitEvent $event) : void{
			Session::remove($event->getPlayer());
		}, EventPriority::NORMAL, $this);

		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
			Session::tickAll();
		}), 20 * 4);

		if($this->behaviour !== self::BEHAVIOUR_HORROR_2){
			$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() : void{
				VersionInfo::BASE_VERSION[0] === "5" ? $this->registerCraftingPM5() : $this->registerCraftingPM4();
			}), 2);
		}
	}

	private function registerCraftingPM4() : void{
		$this->getServer()->getCraftingManager()->registerShapelessRecipe(new ShapelessRecipe(
			[
				CustomiesItemFactory::getInstance()->get("mj105:dead_flashlight"),
				CustomiesItemFactory::getInstance()->get("mj105:battery")
			],
			[CustomiesItemFactory::getInstance()->get("mj105:flashlight")],
			ShapelessRecipeType::CRAFTING()
		));
		if($this->behaviour === self::BEHAVIOUR_HORROR){
			return;
		}
		$this->getServer()->getCraftingManager()->registerShapedRecipe(new ShapedRecipe(
			[
				"A",
				"B",
				"C"
			],
			[
				"A" => VanillaItems::REDSTONE_DUST(), // sadly, pm4 doesn't have copper ingot
				"B" => VanillaItems::PAPER(),
				"C" => VanillaItems::COAL()
			],
			[CustomiesItemFactory::getInstance()->get("mj105:battery")]
		));
		$this->getServer()->getCraftingManager()->registerShapedRecipe(new ShapedRecipe(
			[
				"AB ",
				"BC ",
				"  C"
			],
			[
				"A" => VanillaItems::FLINT_AND_STEEL(),
				"B" => VanillaItems::IRON_NUGGET(),
				"C" => VanillaItems::IRON_INGOT()
			],
			[CustomiesItemFactory::getInstance()->get("mj105:dead_flashlight")]
		));
	}

	private function registerCraftingPM5() : void{
		$this->getServer()->getCraftingManager()->registerShapelessRecipe(new ShapelessRecipe(
			[
				new MetaWildcardRecipeIngredient("mj105:dead_flashlight"),
				new MetaWildcardRecipeIngredient("mj105:battery")
			],
			[CustomiesItemFactory::getInstance()->get("mj105:flashlight")],
			ShapelessRecipeType::CRAFTING()
		));
		if($this->behaviour === self::BEHAVIOUR_HORROR){
			return;
		}
		$this->getServer()->getCraftingManager()->registerShapedRecipe(new ShapedRecipe(
			[
				"A",
				"B",
				"C"
			],
			[
				"A" => new ExactRecipeIngredient(VanillaItems::COPPER_INGOT()),
				"B" => new ExactRecipeIngredient(VanillaItems::PAPER()),
				"C" => new ExactRecipeIngredient(VanillaItems::COAL())
			],
			[CustomiesItemFactory::getInstance()->get("mj105:battery")]
		));
		$this->getServer()->getCraftingManager()->registerShapedRecipe(new ShapedRecipe(
			[
				"AB ",
				"BC ",
				"  C"
			],
			[
				"A" => new ExactRecipeIngredient(VanillaItems::FLINT_AND_STEEL()),
				"B" => new ExactRecipeIngredient(VanillaItems::IRON_NUGGET()),
				"C" => new ExactRecipeIngredient(VanillaItems::IRON_INGOT())
			],
			[CustomiesItemFactory::getInstance()->get("mj105:dead_flashlight")]
		));
	}

	public function onPlayerJoin(PlayerJoinEvent $event) : void{
		$player = $event->getPlayer();
		$inventory = $player->getInventory();
		$offHandInventory = $player->getOffHandInventory();
		$hand = $inventory->getItemInHand();
		$offHand = $offHandInventory->getItem(0);
		if($hand instanceof BaseFlashlight){
			Session::get($player)->checkFlashlight($hand);
		}
		if($offHand instanceof BaseFlashlight){
			Session::get($player)->checkFlashlight($offHand);
		}

		$inventory->getListeners()->add(CallbackInventoryListener::onAnyChange(static function(Inventory $inventory) use ($offHandInventory) : void{
			/** @var PlayerInventory $inventory */
			if($inventory->getItemInHand() instanceof BaseFlashlight){
				Session::get($inventory->getHolder())->checkFlashlight($inventory->getItemInHand());
			}elseif(!($offHandInventory->getItem(0) instanceof BaseFlashlight)){
				Session::get($inventory->getHolder())->disuseFlashlight();
			}
		}));
		$inventory->getHeldItemIndexChangeListeners()->add(static function(int $oldIndex) use ($offHandInventory, $inventory) : void{
			if($inventory->getItemInHand() instanceof BaseFlashlight){
				/** @noinspection PhpParamsInspection */
				Session::get($inventory->getHolder())->checkFlashlight($inventory->getItemInHand());
			}elseif(!($offHandInventory->getItem(0) instanceof BaseFlashlight)){
				/** @noinspection PhpParamsInspection */
				Session::get($inventory->getHolder())->disuseFlashlight();
			}
		});
		$offHandInventory->getListeners()->add(CallbackInventoryListener::onAnyChange(static function(Inventory $offHandInventory) use ($inventory) : void{
			/** @var PlayerOffHandInventory $offHandInventory */
			if($offHandInventory->getItem(0) instanceof BaseFlashlight){
				/** @noinspection PhpParamsInspection */
				Session::get($offHandInventory->getHolder())->checkFlashlight($offHandInventory->getItem(0));
			}elseif(!($inventory->getItemInHand() instanceof BaseFlashlight)){
				Session::get($offHandInventory->getHolder())->disuseFlashlight();
			}
		}));
	}
}
