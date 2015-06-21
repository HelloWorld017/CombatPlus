<?php

namespace Khinenw\MoWeapons\task;


use Khinenw\MoWeapons\Bazooka;
use onebone\minecombat\MineCombat;
use pocketmine\level\Position;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\scheduler\PluginTask;

class BazookaShootTask extends PluginTask{

	private $motionX, $motionY, $motionZ, $x, $y, $z, $level, $size, $maxStep, $yOffset, $currentStep;

	private $gun;

	private $return = null;

	public function __construct(Bazooka $gun, Vector3 $vector, Position $position, $maxStep, $size, $yOffset){
		parent::__construct(MineCombat::getInstance());
		$this->return = ["final" => null];
		$this->motionX = $vector->getX();
		$this->motionY = $vector->getY();
		$this->motionZ = $vector->getZ();

		$this->x = $position->getX();
		$this->y = $position->getY() + $yOffset;
		$this->z = $position->getZ();

		$this->maxStep = $maxStep;
		$this->size = $size;
		$this->yOffset = $yOffset;
		$this->currentStep = null;

		$this->level = $position->getLevel();
		$this->gun = $gun;
	}

	public function onRun($currentTick){
		if($this->currentStep === null){
			$this->currentStep = $currentTick;
		}

		$this->x += $this->motionX;
		$this->y += $this->motionY;
		$this->z += $this->motionZ;

		$aabb = new AxisAlignedBB($this->x - $this->size, $this->y - $this->size, $this->z - $this->size, $this->x + $this->size, $this->y + $this->size, $this->z + $this->size);

		$currentPos = new Position($this->x, $this->y, $this->z, $this->level);
		$this->level->addParticle($this->gun->getParticle($currentPos, $this->gun->color[0], $this->gun->color[1], $this->gun->color[2]));

		$collidingEntities = $this->level->getCollidingEntities($aabb);

		foreach($collidingEntities as $entity){
			if($entity instanceof Player && $this->getOwner()->isEnemy($this->gun->getPlayer()->getName(), $entity->getName())){
				$this->return = $currentPos;
				$this->getHandler()->cancel();
				return;
			}
		}

		$block = $this->level->getBlock($currentPos);
		if($block->getId() !== 0 || $this->y < 0){
			$this->return = $currentPos;
			$this->getHandler()->cancel();
			return;
		}
	}

	public function onCancel(){
		$this->gun->processBazookaShoot($this->return);
	}
}