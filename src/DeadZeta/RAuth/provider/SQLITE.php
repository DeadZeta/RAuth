<?php

declare(strict_types = 1);

namespace DeadZeta\RAuth\provider;

use pocketmine\utils\Config;
use pocketmine\Player;

class SQLITE {

	protected $plugin;
	public $db;
	protected $config;

	public function __construct($plugin, $config, $dump = null) {
		$this->plugin = $plugin;
		$this->config = $config;

		$this->db['provider_cache'] = new \SQLite3($this->plugin->getDataFolder()."users.db");

		$table = "CREATE TABLE IF NOT EXISTS `users`(
			`username` VARCHAR(100) PRIMARY KEY NOT NULL,
			`password` TEXT NOT NULL,
			`ip` TEXT NOT NULL,
			`banned` INTEGER NOT NULL,
			`position` TEXT NOT NULL
			)
		";

        $this->db['provider_cache']->exec($table);

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
		$player = $this->db['provider_cache']->query("SELECT * FROM `users` WHERE `username`='{$username}'")->fetchArray(SQLITE3_ASSOC);

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
		$password = $this->passwordHash($player, $password, "change");

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
		if($time != 0) {
			$time = time()+$time*60;
		}

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
	 * Creating backups SQLITE
	 *
	 * P.S If your server does not have auto reboot, then backups will not be created.
	 *
	 * @return bool
	 */

	public function backupGenerate() : bool {
		if(!file_exists($this->plugin->getDataFolder() . "/backups/")) {
			mkdir($this->plugin->getDataFolder() . "/backups/");
		}

		if(file_exists($this->plugin->getDataFolder() . "/backups/" . date("Y-m-d") . ".db")) {
			$this->plugin->getLogger()->warning("SQLITE Бекап уже есть");
			return false;
		}

		exec("cp " . $this->plugin->getDataFolder() . "users.db " . $this->plugin->getDataFolder() . "/backups/" . date("Y-m-d") . ".db");
		$this->plugin->getLogger()->warning("SQLITE Бекап успешно создан");
		return true;
	}
}

?>