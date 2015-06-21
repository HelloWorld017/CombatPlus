<?php

/*
 * Copyright (C) 2015  Khinenw <deu07115@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
 
namespace Khinenw\CombatShop;

use onebone\minecombat\grenade\FragmentationGrenade;
use onebone\minecombat\gun\Pistol;
use onebone\minecombat\MineCombat;
use pocketmine\block\Block;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class CombatShop extends PluginBase implements Listener{

	private $shops, $doubleTap, $itemPlaceList, $translations;

	public static $weapons, $genarades;

	const TYPE_GUN = 0;
	const TYPE_GRENADE = 1;
	const TYPE_AMMO = 2;
	
	public function onEnable(){
		@mkdir($this->getDataFolder());
		$this->shops = (new Config($this->getDataFolder()."combat_shops.yml", Config::YAML))->getAll();

		if(!file_exists($this->getDataFolder()."translation_en.yml")){
			(new Config($this->getDataFolder()."translation_en.yml", Config::YAML, yaml_parse(stream_get_contents($resource = $this->getResource("translation_en.yml")))))->save();
			@fclose($resource);
			$this->getLogger()->info(TextFormat::YELLOW."Extracted translation_en.yml!");
		}

		if(!file_exists($this->getDataFolder()."translation_ko.yml")){
			(new Config($this->getDataFolder()."translation_ko.yml", Config::YAML, yaml_parse(stream_get_contents($resource = $this->getResource("translation_ko.yml")))))->save();
			@fclose($resource);
			$this->getLogger()->info(TextFormat::YELLOW."Extracted translation_ko.yml!");
		}

		if(!file_exists($this->getDataFolder()."config.yml")){
			$prefs = (new Config($this->getDataFolder()."config.yml", Config::YAML, yaml_parse(stream_get_contents($resource = $this->getResource("config.yml")))));
			$prefs->save();
			$config = $prefs->getAll();
			@fclose($resource);
			$this->getLogger()->info(TextFormat::YELLOW."Extracted config.yml!");
		}else{
			$config = (new Config($this->getDataFolder()."config.yml", Config::YAML))->getAll();
		}
		$language = "en";

		if(isset($config["language"])){
			$language = $config["language"];
		}

		$this->translations = (new Config($this->getDataFolder()."translation_$language.yml", Config::YAML))->getAll();

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->doubleTap = array();
		$this->itemPlaceList = array();
		self::$weapons = array();

		//add default weapon
		$this->addWeapon(new \ReflectionClass(Pistol::class));

		$this->addGrenade(new \ReflectionClass(FragmentationGrenade::class));

		MineCombat::getInstance()->
	}

	public function onSignChange(SignChangeEvent $event){
		if(!$event->getPlayer()->hasPermission("combatshop.create")){
			return;
		}

		$text = $event->getLines();
		$prefix = strtoupper($text[0]);
		if($prefix !== "[WEAPON SHOP]"){
			return;
		}

		$name = $text[1];
		$price = $text[2];
		if(strtolower($name) === "ammo") {
			$ammo = $text[3];
			$this->registerShop(array(
				"name" => $name,
				"cost" => $price,
				"type" => self::TYPE_AMMO,
				"ammo" => $ammo
			), $event->getBlock(), $event->getPlayer());

			$event->setLine(0, $this->getTranslation("WEAPON_SHOP"));
			$event->setLine(1, TextFormat::GRAY.$this->getTranslation("AMMO_DISPLAY", $ammo));
			$event->setLine(2, $this->getTranslation("COST", $price));
			$event->setLine(3, "");
		}else{
			$isGun = true;
			if(isset(self::$genarades[$name])){
				$isGun = false;
			}elseif(isset(self::$weapons[$name])){

			}else{
				return;
			}

			$type = self::TYPE_GUN;

			if(!$isGun){
				$type = self::TYPE_GRENADE;
			}

			$this->registerShop(array(
				"name" => $name,
				"cost" => $price,
				"type" => $type
			), $event->getBlock(), $event->getPlayer());

			$class = self::$weapons[$name]->getMethod("getClass")->invoke(null);
			$classColor = MineCombat::getClassColor($class);

			$event->setLine(0, $this->getTranslation("WEAPON_SHOP"));
			$event->setLine(1, $classColor.$this->getTranslation("CLASS", $class));
			$event->setLine(2, $classColor.$this->getTranslation("GUN_DISPLAY", $name));
			$event->setLine(3, $this->getTranslation("COST", $price));
		}
	}

	public function onBlockBreak(BlockBreakEvent $event){
		$shopId = $this->getShopId($event->getBlock());

		if(isset($this->shops[$shopId])){
			if(!$event->getPlayer()->hasPermission("combatshop.destroy")){
				$event->getPlayer()->sendMessage(TextFormat::RED.$this->getTranslation("NO_PERMISSION_DESTROY"));
				$event->setCancelled(true);
				return;
			}

			if($this->shops[$shopId]["owner"] !== $event->getPlayer()->getName()){
				$event->getPlayer()->sendMessage(TextFormat::RED.$this->getTranslation("NOT_YOUR_SHOP"));
				$event->setCancelled(true);
				return;
			}

			unset($this->shops[$shopId]);
			$event->getPlayer()->sendMessage(TextFormat::AQUA.$this->getTranslation("SHOP_DESTROYED"));
		}
	}

	public function onPlayerInteract(PlayerInteractEvent $event){
		if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
			return;
		}

		$shopId = $this->getShopId($event->getBlock());

		if(!isset($this->shops[$shopId])){
			return;
		}

		if(!$event->getPlayer()->hasPermission("combatshop.use")){
			$event->getPlayer()->sendMessage(TextFormat::RED.$this->getTranslation("NO_PERMISSION_USE"));
			return;
		}

		if(MineCombat::getInstance()->getStatus() !== MineCombat::STAT_GAME_IN_PROGRESS){
			$event->getPlayer()->sendMessage(TextFormat::RED.$this->getTranslation("NOT_INGAME"));
			return;
		}

		if(!isset($this->doubleTap[$event->getPlayer()->getName()])){
			$this->setDoubleTap($event->getPlayer(), $shopId);
			return;
		}

		if($this->doubleTap[$event->getPlayer()->getName()]["id"] !== $shopId){
			$this->setDoubleTap($event->getPlayer(), $shopId);
			return;
		}

		if(($this->doubleTap[$event->getPlayer()->getName()]["time"] - microtime(true) >= 1.5)){
			$this->setDoubleTap($event->getPlayer(), $shopId);
			return;
		}

		$combat = MineCombat::getInstance();
		unset($this->doubleTap[$event->getPlayer()->getName()]);

		if($this->shops[$shopId]["type"] === self::TYPE_GUN){
			$itemData = explode(":", self::$weapons[$this->shops[$shopId]["name"]]->getMethod("getGunItem")->invoke(null));
			if($event->getPlayer()->getInventory()->contains(Item::get($itemData[0], $itemData[1]))){
				$event->getPlayer()->sendMessage(TextFormat::RED.$this->getTranslation("ALREADY_BOUGHT"));
			}
		}

		$returnVal = $combat->decreaseXP($event->getPlayer()->getName(), $this->shops[$shopId]["cost"]);
		if($returnVal){
			$event->getPlayer()->sendMessage(TextFormat::AQUA . $this->getTranslation("BOUGHT"));

			switch($this->shops[$shopId]["type"]){
				case self::TYPE_AMMO:
					$combat->getGunByPlayer($event->getPlayer())->addAmmo($this->shops[$shopId]["ammo"]);
					$event->getPlayer()->sendTip($this->getTranslation("BOUGHT_COLOR_NAME_MONEY",
						TextFormat::GRAY,
						$this->getTranslation("AMMO", $this->shops[$shopId]["ammo"]),
						$this->shops[$shopId]["cost"]
					));
					break;
				case self::TYPE_GUN:
					/*$gun = self::$weapons[$this->shops[$shopId]["name"]]->getMethod("getInstance")->invoke(null, $combat, $event->getPlayer(), [175, 175, 175]);

					if($gun === null){
						return;
					}

					$combat->giveGun($event->getPlayer()->getName(), $gun);*/
					$itemData = explode(":", self::$weapons[$this->shops[$shopId]["name"]]->getMethod("getGunItem")->invoke(null));
					$color = MineCombat::getClassColor(self::$weapons[$this->shops[$shopId]["name"]]->getMethod("getClass")->invoke(null));
					$event->getPlayer()->getInventory()->addItem(Item::get($itemData[0], $itemData[1]));

					$event->getPlayer()->sendTip($this->getTranslation("BOUGHT_COLOR_NAME_MONEY",
						$color,
						$this->shops[$shopId]["name"],
						$this->shops[$shopId]["cost"]
					));
					break;
				case self::TYPE_GRENADE:
					$grenade = self::$genarades[$this->shops[$shopId]["name"]]->getMethod("getInstance")->invoke(null, $combat, $event->getPlayer());

					if($grenade === null){
						return;
					}

					$combat->giveGrenade($event->getPlayer()->getName(), $grenade);

					$color = MineCombat::getClassColor($grenade->getClass());

					$event->getPlayer()->sendTip($this->getTranslation("BOUGHT_COLOR_NAME_MONEY",
						$color,
						$this->shops[$shopId]["name"],
						$this->shops[$shopId]["cost"]
					));
					break;
			}
		}else{
			$event->getPlayer()->sendMessage(TextFormat::RED . $this->getTranslation("NO_MONEY"));
		}

		if($event->getItem()->isPlaceable()){
			$this->itemPlaceList[$event->getPlayer()->getName()] = true;
		}

		$event->setCancelled(true);
	}

	/*public function getTeamColor($playerName, MineCombat $combat){
		switch($combat->getTeam($playerName)){
			case MineCombat::TEAM_RED: return [247, 2, 9];
			case MineCombat::TEAM_BLUE: return [40, 45, 208];
			default: return [175, 175, 175];
		}
	}*/

	public function onBlockPlace(BlockPlaceEvent $event){
		if(isset($this->itemPlaceList[$event->getPlayer()->getName()]) && $this->itemPlaceList[$event->getPlayer()->getName()]){
			$event->setCancelled(true);
			unset($this->itemPlaceList[$event->getPlayer()->getName()]);
		}
	}

	public function onDisable(){
		$config = new Config($this->getDataFolder()."combat_shops.yml", Config::YAML);
		$config->setAll($this->shops);
		$config->save();
	}

	public function setDoubleTap(Player $player, $shopId){
		$this->doubleTap[$player->getName()] = array(
			"id" => $shopId,
			"time" => microtime(true)
		);
		$player->sendMessage(TextFormat::YELLOW.$this->getTranslation("DOUBLE_TAP_TO_BUY"));
	}

	public function registerShop($shopData, Block $block, Player $owner){
		$shopId = $this->getShopId($block);
		$shopData["owner"] = $owner->getName();
		$owner->sendMessage(TextFormat::AQUA.$this->getTranslation("SHOP_CREATED"));
		$this->shops[$shopId] = $shopData;
	}

	public function getShopId(Block $block){
		return $block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getFolderName();
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		if($command->getName() === "weapons"){
			$sender->sendMessage(TextFormat::AQUA."==========".$this->getTranslation("WEAPONS")."==========");
			$sender->sendMessage(TextFormat::AQUA."==========".$this->getTranslation("GUNS")."==========");

			foreach(self::$weapons as $weaponName => $weapon){
				$class = $weapon->getMethod("getClass")->invoke(null);
				$sender->sendMessage(MineCombat::getClassColor($class).$this->getTranslation("CLASS", $class)." ".$weaponName);
			}

			$sender->sendMessage(TextFormat::AQUA."==========".$this->getTranslation("GRENADES")."==========");

			foreach(self::$genarades as $weaponName => $weapon){
				$class = $weapon->getMethod("getClass")->invoke(null);
				$sender->sendMessage(MineCombat::getClassColor($class).$this->getTranslation("CLASS", $class)." ".$weaponName);
			}
			return true;
		}
		return false;
	}

	public function getTranslation($key, ...$params){
		if(!isset($this->translations[$key])){
			return "UNDEFINED_TRANSLATION : $key";
		}
		$translation = $this->translations[$key];

		foreach($params as $key => $param){
			$translation = str_replace("%s".($key + 1), $param, $translation);
		}

		return $translation;
	}

	public function getShopList(){
		return $this->shops;
	}

	public function addWeapon(\ReflectionClass $gun){
		self::$weapons[$gun->getMethod("getName")->invoke(null)] = $gun;
	}

	public function addGrenade(\ReflectionClass $grenade){
		self::$genarades[$grenade->getMethod("getName")->invoke(null)] = $grenade;
	}
}
