<?php

declare(strict_types = 1);

namespace DeadZeta\RAuth;

use pocketmine\plugin\PluginBase;
use DeadZeta\RAuth\provider\Provider;

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

		if(!class_exists("DeadZeta\RAuth\libs\jojoe77777\FormAPI\FormAPI")) {
				$this->getLogger()->error("Attention! Use Compiled Poggit or GitHub Release!");
            	$this->getServer()->getPluginManager()->disablePlugin($this);
            	return;
		}

		$provider->createData();

		$config = $provider->getConfig();

		$this->getServer()->getPluginManager()->registerEvents(new EventListener($this, $provider->provider, $config), $this);
	}
}

?>