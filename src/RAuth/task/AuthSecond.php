<?php

namespace RAuth\task;

use pocketmine\plugin\PluginBase;
use pocketmine\Player;
use pocketmine\scheduler\Task;

class AuthSecond extends Task {

	protected $plugin;

	protected $player;

	protected $time;

	public function __construct(PluginBase $plugin, Player $player, $data, $config) {
		$this->plugin = $plugin;
		$this->player = $player;
		$this->data = $data;

		$settings = $config->get("settings");
		$this->time = $settings['timeout'];
	}

	public function onRun(int $currentTick) {
		$this->time--;

		if($this->data) {
			$this->player->sendTip("Login to continue");
		}else{
			$this->player->sendTip("Register to continue");
		}



		if($this->time == 1) {
			$this->player->close("", "Login timed out.");
			$this->plugin->getScheduler()->cancelTask($this->getTaskId());
		}
	}
}

?>