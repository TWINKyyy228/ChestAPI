<?php

namespace ChestAPI;

use pocketmine\{Player, Server};
use pocketmine\item\Item;
use pocketmine\nbt\tag\{CompoundTag, StringTag, IntTag};
use pocketmine\tile\{Tile, Chest};
use pocketmine\math\Vector3;
use pocketmine\inventory\Inventory;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\scheduler\CallbackTask;

class ChestAPI extends \pocketmine\plugin\PluginBase implements \pocketmine\event\Listener{

	public static $instance;
	public static $players_in_chest = [];

	const SINGLE_CHEST = 1;
	const DOUBLE_CHEST = 2;

	const SINGLE_CHEST_MAX_SIZE = 26;
	const DOUBLE_CHEST_MAX_SIZE = 53;

	const CHEST_HEIGHT = 2;

	public function onEnable(){
		self::$instance = $this;
		Server::getInstance()->getPluginManager()->registerEvents($this, $this);
	}

	public function openChest(Player $p, array $params, string $name = 'Сундук', int $data = ChestAPI::SINGLE_CHEST, $sound = true, $delete = true, string $message = ""){
		if(isset(ChestAPI::$players_in_chest[strtolower($p->getName())])) return false;
		Server::getInstance()->getPluginManager()->callEvent($ev = new ChestOpenEvent($p, $params, $name, $data, $message, ChestAPI::getInstance()));
		if($ev->isCancelled()) return false;
		if($sound) $p->getLevel()->addSound(new \pocketmine\level\sound\ClickSound($p));
		switch($data){
			case ChestAPI::SINGLE_CHEST:
				if(isset($params[ChestAPI::SINGLE_CHEST_MAX_SIZE + 1])) return ChestAPI::sendLog('Максимальное количество слотов в ОДИНАРНОМ сундуке - '.ChestAPI::SINGLE_CHEST_MAX_SIZE.'. Исправьте ошибку!');

				$pk = new UpdateBlockPacket();
				$pk->blockId = 54;
				$pk->blockData = 0;
				$pk->flags = 0b0000;
				$pk->x = $p->getX();                           // отправляем игроку блок сундука;
				$pk->y = $p->getY() - ChestAPI::CHEST_HEIGHT;
				$pk->z = $p->getZ();
				$p->dataPacket($pk);

				$chest = Tile::createTile("Chest", $p->getLevel(), new CompoundTag("", [new StringTag("id", Tile::CHEST), new StringTag("CustomName", $name), new IntTag("x", $p->x), new IntTag("y", $p->y - ChestAPI::CHEST_HEIGHT), new IntTag("z", $p->z)]));
				if($chest == null) return false;
				$inventory = $chest->getInventory();

				foreach($params as $slot => $item){
					if(strpos($slot, '-') !== false){
						$one = explode('-', $slot)[0];
						$two = explode('-', $slot)[1];
						for($i = $one; $i <= $two; ++$i) $inventory->setItem($i, $item);
					}elseif(strpos($slot, '|') !== false){
						$one = explode('|', $slot)[0];
						$two = explode('|', $slot)[1];
						while($one <= $two){
							$inventory->setItem($one, $item);
							$one += 9;
						}
					}else $inventory->setItem($slot, $item); // перебираем массив и расставляем вещи в инвентаре;
				}

				$p->sendMessage($message);
				$p->addWindow($inventory);

				ChestAPI::$players_in_chest[strtolower($p->getName())] = [
					'x' => $chest->x,
					'y' => $chest->y,               // сохраняем координаты тайлов в массиве, что бы в дальнейшем удалить честы;
					'z' => $chest->z,
					'inventory' => $inventory,
					'delete' => $delete,
					'data' => ChestAPI::SINGLE_CHEST
				];
			return ['inventory' => $inventory, 'tile' => $chest, 'player' => $p, 'params' => $params, 'data' => $data, 'customname' => $name, 'message' => $message]; // возвращаем массив с переменными для удобства и дальнейшего использования;
			case ChestAPI::DOUBLE_CHEST:
				$chest_1 = Tile::createTile("Chest", $p->getLevel(), new CompoundTag("", [new StringTag("id", Tile::CHEST), new StringTag("CustomName", $name), new IntTag("x", $p->x), new IntTag("y", $p->y - ChestAPI::CHEST_HEIGHT), new IntTag("z", $p->z)]));
				$chest_2 = Tile::createTile("Chest", $p->getLevel(), new CompoundTag("", [new StringTag("id", Tile::CHEST), new StringTag("CustomName", $name), new IntTag("x", $p->x), new IntTag("y", $p->y - ChestAPI::CHEST_HEIGHT), new IntTag("z", $p->z - 1)]));

				$chest_1->pairWith($chest_2); // пейрим сундуки между собой;
				$chest_2->pairWith($chest_1);
				if($chest_1 == null or $chest_2 == null) return false;
				$inventory = $chest_1->getDoubleInventory(); // получаем двойной инвентарь;

				ChestAPI::$players_in_chest[strtolower($p->getName())] = [
					'x' => $chest_1->x,
					'y' => $chest_1->y,
					'z' => $chest_1->z,
					'inventory' => $inventory,
					'delete' => $delete,
					'data' => ChestAPI::DOUBLE_CHEST // сохраняем координаты тайлов в массиве, что бы в дальнейшем удалить честы;
				];

				$pk = new UpdateBlockPacket();
				$pk->blockId = 54;
				$pk->blockData = 0;
				$pk->flags = 0b0000;
				$pk->x = $p->getX();
				$pk->y = $p->getY() - ChestAPI::CHEST_HEIGHT;
				$pk->z = $p->getZ();
				$p->dataPacket($pk);
				 								// отправляем игроку блоки сундуков;
				$pk = new UpdateBlockPacket();
				$pk->blockId = 54;
				$pk->blockData = 0;
				$pk->flags = 0b0000;
				$pk->x = $p->getX();
				$pk->y = $p->getY() - ChestAPI::CHEST_HEIGHT;
				$pk->z = $p->getZ() - 1;
				$p->dataPacket($pk);


				foreach($params as $slot => $item){
					if(strpos($slot, '-') !== false){
						$one = explode('-', $slot)[0];
						$two = explode('-', $slot)[1];
						for($i = $one; $i <= $two; ++$i) $inventory->setItem($i, $item);
					}elseif(strpos($slot, '|') !== false){
						$one = explode('|', $slot)[0];
						$two = explode('|', $slot)[1];
						while($one <= $two){
							$inventory->setItem($one, $item);
							$one += 9;
						}
					}else $inventory->setItem($slot, $item); // перебираем массив и расставляем вещи в инвентаре;
				}

				Server::getInstance()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "openDoubleInventory"], [$p, $inventory, $chest_1, $delete]), 4); // открытие двойного сундука, без таска не сработает! Если поставить задержку меньше 3 тиков, тоже не сработает.

			return ['inventory' => $inventory, 'tile_1' => $chest_1, 'tile_2' => $chest_2, 'player' => $p, 'params' => $params, 'data' => $data, 'customname' => $name, 'message' => $message]; // возвращаем массив с переменными для удобства и дальнейшего использования;
		}
	}
	public function deleteChest(Player $p) : bool{
		if(!isset(ChestAPI::$players_in_chest[strtolower($p->getName())])) return false;
		$pos = ChestAPI::$players_in_chest[strtolower($p->getName())];
		Server::getInstance()->getPluginManager()->callEvent($ev = new ChestDeleteEvent($p, $pos, ChestAPI::getInstance()));
		if($ev->isCancelled()) return false;
		if(($tile = $p->getLevel()->getTile(new Vector3($pos['x'], $pos['y'], $pos['z']))) instanceof Chest){
			switch($pos['data']){
				case ChestAPI::SINGLE_CHEST:

					// удаляем тайл;
					$tile->close();

					$pk = new UpdateBlockPacket();
					$pk->blockId = $p->getLevel()->getBlock(new Vector3($pos['x'], $pos['y'], $pos['z']))->getId();
					$pk->blockData = $p->getLevel()->getBlock(new Vector3($pos['x'], $pos['y'], $pos['z']))->getDamage();
					$pk->flags = 0b0000;
					$pk->x = $pos['x']; 				// отправляем игроку блоки, которые были на месте фейк сундуков;
					$pk->y = $pos['y'];
					$pk->z = $pos['z'];
					$p->dataPacket($pk);

				break;
				case ChestAPI::DOUBLE_CHEST:
					$chest_2 = $tile->getPair(); 		// получаем второй тайл;

					$pk = new UpdateBlockPacket();
					$pk->blockId = $p->getLevel()->getBlock(new Vector3($tile->x, $tile->y, $tile->z))->getId();
					$pk->blockData = $p->getLevel()->getBlock(new Vector3($tile->x, $tile->y, $tile->z))->getDamage();
					$pk->flags = 0b0000;
					$pk->x = $tile->x;
					$pk->y = $tile->y;
					$pk->z = $tile->z;
					$p->dataPacket($pk);
														// отправляем игроку блоки, которые были на месте фейк сундуков;
					$pk = new UpdateBlockPacket();
					$pk->blockId = $p->getLevel()->getBlock(new Vector3($chest_2->x, $chest_2->y, $chest_2->z))->getId();
					$pk->blockData = $p->getLevel()->getBlock(new Vector3($chest_2->x, $chest_2->y, $chest_2->z))->getDamage();
					$pk->flags = 0b0000;
					$pk->x = $chest_2->x;
					$pk->y = $chest_2->y;
					$pk->z = $chest_2->z;
					$p->dataPacket($pk);

					// удаляем тайлы;

					$tile->close();
					$chest_2->close();
				break;
			}
		}
		return true;
	}
	public function getItemsNames(Inventory $inventory) : array{
		$custom = [];
		for($i = 0; $i <= $inventory->getSize(); ++$i){
			$item = $inventory->getItem($i);
			if($item instanceof Item and $item->getId() !== 0){
				if($item->hasCustomName()) $custom[] = $item->getCustomName();
				else $custom[] = $item->getName();
			}
		}
		return $custom;
	}
	public function getItems(Inventory $inventory) : array{
		$items = [];
		for($i = 0; $i <= $inventory->getSize(); ++$i){
			$item = $inventory->getItem($i);
			if($item instanceof Item and $item->getId() !== 0) $items[] = $item;
		}
		return $items;
	}
	public function getItemsIds(Inventory $inventory) : array{
		$ids = [];
		for($i = 0; $i <= $inventory->getSize(); ++$i){
			$item = $inventory->getItem($i);
			if($item instanceof Item and $item->getId() !== 0) $ids[] = $item->getId();
		}
		return $ids;
	}
	public function getItemsDamages(Inventory $inventory) : array{
		$damages = [];
		for($i = 0; $i <= $inventory->getSize(); ++$i){
			$item = $inventory->getItem($i);
			if($item instanceof Item and $item->getId() !== 0) $damages[] = $item->getDamage();
		}
		return $damages;
	}
	public function setInventory(Inventory $inventory, array $params){
		foreach($params as $slot => $item){
			if(strpos($slot, '-') !== false){
				$one = explode('-', $slot)[0];
				$two = explode('-', $slot)[1];
				for($i = $one; $i <= $two; ++$i) $inventory->setItem($i, $item);
			}elseif(strpos($slot, '|') !== false){
				$one = explode('|', $slot)[0];
				$two = explode('|', $slot)[1];
				while($one <= $two){
					$inventory->setItem($one, $item);
					$one += 9;
				}
			}else $inventory->setItem($slot, $item); // перебираем массив и расставляем вещи в инвентаре;
		}
	}
	public function handlePlayerDropItem(\pocketmine\event\player\PlayerDropItemEvent $e){
		if(!isset(ChestAPI::$players_in_chest[strtolower($e->getPlayer()->getName())])) return;
		Server::getInstance()->getPluginManager()->callEvent($ev = new ChestDropEvent($e->getPlayer(), $e->getItem(), $e->getEntity(), ChestAPI::getInstance()));
		if($ev->isCancelled()) $e->setCancelled();
	}
	public function handleInventoryClose(\pocketmine\event\inventory\InventoryCloseEvent $e){
		if(isset(ChestAPI::$players_in_chest[strtolower($e->getPlayer()->getName())])){
			if(ChestAPI::$players_in_chest[strtolower($e->getPlayer()->getName())]['delete']) ChestAPI::getInstance()->deleteChest($e->getPlayer());
			unset(ChestAPI::$players_in_chest[strtolower($e->getPlayer()->getName())]);
			Server::getInstance()->getPluginManager()->callEvent($ev = new ChestCloseEvent($e->getPlayer(), ChestAPI::getInstance()));
		}
	}
	public function handleInventoryClick(\pocketmine\event\inventory\InventoryClickEvent $e){
		if(isset(ChestAPI::$players_in_chest[strtolower($e->getPlayer()->getName())])){
			Server::getInstance()->getPluginManager()->callEvent($ev = new ChestClickEvent($e->getPlayer(), $e->getItem(), $e->getSlot(), $e->getInventory(), ChestAPI::getInstance()));
			if($ev->isCancelled()) $e->setCancelled();
		}
	}
	public function handlePlayerQuit(\pocketmine\event\player\PlayerQuitEvent $e){
		if(isset(ChestAPI::$players_in_chest[strtolower($e->getPlayer()->getName())])){
			if(ChestAPI::$players_in_chest[strtolower($e->getPlayer()->getName())]['delete']) ChestAPI::getInstance()->deleteChest($e->getPlayer());
			unset(ChestAPI::$players_in_chest[strtolower($e->getPlayer()->getName())]);
			Server::getInstance()->getPluginManager()->callEvent($ev = new ChestCloseEvent($e->getPlayer(), ChestAPI::getInstance()));
		}
	}
	public function closeInventory(Player $p){
		$pk = new \pocketmine\network\mcpe\protocol\ContainerClosePacket();
		$pk->windowid = 10;
		$p->dataPacket($pk);
	}
	public function openDoubleInventory(Player $p, $inventory, $chest_1, $delete){
		if(!$p instanceof Player or !$inventory instanceof \pocketmine\inventory\DoubleChestInventory) return;
		$p->addWindow($inventory);
	}
	public static function sendLog(string $message){
		Server::getInstance()->getLogger()->critical('[ChestAPI] '.$message);
	}
	public static function getInstance() : ChestAPI{
		return self::$instance;
	}
}