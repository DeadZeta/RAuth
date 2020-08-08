<?php

namespace DeadZeta\RAuth;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\level\Position;
use pocketmine\scheduler\Task;

use DeadZeta\RAuth\task\AuthSecond;

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
	protected $formapi;
	private $provider;
	private $config;

	protected $valid = array();
	protected $authorizations = array();
	protected $wrong = array();
	protected $task = array();

	public function __construct(PluginBase $plugin, $formapi, $provider, $config) {
		$this->plugin = $plugin;
		$this->formapi = $formapi;
		$this->provider = $provider;
		$this->config = $config;
	}

	public function onPreCommand(PlayerCommandPreprocessEvent $event) {
		$player = $event->getPlayer();
		$message =  $event->getMessage();
		$message = explode(" ", $message);
		$command = $message[0];
		unset($message[0]);
		$message = implode(" ", $message);
		$args = explode(" ", $message);

		if($command{0} != "/" && $command != "/login" || $command != "/register" || $command != "achpw") {
			if($this->valid[$player->getName()] === true) {
				$event->setCancelled();
			}
		}

		switch ($command) {
			case '/register':
				if(!$this->valid[$player->getName()]) {
					$player->sendMessage("You are already registered.");
					return true;
				}

				if(!isset($args[0], $args[1])) {
					$player->sendMessage("Usage: /register <password> <repassword>.");
					return true;
				}

				if(strlen($args[0]) == 0) {
    				$player->sendMessage("Password must not be empty!");
    				return;
    			}

				if($args[0] != $args[1]) {
					$player->sendMessage("Password mismatch.");
					return true;
				}

				if(!$this->provider->registerPlayer($player, $args[0])) {
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

				if(!isset($args[0])) {
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

				if(!$this->provider->passwordHash($player, $args[0])) {
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

			case '/achpw':
				if($this->valid[$player->getName()]) {
					$player->sendMessage("You are not authorized.");
					return true;
				}

				$data = $this->provider->getPlayer($player);

				if(!isset($args[0], $args[1])) {
					$player->sendMessage("Usage: /achpw <Old Password> <New Password>");
					return true;
				}

				if(!$this->provider->passwordHash($player, $args[0])) {
					$player->sendMessage("The old password does not match.");
					return true;
				}

				if(!$this->provider->updatePassword($player, $args[1])) {
					$player->sendMessage("An error has occurred.");
					return true;
				}

				$player->sendMessage("Password changed successfully. Do not forget it ;)");
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
            $this->plugin->getServer()->getIPBans()->addBan($player->getAddress(), "IP is banned for suspecting an attack on the server. BotFilter");
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

		$settings = $this->config->get("settings");

		if(isset($position[2]) && $this->plugin->getServer()->getLevelByName($position[5])) {
			$player->teleport(new Position((float)$position[0], (float)$position[1], (float)$position[2], $this->plugin->getServer()->getLevelByName($position[5]), $position[3], $position[4]));
		}else{
			$player->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn());
		}

		if($player->getAddress() != $data['ip']) {
			$this->valid[$player->getName()] = true;
			$player->setImmobile(true);

			if($this->provider->getPlayer($player)) {
				if($settings['form']) {
					$this->onModalAuth($player);
				}else{
					$player->sendMessage("Please Login to continue playing on the Server.");
					$player->sendMessage("To login, write: /login <password>");
				}
			}else{
				if($settings['form']) {
					$this->onModalRegister($player);
				}else{
					$player->sendMessage("Please Register to continue playing on the Server.");
					$player->sendMessage("To register, write: /register <password> <repassword>");
				}
			}

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

	public function onModalRegister(Player $player) {
		$form = $this->formapi->createCustomForm(function (Player $player, array $data = null){
    		if($data === null){
        		$this->onModalRegisterInfo($player, "Please Register.");
        		return;
    		}
    	
    		if(strlen($data[0]) == 0) {
    			$this->onModalRegisterInfo($player, "Password must not be empty!");
    			return;
    		}

    		if($data[0] != $data[1]) {
    			$this->onModalRegisterInfo($player, "Password mismatch.");
    			return;
    		}else{
    			$this->provider->registerPlayer($player, $data[0]);

    			$player->sendMessage("You are successfully registered.");

				$this->valid[$player->getName()] = false;
				$player->setImmobile(false);
				$this->plugin->getScheduler()->cancelTask($this->task[$player->getName()]->getTaskId());
				unset($this->task[$player->getName()]);
				$this->provider->updatePlayer($player);
				return;
    		}


		});

		$form->setTitle("Registration Form");
		
		$form->addInput("Password");
		$form->addInput("RePassword");
 		$form->sendToPlayer($player);
	}

	public function onModalAuth(Player $player) {
		$form = $this->formapi->createCustomForm(function (Player $player, array $data = null){
    		if($data === null){
        		$this->onModalAuthInfo($player, "Please Login.");
        		return;
    		}

    		$cache = $this->provider->getPlayer($player);
    		$settings = $this->config->get("settings");

    		if($this->wrong[$player->getName()] >= $settings['max_wrongs']) {
				$this->provider->banPlayer($player, $settings['max_wrongs_time']);
				$this->wrong[$player->getName()] = -1;
				$player->close("", "You were banned for {$settings['max_wrongs_time']} minutes.");
			}

    		if(!$this->provider->passwordHash($player, $data[0])) {
    			$this->onModalAuthInfo($player, "Password mismatch.");
    			$this->wrong[$player->getName()]++;
    			return;
    		}else{
    			$player->sendMessage("You are successfully authorized.");

				$this->valid[$player->getName()] = false;
				$player->setImmobile(false);
				$this->plugin->getScheduler()->cancelTask($this->task[$player->getName()]->getTaskId());
				unset($this->task[$player->getName()]);
				$this->provider->updatePlayer($player);
				return;
    		}


		});

		$form->setTitle("Authorization Form");
		
		$form->addInput("Password");
 		$form->sendToPlayer($player);
	}

	public function onModalRegisterInfo($player, $message) {
		$form = $this->formapi->createSimpleForm(function (Player $player, int $data = null){
    		if($data === null || isset($data)){
        		$this->onModalRegister($player);
        		return;
    		}

		});

		$form->setTitle("Information Form");
		$form->setContent($message);
		
		$form->addButton("Ok");
 		$form->sendToPlayer($player);
	}

	public function onModalAuthInfo($player, $message) {
		$form = $this->formapi->createSimpleForm(function (Player $player, int $data = null){
    		if($data === null || isset($data)){
        		$this->onModalAuth($player);
        		return;
    		}

		});

		$form->setTitle("Information Form");
		$form->setContent($message);
		
		$form->addButton("Ok");
 		$form->sendToPlayer($player);
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