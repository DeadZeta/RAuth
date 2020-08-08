<?php

declare(strict_types = 1);

namespace DeadZeta\RAuth;

use pocketmine\plugin\PluginBase;
use DeadZeta\RAuth\provider\Provider;
use DeadZeta\RAuth\libs\formapi\FormAPI;

class Main extends PluginBase {

	public function onEnable() {
		$this->registerPluginEvents();
	}

	/**
	 * Event Registration
	 * 
	 * @return void
	 */

	public function registerPluginEvents() : void {
		$provider = new Provider($this);
		$formapi = new FormAPI;
		$provider->createData();

		$config = $provider->getConfig();

		$this->getServer()->getPluginManager()->registerEvents(new EventListener($this, $formapi, $provider->provider, $config), $this);
	}
}

?>