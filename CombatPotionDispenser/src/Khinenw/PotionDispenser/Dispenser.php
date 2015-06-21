<?php

/*
 * Potion Dispenser, An potion dispenser for EconomyS.
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
 
namespace Khinenw\PotionDispenser;

use onebone\minecombat\MineCombat;
use pocketmine\block\Block;
use pocketmine\entity\Effect;
use pocketmine\entity\InstantEffect;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\TextContainer;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class Dispenser extends PluginBase implements Listener{

	private $dispensers, $doubleTap, $itemPlaceList, $translations;
	
	public $colorList = array(
		TextFormat::BLACK => array(0, 0, 0),
		TextFormat::DARK_BLUE => array(0, 0, 170),
		TextFormat::DARK_GREEN => array(0,170, 0),
		TextFormat::DARK_AQUA => array(0, 170, 170),
		TextFormat::DARK_RED => array(170, 0, 0),
		TextFormat::DARK_PURPLE => array(170, 0, 170),
		TextFormat::GOLD => array(255, 170, 0),
		TextFormat::GRAY => array(170, 170, 170),
		TextFormat::DARK_GRAY => array(55, 55, 55),
		TextFormat::BLUE => array(55, 55, 255),
		TextFormat::GREEN => array(55, 255, 55),
		TextFormat::AQUA => array(55, 255, 255),
		TextFormat::RED => array(255, 55, 55),
		TextFormat::LIGHT_PURPLE => array(255, 55, 255),
		TextFormat::YELLOW => array(255, 255, 55),
		TextFormat::WHITE => array(255, 255, 255));
	
	public function onEnable(){
		@mkdir($this->getDataFolder());
		$this->dispensers = (new Config($this->getDataFolder()."dispensers.yml", Config::YAML))->getAll();

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
	}

	public function onSignChange(SignChangeEvent $event){
		if(!$event->getPlayer()->hasPermission("potiondispenser.create")){
			return;
		}

		$text = $event->getLines();
		$prefix = strtoupper($text[0]);
		if($prefix !== "[DISPENSER]" && $prefix !== "[POTION SHOP]"){
			return;
		}

		$effect = explode(':', $text[1].$text[2]);

		if(count($effect) < 1){
			return;
		}

		if($effect[0] === "clear"){
			$this->registerDispenser(array(
				"name" => "clear",
				"cost" => (int) $text[3]
			), $event->getBlock(), $event->getPlayer());

			$event->setLine(0, $this->getTranslation("DISPENSER"));
			$event->setLine(1, TextFormat::GOLD.$this->getTranslation("POTION_NAME_NO_LEV", $this->getTranslation("CLEAR")));
			$event->setLine(2, "");
			$event->setLine(3, $this->getTranslation("DISPENSER_COST", ((int) $text[3])."XP"));

			return;
		}elseif(count($effect) < 2){
			return;
		}

		$effectInstance = Effect::getEffectByName($effect[0]);
		if($effectInstance === null){
			$effectInstance = Effect::getEffect($effect[0]);
			if($effectInstance === null){
				return;
			}
		}

		$effectId = $effectInstance->getId();
		$amplifier = (int) $effect[1];

		if($effectInstance instanceof InstantEffect){
			$duration = 1;
		}else{
			if(count($effect) < 3){
				return;
			}
			$duration = ((int) $effect[2]) * 20;
		}

		$this->registerDispenser(array(
			"name" => $effectId,
			"amplifier" => $amplifier,
			"duration" => $duration,
			"cost" => $text[3]
		), $event->getBlock(), $event->getPlayer());

		$event->setLine(0, $this->getTranslation("DISPENSER"));
		$color = $effectInstance->isBad() ? TextFormat::RED : TextFormat::AQUA;
		$event->setLine(1, $color.$this->getTranslation("POTION_NAME", $this->getServer()->getLanguage()->translate(new TextContainer($effectInstance->getName())), $amplifier + 1));

		if($effectInstance instanceof InstantEffect) {
			$event->setLine(2, "");
		}else{
			$event->setLine(2, $this->getTranslation("DURATION", (int) $effect[2]));
		}

		$price = (((int) $text[3])."XP");

		$event->setLine(3, $this->getTranslation("DISPENSER_COST", $price));
	}

	public function onBlockBreak(BlockBreakEvent $event){
		$dispenserId = $this->getDispenserId($event->getBlock());

		if(isset($this->dispensers[$dispenserId])){
			if(!$event->getPlayer()->hasPermission("potiondispenser.destroy")){
				$event->getPlayer()->sendMessage(TextFormat::RED.$this->getTranslation("NO_PERMISSION_DESTROY"));
				$event->setCancelled(true);
				return;
			}

			if($this->dispensers[$dispenserId]["owner"] !== $event->getPlayer()->getName()){
				$event->getPlayer()->sendMessage(TextFormat::RED.$this->getTranslation("NOT_YOUR_DISPENSER"));
				$event->setCancelled(true);
				return;
			}

			unset($this->dispensers[$dispenserId]);
			$event->getPlayer()->sendMessage(TextFormat::AQUA.$this->getTranslation("DISPENSER_DESTROYED"));
		}
	}

	public function onPlayerInteract(PlayerInteractEvent $event){
		if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
			return;
		}

		$dispenserId = $this->getDispenserId($event->getBlock());

		if(!isset($this->dispensers[$dispenserId])){
			return;
		}

		if(!$event->getPlayer()->hasPermission("potiondispenser.use")){
			$event->getPlayer()->sendMessage(TextFormat::RED.$this->getTranslation("NO_PERMISSION_USE"));
			return;
		}

		if(!isset($this->doubleTap[$event->getPlayer()->getName()])){
			$this->setDoubleTap($event->getPlayer(), $dispenserId);
			return;
		}

		if($this->doubleTap[$event->getPlayer()->getName()]["id"] !== $dispenserId){
			$this->setDoubleTap($event->getPlayer(), $dispenserId);
			return;
		}

		if(($this->doubleTap[$event->getPlayer()->getName()]["time"] - microtime(true) >= 1.5)){
			$this->setDoubleTap($event->getPlayer(), $dispenserId);
			return;
		}

		unset($this->doubleTap[$event->getPlayer()->getName()]);

		if($event->getPlayer()->hasEffect($this->dispensers[$dispenserId]["name"])){
			$event->getPlayer()->sendMessage(TextFormat::RED.$this->getTranslation("ALREADY_HAS_EFFECT"));
			return;
		}

		$api = MineCombat::getInstance();
		$returnVal = $api->decreaseXP($event->getPlayer()->getName(), $this->dispensers[$dispenserId]["cost"]);
		if($returnVal){

			$event->getPlayer()->sendMessage(TextFormat::AQUA.$this->getTranslation("BOUGHT"));

			if($this->dispensers[$dispenserId]["name"] === "clear"){
				$event->getPlayer()->removeAllEffects();
				$event->getPlayer()->sendTip($this->getTranslation("BOUGHT_COLOR_NAME_MONEY", 
						TextFormat::WHITE,
						$this->getTranslation("CLEAR"),
						$this->dispensers[$dispenserId]["cost"],
						"XP"
				));
			}else{
				$effect = Effect::getEffect($this->dispensers[$dispenserId]["name"]);
				$effect->setAmplifier($this->dispensers[$dispenserId]["amplifier"])->setDuration($this->dispensers[$dispenserId]["duration"]);
				$event->getPlayer()->addEffect($effect);
				$effectColor = $effect->getColor();
				$event->getPlayer()->sendTip($this->getTranslation("BOUGHT_COLOR_NAME_MONEY", 
						$this->getTextFormatFromColor($effectColor[0], $effectColor[1], $effectColor[2]),
						$this->getServer()->getLanguage()->translate(new TextContainer($effect->getName())),
						$this->dispensers[$dispenserId]["cost"],
						"XP"
				));
			}

		}else{
			$event->getPlayer()->sendMessage(TextFormat::RED.$this->getTranslation("NO_MONEY"));
		}

		if($event->getItem()->isPlaceable()){
			$this->itemPlaceList[$event->getPlayer()->getName()] = true;
		}

		$event->setCancelled(true);
	}

	public function onBlockPlace(BlockPlaceEvent $event){
		if(isset($this->itemPlaceList[$event->getPlayer()->getName()]) && $this->itemPlaceList[$event->getPlayer()->getName()]){
			$event->setCancelled(true);
			unset($this->itemPlaceList[$event->getPlayer()->getName()]);
		}
	}

	public function onDisable(){
		$config = new Config($this->getDataFolder()."dispensers.yml", Config::YAML);
		$config->setAll($this->dispensers);
		$config->save();
	}

	public function setDoubleTap(Player $player, $dispenserId){
		$this->doubleTap[$player->getName()] = array(
			"id" => $dispenserId,
			"time" => microtime(true)
		);
		$player->sendMessage(TextFormat::YELLOW.$this->getTranslation("DOUBLE_TAP_TO_BUY"));
	}

	public function registerDispenser($dispenserData, Block $block, Player $owner){
		$dispenserId = $this->getDispenserId($block);
		$dispenserData["owner"] = $owner->getName();
		$owner->sendMessage(TextFormat::AQUA.$this->getTranslation("DISPENSER_CREATED"));
		$this->dispensers[$dispenserId] = $dispenserData;
	}

	public function getDispenserId(Block $block){
		return $block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getFolderName();
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

	public function getDispenserList(){
		return $this->dispensers;
	}

	public function getTextFormatFromColor($r, $g, $b){
		$closest = null;
		$closestFormat = TextFormat::AQUA;
		foreach($this->colorList as $colorFormat => $colorValue){
			$currentVal = pow($r - $colorValue[0], 2) + pow($g - $colorValue[1], 2) + pow($b - $colorValue[2], 2);
			if($closest === null || $closest > $currentVal){
				$closest = $currentVal;
				$closestFormat = $colorFormat;
			}
		}
		
		return $closestFormat;
	}
}
