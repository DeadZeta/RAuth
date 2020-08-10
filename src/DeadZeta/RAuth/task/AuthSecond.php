<?php

namespace DeadZeta\RAuth\task;

use pocketmine\plugin\PluginBase;
use pocketmine\Player;
use pocketmine\scheduler\PluginTask;

class AuthSecond extends PluginTask {

	protected $plugin;

	protected $player;

	protected $time;

	public function __construct(PluginBase $plugin, Player $player, $data, $config) {
		parent::__construct($plugin);
		$this->plugin = $plugin;
		$this->player = $player;
		$this->data = $data;

		$settings = $config->get("settings");
		$this->time = $settings['timeout'];
	}

	public function onRun($currentTick) {
		$this->time--;

		if($this->data) {
			$this->player->sendTip("Авторизируйтесь, для продолжения.");
		}else{
			$this->player->sendTip("Зарегистрируйтесь, для продолжения.");
		}



		if($this->time == 1) {
			$this->player->close("", "Время авторизации вышло.");
			$this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
		}
	}
}

?>