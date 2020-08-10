<?php

namespace DeadZeta\RAuth;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\level\Position;
use pocketmine\scheduler\PluginTask;

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
					$player->sendMessage("Вы уже зарегистрированны!");
					return true;
				}

				if(!isset($args[0], $args[1])) {
					$player->sendMessage("Используйте: /register <пароль> <повторпароля>.");
					return true;
				}

				if(strlen($args[0]) == 0) {
    				$player->sendMessage("Пароль не может быть пустым!");
    				return;
    			}

				if($args[0] != $args[1]) {
					$player->sendMessage("Пароли не совпадают.");
					return true;
				}

				if(!$this->provider->registerPlayer($player, $args[0])) {
					$player->sendMessage("Вы уже зарегистрированны!");
					return false;
				}

				$player->sendMessage("Вы успешно зарегистрированны.");

				$this->valid[$player->getName()] = false;
				$player->setImmobile(false);
				$this->plugin->getServer()->getScheduler()->cancelTask($this->task[$player->getName()]->getTaskId());
				unset($this->task[$player->getName()]);
				$this->provider->updatePlayer($player);
				return true;
				break;

			case '/login':
				if(!$this->valid[$player->getName()]) {
					$player->sendMessage("Вы уже авторизованны.");
					return true;
				}

				if(!isset($args[0])) {
					$player->sendMessage("Используйте: /login <пароль>.");
					return true;
				}

				$data = $this->provider->getPlayer($player);
				$settings = $this->config->get("settings");

				if(!$data) {
					$player->sendMessage("Вы не зарегистрированны!");
					return true;
				}

				if($this->wrong[$player->getName()] >= $settings['max_wrongs']) {
					$this->provider->banPlayer($player, $settings['max_wrongs_time']);
					$this->wrong[$player->getName()] = -1;
					$player->close("", "Вы забанены на {$settings['max_wrongs_time']} минут.");
				}

				if(!$this->provider->passwordHash($player, $args[0])) {
					$player->sendMessage("Пароли не совпадают.");
					$this->wrong[$player->getName()]++;
					return true;
				}

				$player->sendMessage("Вы успешно авторизованны.");

				$this->valid[$player->getName()] = false;
				$player->setImmobile(false);
				$this->plugin->getServer()->getScheduler()->cancelTask($this->task[$player->getName()]->getTaskId());
				unset($this->task[$player->getName()]);
				$this->provider->updatePlayer($player);
				return true;
				break;

			case '/achpw':
				if($this->valid[$player->getName()]) {
					$player->sendMessage("Вы не авторизованны.");
					return true;
				}

				$data = $this->provider->getPlayer($player);

				if(!isset($args[0], $args[1])) {
					$player->sendMessage("Используйте: /achpw <Старый пароль> <Новый пароль>");
					return true;
				}

				if(!$this->provider->passwordHash($player, $args[0])) {
					$player->sendMessage("Старый пароль не сопадает.");
					return true;
				}

				if(!$this->provider->updatePassword($player, $args[1])) {
					$player->sendMessage("Произошла ошибка смены пароля.");
					return true;
				}

				$player->sendMessage("Пароль успешно сменен. Не забудь его ;)");
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
            $this->plugin->getServer()->getIPBans()->addBan($player->getAddress(), "IP адрес забанен за подозрение в Атаке. БотФильтер");
		}

		if($data['banned'] > 0) {
			if($data['banned'] > time()) {
				$event->setKickMessage("Отказано в доступе.");
            	$event->setCancelled();
			}else{
				$this->provider->banPlayer($player, 0);
			}
		}

		foreach($this->plugin->getServer()->getOnlinePlayers() as $players){
			if($players !== $player and strtolower($player->getName()) === strtolower($players->getName())){
				$event->setCancelled();
				$player->close("", "С данного никнейма уже играют!");
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
				$player->sendMessage("Пожалуйста авторизируйтесь для игры на сервере.");
				$player->sendMessage("Напишите: /login <пароль>");
			}else{
			    $player->sendMessage("Пожалуйста зарегистрируйтесь для игры на сервере.");
				$player->sendMessage("Напишите: /register <пароль> <повторпароля>");
			}

			$this->task[$player->getName()] = new AuthSecond($this->plugin, $player, $data, $this->config);
			$this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($this->task[$player->getName()], 20);
		}

		return true;
	}

	public function onQuit(PlayerQuitEvent $event) : bool {
		$player = $event->getPlayer(); 

		if(isset($this->task[$player->getName()])) {
			$this->plugin->getServer()->getScheduler()->cancelTask($this->task[$player->getName()]->getTaskId());
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