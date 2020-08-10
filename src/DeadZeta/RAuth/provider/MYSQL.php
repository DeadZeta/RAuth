<?php

declare(strict_types = 1);

namespace DeadZeta\RAuth\provider;

use pocketmine\utils\Config;
use pocketmine\Player;

class MYSQL {

	protected $plugin;
	public $db;
	protected $config;

	public function __construct($plugin, $config, $dump = null) {
		$this->plugin = $plugin;
		$this->config = $config;

		$mysql = $this->config->get("mysql_data");
		$this->db['provider_cache'] = new \mysqli($mysql['host'], $mysql['user'], $mysql['password'], $mysql['base'], $mysql['port']);

		if($this->db['provider_cache']->connect_error) {
			$this->plugin->getLogger()->critical("Провайдер MYSQL не может подключится к базе данных.");
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

        if($dump == null) {
        	$this->backupGenerate();
        }
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
		$password = $this->passwordHash($player, $password);
		$x = $player->getX();
		$y = $player->getY();
		$z = $player->getZ();
		$yaw = $player->getYaw();
		$pitch = $player->getPitch();
		$level = $player->getLevel()->getName();
		$position = "{$x}, {$y}, {$z}, {$yaw}, {$pitch}, {$level}";

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
		$x = $player->getX();
		$y = $player->getY();
		$z = $player->getZ();
		$yaw = $player->getYaw();
		$pitch = $player->getPitch();
		$level = $player->getLevel()->getName();
		$position = "{$x}, {$y}, {$z}, {$yaw}, {$pitch}, {$level}";

		if($this->getPlayer($player)) {
			$query = $this->db['provider_cache']->query("UPDATE `users` SET `ip`='{$player->getAddress()}', `position`='{$position}' WHERE `username`='{$username}'");
			
			if($query) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Update Player Password
	 *
	 * @param Player 
	 *
	 * @param string
	 *
	 * @return bool
	 */

	public function updatePassword(Player $player, string $password) : bool {
		$username = strtolower($player->getName());
		$password = $this->passwordHash($player, $password);

		if($this->getPlayer($player)) {
			$query = $this->db['provider_cache']->query("UPDATE `users` SET `password`='{$password}' WHERE `username`='{$username}'");
			
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
	 * @param Player
	 *
	 * @param string
	 *
	 * @return string|bool
	 */

	public function passwordHash(Player $player, string $password, string $type = null) {
		if($this->getPlayer($player)) {
			$player = $this->getPlayer($player);
			if(isset($type)) {
				return password_hash($password, PASSWORD_BCRYPT);
			}

			if(password_verify($password, $player['password'])) {
				return true;
			}
		}else{
			return password_hash($password, PASSWORD_BCRYPT);
		}

		return false;
	}

	/**
	 * Creating backups MYSQL
	 *
	 * P.S If your server does not have auto reboot, then backups will not be created.
	 *
	 * @return bool
	 */

	public function backupGenerate() : bool {
		if(!file_exists($this->plugin->getDataFolder() . "/backups/")) {
			mkdir($this->plugin->getDataFolder() . "/backups/");
		}

		if(file_exists($this->plugin->getDataFolder() . "/backups/" . date("Y-m-d") . ".sql")) {
			$this->plugin->getLogger()->warning("MYSQL Бекап уже есть.");
			return false;
		}

		$mysql = $this->config->get("mysql_data");

		exec("mysqldump --user=".$mysql['user']." --password=".$mysql['password']." ".$mysql['base']." users > ".$this->plugin->getDataFolder()."/backups/".date('Y-m-d').".sql");
		$this->plugin->getLogger()->warning("MYSQL Бекап успешно создан");
		return true;
	}
}

?>