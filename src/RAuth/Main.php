<?php

namespace RAuth;

use pocketmine\plugin\PluginBase;
use RAuth\provider\Provider;

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
		$provider->createData();

		$config = $provider->getConfig();

		$this->getServer()->getPluginManager()->registerEvents(new EventListener($this, $provider->provider, $config), $this);
	}
}

?>