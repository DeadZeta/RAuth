<?php

namespace RAuth\provider;

use pocketmine\utils\Config;
use pocketmine\Player;

class MYSQL {

	protected $plugin;
	public $db;
	protected $config;

	public function __construct($plugin, $config) {
		$this->plugin = $plugin;
		$this->config = $config;

		$mysql = $this->config->get("mysql_data");
		$this->db['provider_cache'] = new \mysqli($mysql['host'], $mysql['user'], $mysql['password'], $mysql['base'], $mysql['port']);

		if($this->db['provider_cache']->connect_error) {
			$this->plugin->getLogger()->critical("MYSQL Provider can't connect to db.");
			$this->plugin->getServer()->getPluginManager()->disablePlugin($this->plugin);
		}

		$table = "CREATE TABLE IF NOT EXISTS `users`(
			`username` VARCHAR(50) PRIMARY KEY NOT NULL,
			`password` TEXT NOT NULL,
			`ip` TEXT NOT NULL,
			`banned` INTEGER NOT NULL,
			`position` TEXT NOT NULL
			)
		";

        $this->db['provider_cache']->query($table);

        $this->backupGenerate();
	}

	/**
	 * Getting Player Information
	 *
	 * @param Player 
	 *
	 * @return mixed
	 */


	public function getPlayer(Player $player) {
		$username = strtolower($player->getName());
		$player = $this->db['provider_cache']->query("SELECT * FROM `users` WHERE `username`='{$username}'")->fetch_assoc();

		if($player) {
			return $player;
		}

		return false;
	}

	/**
	 * Player Registration
	 *
	 * @param PLayer
	 *
	 * @param string 
	 *
	 * @return bool
	 */

	public function registerPlayer(Player $player, string $password) : bool {
		$username = strtolower($player->getName());
		$password = md5(hash("ripemd128", $password));
		$x = (int)$player->getX();
		$y = (int)$player->getY();
		$z = (int)$player->getZ();
		$yaw = $player->getYaw();
		$pitch = $player->getPitch();
		$position = "{$x}, {$y}, {$x}, {$yaw}, {$pitch}";

		if(!$this->getPlayer($player)) {
			$query = $this->db['provider_cache']->query("INSERT INTO `users`(`username`, `password`, `ip`, `banned`, `position`) VALUES ('{$username}','{$password}','{$player->getAddress()}','0','{$position}')");
			
			if($query) {
				return true;
			}
		}

		return false;
	}

	/**
	 * IP and Position Player Update
	 *
	 * @param Player 
	 *
	 * @return bool
	 */

	public function updatePlayer(Player $player) : bool {
		$username = strtolower($player->getName());
		$x = (int)$player->getX();
		$y = (int)$player->getY();
		$z = (int)$player->getZ();
		$yaw = $player->getYaw();
		$pitch = $player->getPitch();
		$position = "{$x}, {$y}, {$x}, {$yaw}, {$pitch}";

		if($this->getPlayer($player)) {
			$query = $$this->db['provider_cache']->query("UPDATE `users` SET `ip`='{$player->getAddress()}', `position`='{$position}' WHERE `username`='{$username}'");
			
			if($query) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Player lock on time (minutes)
	 *
	 * @param Player 
	 *
	 * @param int
	 *
	 * @return bool
	 */

	public function banPlayer(Player $player, int $time) : bool {
		$username = strtolower($player->getName());
		$time = time()+$time;

		if($this->getPlayer($player)) {
			$query = $this->db['provider_cache']->query("UPDATE `users` SET `banned`='{$time}' WHERE `username`='{$username}'");
			
			if($query) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Creating a Password Hash
	 *
	 * @param string 
	 *
	 * @return string
	 */

	public function passwordHash(string $password) : mixed {
		return md5(hash("ripemd128", $password));
	}

	/**
	 * Creating backups MYSQL
	 *
	 * @return bool
	 */

	public function backupGenerate() : bool {
		if(!file_exists($this->plugin->getDataFolder() . "/backups/")) {
			mkdir($this->plugin->getDataFolder() . "/backups/");
		}

		if(file_exists($this->plugin->getDataFolder() . "/backups/" . date("Y-m-d") . ".sql")) {
			$this->plugin->getLogger()->warning("MYSQL Dump already created");
			return false;
		}

		$mysql = $this->config->get("mysql_data");

		exec("mysqldump --user=".$mysql['user']." --password=".$mysql['password']." ".$mysql['base']." users > ".$this->plugin->getDataFolder()."/backups/".date('Y-m-d').".sql");
		$this->plugin->getLogger()->warning("MYSQL Dump Created");
		return true;
	}
}

?>