<?php
/*   Copyright (C) 2015 Khinenw
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


namespace Khinenw\MoWeapons\task;

use onebone\minecombat\gun\FlameThrower;
use onebone\minecombat\MineCombat;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\network\Network;
use pocketmine\network\protocol\SetEntityDataPacket;
use pocketmine\Player;
use pocketmine\scheduler\PluginTask;
use pocketmine\Server;

class FlameTask extends PluginTask{
	private $player, $target, $duration, $currentDuration;

	public function __construct(Player $player, Player $target, MineCombat $plugin, $duration){
		parent::__construct($plugin);
		$this->player = $player;
		$this->target = $target;
		$this->duration = $duration;
		$this->currentDuration = 0;
	}

	public function onRun($currentTick){
		if(($this->currentDuration > $this->duration) || ($this->getOwner()->getStatus() !== MineCombat::STAT_GAME_IN_PROGRESS)){
			$this->getHandler()->cancel();
		}

		if($currentTick % 100 === 0){
			$this->target->attack(2, new EntityDamageByEntityEvent($this->player, $this->target, 15, 2, 0));
			$this->currentDuration++;
		}

	}

	public function onCancel(){
		$flags = (int) $this->target->getDataProperty(Player::DATA_FLAGS);
		$dataProperty = [Player::DATA_FLAGS => [Player::DATA_TYPE_BYTE, $flags]];

		$pk = new SetEntityDataPacket();
		$pk->eid = $this->target->getId();
		$pk->metadata = $dataProperty;

		Server::broadcastPacket($this->target->getLevel()->getPlayers(), $pk->setChannel(Network::CHANNEL_WORLD_EVENTS));

		unset(FlameThrower::$tasks[$this->target->getName()]);
	}
}