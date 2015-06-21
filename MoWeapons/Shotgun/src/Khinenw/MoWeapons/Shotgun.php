<?php
/*
 *   Copyright (C) 2015 Khinenw
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Khinenw\MoWeapons;

use onebone\minecombat\gun\BaseGun;
use onebone\minecombat\MineCombat;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\network\Network;
use pocketmine\network\protocol\ExplodePacket;
use pocketmine\Player;
use pocketmine\Server;

class Shotgun extends BaseGun{
	private $lastShoot;

	public function __construct(MineCombat $plugin, Player $player, $color = [175, 175, 175]){
		parent::__construct($plugin, $player, 30, 30, $color);
	}

	public function onShoot(){
		$this->lastShoot = microtime(true);
		$pk = new ExplodePacket();
		$pk->x = $this->player->getX();
		$pk->y = $this->player->getY();
		$pk->z = $this->player->getZ();
		$pk->radius = 10;
		$pk->records = [new Vector3($this->player->getX(), $this->player->getY() + 1.62, $this->player->getZ())];
		Server::broadcastPacket($this->getPlayer()->getLevel()->getChunkPlayers($this->player->getX() >> 4, $this->player->getZ() >> 4), $pk->setChannel(Network::CHANNEL_BLOCKS));
	}

	public function canShoot(){
		$time = microtime(true);
		return ($time - $this->lastShoot > 0.5);
	}

	public function onShot(Player $target){
		if($this->getPlugin()->isEnemy($this->getPlayer()->getName(), $target->getName())){
			$distance = $this->getPlayer()->distance($target);

			$damage = $this->getDamage($distance);
			$target->attack($damage, new EntityDamageByEntityEvent($this->getPlayer(), $target, 15, $damage, 0));
		}
	}
	
	public static function getGunItem(){
		return Item::STICK.":0";
	}

	public function canGive(Player $player){
		return true;
	}

	public function getMagazineAmmo(){
		return 10;
	}

	public function getDamage($distance){
		return 7; // TODO: Damage by distance
	}

	public static function getName(){
		return "SPAS-12";
	}

	public static function getClass(){
		return "B";
	}

	public static function getInstance(MineCombat $plugin, Player $player, $color){
		return new self($plugin, $player, $color);
	}

}