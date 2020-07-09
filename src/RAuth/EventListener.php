<?php

namespace RAuth;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\level\Position;
use pocketmine\scheduler\Task;

use RAuth\task\AuthSecond;

use pocketmine\Player;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerToggleSprintEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;

class EventListener implements Listener {

	protected $plugin;
	private $provider;
	private $config;

	protected $valid = array();
	protected $authorizations = array();
	protected $wrong = array();
	protected $task = array();

	public function __construct(PluginBase $plugin, $provider, $config) {
		$this->plugin = $plugin;
		$this->provider = $provider;
		$this->config = $config;
	}

	public function onPreCommand(PlayerCommandPreprocessEvent $event) {
		$player = $event->getPlayer();
		$message =  $event->getMessage();
		$message = explode(" ", $message);
		$command = $message[0];
		unset($message[0]);
		$args = $message;

		if($this->valid[$player->getName()] === true && $command{0} != "/" && $command != "/login" || $command != "/register") {
			$event->setCancelled();
		}

		switch ($command) {
			case '/register':
				if(!$this->valid[$player->getName()]) {
					$player->sendMessage("You are already registered.");
					return true;
				}

				if(!$args[1] || !$args[2]) {
					$player->sendMessage("Usage: /register <password> <repassword>.");
					return true;
				}

				if($args[1] != $args[2]) {
					$player->sendMessage("Password mismatch.");
					return true;
				}

				if(!$this->provider->registerPlayer($player, $args[1])) {
					$player->sendMessage("You are already registered!");
					return false;
				}

				$player->sendMessage("You are successfully registered.");

				$this->valid[$player->getName()] = false;
				$player->setImmobile(false);
				$this->plugin->getScheduler()->cancelTask($this->task[$player->getName()]->getTaskId());
				unset($this->task[$player->getName()]);
				$this->provider->updatePlayer($player);
				return true;
				break;

			case '/login':
				if(!$this->valid[$player->getName()]) {
					$player->sendMessage("You are already logged in.");
					return true;
				}

				if(!$args[1]) {
					$player->sendMessage("Usage: /login <password>.");
					return true;
				}

				$data = $this->provider->getPlayer($player);
				$settings = $this->config->get("settings");

				if(!$data) {
					$player->sendMessage("You are not registered yet!");
					return true;
				}

				if($this->wrong[$player->getName()] >= $settings['max_wrongs']) {
					$this->provider->banPlayer($player, $settings['max_wrongs_time']);
					$this->wrong[$player->getName()] = -1;
					$player->close("", "You were banned for {$settings['max_wrongs_time']} minutes.");
				}

				if($this->provider->passwordHash($args[1]) != $data['password']) {
					$player->sendMessage("Password mismatch.");
					$this->wrong[$player->getName()]++;
					return true;
				}

				$player->sendMessage("You are successfully logged.");

				$this->valid[$player->getName()] = false;
				$player->setImmobile(false);
				$this->plugin->getScheduler()->cancelTask($this->task[$player->getName()]->getTaskId());
				unset($this->task[$player->getName()]);
				$this->provider->updatePlayer($player);
				return true;
				break;
		}
	}

	public function onPreLogin(PlayerPreLoginEvent $event) : bool {
		$player = $event->getPlayer();

		$data = $this->provider->getPlayer($player);

		$settings = $this->config->get("settings");

		if(!isset($this->wrong[$player->getName()])) {
			$this->wrong[$player->getName()] = 0;
		}

		if(isset($this->authorizations[$player->getName()])) {
			$this->authorizations[$player->getName()]++;
		}else{
			$this->authorizations[$player->getName()] = 1;
		}

		if($this->authorizations[$player->getName()] >= $settings['max_joins']) {
			$event->setKickMessage("Access denied.");
            $event->setCancelled();
            $this->plugin->getServer()->getIPBans()->addBan($ip, "IP is banned for suspecting an attack on the server. BotFilter");
		}

		if($data['banned'] > 0) {
			if($data['banned'] > time()) {
				$event->setKickMessage("Access denied.");
            	$event->setCancelled();
			}else{
				$this->provider->banPlayer($player, 0);
			}
		}

		foreach($this->plugin->getServer()->getOnlinePlayers() as $players){
			if($players !== $player and strtolower($player->getName()) === strtolower($players->getName())){
				$event->setCancelled();
				$player->close("", "A player with this nickname is already playing on the server!");
			}
		}

		return true;
	}

	public function onJoin(PlayerJoinEvent $event) : bool {
		$player = $event->getPlayer();

		$data = $this->provider->getPlayer($player);

		$this->valid[$player->getName()] = false;

		$position = explode(", ", $data['position']);

		if(isset($position[2])) {
			$player->teleport(new Position((float)$position[0], (float)$position[1], (float)$position[2], $player->getLevel(), $position[3], $position[4]));
		}

		if($player->getAddress() != $data['ip']) {
			$this->valid[$player->getName()] = true;
			$player->setImmobile(true);

			$player->sendMessage("/register or /login");

			$this->task[$player->getName()] = new AuthSecond($this->plugin, $player, $data, $this->config);
			$this->plugin->getScheduler()->scheduleRepeatingTask($this->task[$player->getName()], 20);
		}

		return true;
	}

	public function onQuit(PlayerQuitEvent $event) : bool {
		$player = $event->getPlayer(); 

		if(isset($this->task[$player->getName()])) {
			$this->plugin->getScheduler()->cancelTask($this->task[$player->getName()]->getTaskId());
			unset($this->task[$player->getName()]);
		}

		if(!$this->valid[$player->getName()]) {
			$this->provider->updatePlayer($player);
		}

		unset($this->valid[$player->getName()]);
		return true;
	}

	public function onBreak(BlockBreakEvent $event) : bool {
		$player = $event->getPlayer();

		if($this->valid[$player->getName()]) {
			$event->setCancelled();
		}

		return true;
	}

	public function onPlace(BlockPlaceEvent $event) : bool {
		$player = $event->getPlayer();

		if($this->valid[$player->getName()]) {
			$event->setCancelled();
		}

		return true;
	}

	public function onSprint(PlayerToggleSprintEvent $event) : bool {
		$player = $event->getPlayer();

		if($this->valid[$player->getName()]) {
			$event->setCancelled();
		}

		return true;
	}

	public function onInteract(PlayerInteractEvent $event) : bool {
		$player = $event->getPlayer();

		if($this->valid[$player->getName()]) {
			$event->setCancelled();
		}

		return true;
	}

	public function onDamage(EntityDamageEvent $event) : bool {
		$entity = $event->getEntity();

		if($entity instanceof Player) {
			$player = $entity->getPlayer();

			if($this->valid[$player->getName()]) {
				$event->setCancelled();
			}
		}

		return true;
	}

	public function onDrop(PlayerDropItemEvent $event) : bool {
		$player = $event->getPlayer();

		if($this->valid[$player->getName()]) {
			$event->setCancelled();
		}

		return true;
	}

}

?>