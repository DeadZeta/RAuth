<?php

namespace RAuth\provider;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

use RAuth\provider\MYSQL;
use RAuth\provider\SQLITE;

class Provider {

	private $plugin;

	public $provider = array();

	public function __construct(PluginBase $plugin) {
		$this->plugin = $plugin;
	}

	/**
	 * Creating Config
	 * 
	 * @return void
	 */

	public function createData() : void {
        $this->plugin->saveDefaultConfig();

        $this->loadProvider();
	}

	/**
	 * Get Config
	 * 
	 * @return mixed
	 */

	public function getConfig() : object {
		return $this->plugin->getConfig();
	}

	/**
	 * Provider Loader
	 * 
	 * @return void
	 */

	public function loadProvider() : void {
		$config = $this->getConfig();
		$provider = $config->get("provider");

		switch ($provider) {
			case 'SQLITE':
				$this->provider = new SQLITE($this->plugin, $config);
				break;
			
			case 'MYSQL':
				$this->provider = new MYSQL($this->plugin, $config);
				break;

			default:
				$this->plugin->getLogger()->critical("Provider not found.");
				$this->plugin->getServer()->getPluginManager()->disablePlugin($this->plugin);
				break;
		}
	}
}

?>