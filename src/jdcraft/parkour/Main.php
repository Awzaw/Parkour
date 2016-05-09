<?php

/*
 * Parkour for PocketMine-MP
 * Copyright (C) 2016  JDCRAFT <jdcraftmcpe@gmail.com>
 * http://www.jdcraft.net
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace jdcraft\parkour;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\level\Position;
use pocketmine\utils\TextFormat;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerGameModeChangeEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\entity\EntityTeleportEvent;

class Main extends PluginBase implements Listener {

    /**
     * @var Config
     */
    private $parkour;
    private $sessions;
    private $signchanging;
    private $lang, $tag;

    public function onEnable() {
        if (!file_exists($this->getDataFolder())) {
            mkdir($this->getDataFolder());
        }

        $this->parkour = array();
        $this->sessions = [];
        $this->signchanging = false;

        $this->saveResource("language.properties");
        $this->saveResource("parkour.yml");
        $this->lang = new Config($this->getDataFolder() . "language.properties", Config::PROPERTIES);
        $this->tag = new Config($this->getDataFolder() . "parkour.yml", Config::YAML);

        $parkourYml = new Config($this->getDataFolder() . "ParkourData.yml", Config::YAML);
        $this->parkour = $parkourYml->getAll();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onDisable() {
        $this->saveParkours();
    }

    public function onSignChange(SignChangeEvent $event) {

        if (($data = $this->checkTag($event->getLine(0), $event->getLine(1))) !== false) {
            $player = $event->getPlayer();
            if (!$player->hasPermission("parkour.create")) {
                $player->sendMessage($this->getMessage("no-permission-create"));
                return;
            }

            //attempted debounce, timings fix... doesn't stop sign change glitches
//            if ($this->signchanging)
//                return;

            $block = $event->getBlock();
            $parkourname = $event->getLine(2);

            switch ($event->getLine(1)) {
                case "start":

                    $idamount = explode(':', $event->getLine(3));
                    $id = 57;
                    $idstring = "";
                    $amount = 64;

                    //var_dump($idamount);
                    //If no amount given, set to 64
                    if (empty($idamount[1]) || $idamount[1] === 0)
                        $amount = 64; //Put these in config.yml
                    else
                        $amount = $idamount[1]; //$amount could still be string...


                        
//If no reward given, set to 57
                    if (empty($idamount[0]) || $idamount[0] === 0)
                        $id = 57; //Put these in config.yml
                    else
                        $id = $idamount[0]; //$id could be string or int still...




                        
// Check if the string reward is a valid block... not working?

                    if (!is_numeric($id)) {// if ID is a string
                        $rewardblock = Item::fromString($id);
                        if (!$rewardblock instanceof ItemBlock) {
                            $player->sendMessage($this->getMessage("reward-invalid"));
                            $event->setCancelled(true);
                            return;
                        }
                        $idstring = $id;
                        $id = $rewardblock->getId();
                    } else {// if ID is an INT
                        if ($id === 0)
                            $id = 57;
                        $rewardblock = Item::get($id);

                        if (!$rewardblock instanceof ItemBlock) {
                            $player->sendMessage($this->getMessage("reward-invalid"));
                            return;
                        }
                        $idstring = Item::get($id)->getName();
                    }

//Check if amount is valid

                    if (!is_numeric($amount)) {
                        $amount = 64; //put this in config instead
                    }

                    //Check Target Parkout is valid
                    if (trim($event->getLine(2)) === "") {
                        $player->sendMessage($this->getMessage("no-target-parkour"));
                        return;
                    }

                    //Check if there's already START Sign
                    //var_dump($this->parkour);

                    foreach (array_keys($this->parkour) as $d) {

                        //echo("Checking the parkours before making a start sign\n");
                        var_dump($d);
                        //echo($this->parkour[$d]["type"] . "\n");
                        //echo("Is " . $this->parkour[$d]["name"] . " === " . $parkourname . "\n");

                        if ($this->parkour[$d]["type"] === 0 && ($this->parkour[$d]["name"] === $parkourname)) {
                            $player->sendMessage($this->getMessage("start-exists") . " " . $parkourname);
                            return;
                        }
                    }

                    //echo("Got here, now make the parkour " . $event->getLine(2) . "\n");

                    $this->parkour[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()] = array(
                        "type" => 0,
                        "reward" => ($id . ":" . $amount),
                        "name" => ($event->getLine(2)),
                        "x" => $player->x,
                        "y" => $player->y,
                        "z" => $player->z,
                        "level" => $player->getLevel()->getFolderName(),
                        "top" => array(),
                        "maker" => $player->getName()
                    );
                    //$mu = EconomyAPI::getInstance()->getMonetaryUnit();
                    $mu = "$";

                    //Check if there's a FINISH Sign
                    foreach (array_keys($this->parkour) as $e) {
                        var_dump($e);

                        if ($this->parkour[$e]["type"] === 1 && ($this->parkour[$e]["name"] === $parkourname)) {
                            $player->sendMessage($this->getMessage("start-created-finish"));
                            break;
                        }
                        $player->sendMessage($this->getMessage("start-created-nofinish"));
                    }


                    //Write the START SIGN
                    //$this->signchanging = true;//doubt this does anything... maybe just a PM timings glitch?
                    $event->setLine(0, TextFormat::GREEN . str_replace("%MONETARY_UNIT%", $mu, $data[0]));
                    $event->setLine(1, TextFormat::WHITE . str_replace("%MONETARY_UNIT%", $mu, $data[1]));
                    $event->setLine(2, TextFormat::AQUA . str_replace(["%2", "%MONETARY_UNIT%"], [$event->getLine(2)], $data[2]));
                    $event->setLine(3, TextFormat::GOLD . str_replace(["%1"], $idstring . ' x ' . $amount, $data[3]));
                    //$this->signchanging = false;

                    $this->saveParkours();
                    //echo("saved");


                    return;


                case ("finish"):

                    if (trim($parkourname) === "") {
                        $player->sendMessage($this->getMessage("no-parkour-name"));
                        return;
                    }
                    if (strpos($parkourname, ":")) {
                        $player->sendMessage($this->getMessage("invalid-parkour-name"));
                        return;
                    }

                    //Check if there's already a FINISH Sign

                    foreach (array_keys($this->parkour) as $d) {
                        //var_dump($d);

                        if ($this->parkour[$d]["type"] === 1 && ($this->parkour[$d]["name"] === $parkourname)) {
                            $player->sendMessage($this->getMessage("finish-exists") . " " . $parkourname);
                            return;
                        }
                    }


                    //Message  depends if there is a start sign

                    foreach (array_keys($this->parkour) as $d) {
                        if ($this->parkour[$d]["type"] === 0 && $this->parkour[$d]["name"] === $parkourname) {
                            $player->sendMessage($this->getMessage("start-exists"));
                            break;
                        }
                        $player->sendMessage($this->getMessage("no-start"));
                    }


                    $this->parkour[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()] = array(
                        "name" => $event->getLine(2),
                        "type" => 1
                    );

                    //$this->signchanging = true;
                    $event->setLine(0, TextFormat::RED . $data[0]);
                    $event->setLine(1, TextFormat::WHITE . $data[1]);
                    $event->setLine(2, TextFormat::GOLD . str_replace("%1", $event->getLine(2), $data[2]));
                    $event->setLine(3, TextFormat::AQUA . str_replace("%1", $event->getLine(3), $data[3]));
                    //$this->signchanging = false;

                    $this->saveParkours();
            }
        }
    }

    public function onInteract(PlayerInteractEvent $event) {

        if ($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
            return;
        }

        $block = $event->getBlock();
        if (!($block->getID() == 63 or $block->getID() == 68 or $block->getID() == 323))
            return;

        $sender = $event->getPlayer();


//If it's a parkour block that was clicked
        if (isset($this->parkour[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()])) {
            $parkour = $this->parkour[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()];

            if ($parkour["type"] === 1) {

                // Player clicked a FINISH sign
                $parkourname = $this->parkour[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()]["name"];


                //Check there is a Start Sign, if not warn and exit
                $count = array();
                foreach ($this->parkour as $p) {
                    @$count[$p['name']] ++;
                }
                $howmanyparkour = $count[$parkourname];

                if ($howmanyparkour < 2) {
                    $sender->sendMessage($this->getMessage("no-start-sign") . " " . $parkourname);
                    return;
                }

                //a finish sign was clicked... but no session exists
                if (!isset($this->sessions[$sender->getName()])) {
                    $sender->sendMessage($this->getMessage("click-start") . " " . $parkourname);
                    return;
                }

                if ($parkourname == $this->sessions[$sender->getName()]["parkour"]) {
                    // CONGRATULATIONS!! a session exists and it's the same name as the finish pk sign you clicked
                    //Get the time elapsed
                    $endtime = time();
                    $starttime = $this->sessions[$sender->getName()]["start"];
                    $timespent = $this->timeSpent($starttime, $endtime);

                    //Get the reward from the Corresponding Start Sign
                    foreach (array_keys($this->parkour) as $p) {

                        if ($this->parkour[$p]["name"] == $parkourname && $this->parkour[$p]["type"] == 0) {
                            $reward = $this->parkour[$p]["reward"];

                            //Check if Top Score
                            if (!isset($this->parkour[$p]["top"]["besttime"]) || ($endtime - $starttime) < $this->parkour[$p]["top"]["besttime"]) {
                                $this->parkour[$p]["top"] = array("player" => ($sender->getName()), "besttime" => ($endtime - $starttime));
                                $sender->sendMessage($this->getMessage("best-score") . " " . $timespent);
                            }
                        }
                    }

                    if (!isset($reward)) {//Set default reward
                        $reward = "64:16";
                    }

                    $idamount = explode(':', $reward);
                    $id = $idamount[0];
                    $amount = 0;
                    if (!isset($idamount[1])) {
                        $amount = 64;
                    } else {
                        $amount = $idamount[1];
                    }

                    //var_dump($idamount);
                    // Convert string rewards to ID
                    if (!is_numeric($id)) {
                        $item = Item::fromString($id);

                        if ($item instanceof ItemBlock) {
                            $id = $item->getId();
                        }
                    }

                    //echo ("Giving ID " . $id . ", Amount " . $amount . "\n");

                    $sender->getInventory()->addItem(new Item($id, 0, $amount));
                    $sender->sendMessage($this->getMessage("parkour-completed") . " " . $parkourname . " for " . $reward . " in " . $timespent);

                    unset($this->sessions[$sender->getName()]);

                    return;
                }

                // a session exists and it's not the pk the user is currently playing
                if ($parkourname != $this->sessions[$sender->getName()]["parkour"]) {
                    $sender->sendMessage($this->getMessage("already-playing") . " " . $this->sessions[$sender->getName()]["parkour"]);
                    return;
                }
            } else {

//User Clicked a Start Sign
//
//If there's no end sign for the start.. message and return

                $parkourname = $this->parkour[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()]["name"];

                $count = array();
                foreach ($this->parkour as $p) {
                    @$count[$p['name']] ++;
                }
                $howmanyparkour = $count[$parkourname];

                if ($howmanyparkour < 2) {
                    $sender->sendMessage($this->getMessage("no-finish-sign") . " " . $parkourname);
                    return;
                }

                //START the PARKOUR!


                if (!isset($this->sessions[$sender->getName()])) {
                    $parkourplaying = $parkour["name"];
                    $maker = $parkour["maker"];
                    $sender->setGamemode(0);
                    $sender->sendMessage($this->getMessage("parkour-started") . " " . $parkourplaying . " by " . $maker);

                    //If a best time is set, display it
                    if (isset($parkour["top"]["besttime"])) {
                        $bestplayer = $parkour["top"]["player"];
                        $besttime = $this->timeSpent(0, $parkour["top"]["besttime"]);

                        $sender->sendMessage($this->getMessage("best-parkour") . " " . $besttime . " by " . $bestplayer);
                    }

                    $this->sessions[$sender->getName()] = array("parkour" => $parkourplaying, "start" => time());
                } else {
                    $sender->sendMessage($this->getMessage("already-playing") . " " . $this->sessions[$sender->getName()]["parkour"]);
                }
            }
        }
    }

    public function onBlockBreak(BlockBreakEvent $event) {
        $block = $event->getBlock();

        if (!($block->getID() == 63 or $block->getID() == 68 or $block->getID() == 323))
            return;

        if (isset($this->parkour[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()])) {
            $player = $event->getPlayer();
            $parkour = $this->parkour[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()];

            $parkourname = $parkour["name"];
            $parkourtype = $parkour["type"];

            //Delete this parkour marker 
            //MESSY PERMISSIONS CHECKING... someone else could do this so much better!
            //
            //LOOP THROUGH ALL PARKOURS
            foreach ($this->parkour as $subKey => $subArray) {

                if ($parkourtype === 1 && $subArray["name"] == $parkourname && ($subArray["type"] === 0)) {
                    //we are breaking A Finish Sign so deal with the START sign first to check maker

                    $maker = $subArray["maker"];

                    if ($maker === $player->getName()) {

                        if (!$player->hasPermission("parkour.create")) {
                            $player->sendMessage($this->getMessage("no-permission-break"));
                            $event->setCancelled(true);
                            return;
                        }
                    } else {// If it's someone elses parkour...
                        if (!$player->hasPermission("parkour")) {
                            $player->sendMessage($this->getMessage("no-permission-break-others"));
                            $event->setCancelled(true);
                            return;
                        }
                    }

                    //set to null first - unset() seems to be delayed sometimes?

                    $this->parkour[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()] = null;
                    unset($this->parkour[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()]);

                    $this->parkour[$subKey]["top"] = null;
                    unset($this->parkour[$subKey]["top"]); // And clear top scores when the FINISH sign is broken
                }


                if ($parkourtype === 0 && $subArray["name"] == $parkourname && $subArray["type"] === 0) {
                    //We are breaking a Start Parkour, so get the maker for it

                    $maker = $subArray["maker"];

                    if ($maker === $player->getName()) {

                        if (!$player->hasPermission("parkour.create")) {
                            $player->sendMessage($this->getMessage("no-permission-break"));
                            $event->setCancelled(true);
                            return;
                        }
                    } else {// If it's someone elses parkour...
                        if (!$player->hasPermission("parkour")) {
                            $player->sendMessage($this->getMessage("no-permission-break-others"));
                            $event->setCancelled(true);
                            return;
                        }
                    }

                    //echo("DELETING START PARKOUR\n");
                    $this->parkour[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()] = null;
                    unset($this->parkour[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()]);
                }
            }
//Delete all sessions for players in this parkour

            foreach ($this->sessions as $key => $sessionarray) {
                if ($sessionarray["parkour"] === $parkourname)
                    $this->sessions[$key] = null;
                unset($this->sessions[$key]);
            }


            $this->saveParkours();
            $player->sendMessage($this->getMessage("parkour-removed"));
        }
    }

//ANTICHEATS

    public function onPlayerTeleport(EntityTeleportEvent $event) {
        $entity = $event->getEntity();
        if ($entity instanceof Player) {
            if (isset($this->sessions[$entity->getName()])) {
                $event->setCancelled(true);
            }
        }
    }

    public function onGameModeChange(PlayerGameModeChangeEvent $event) {
        if (isset($this->sessions[$event->getPlayer()->getName()])) {
            $event->setCancelled(true);
            $event->getPlayer()->sendMessage($this->getMessage("no-cheat"));
        }
    }

    public function onPlayerQuit(PlayerQuitEvent $event) {

//Clean up

        if (isset($this->sessions[$event->getPlayer()->getName()])) {
            unset($this->sessions[$event->getPlayer()->getName()]);
        }
    }

//Clean up again, why not?

    public function onJoin(PlayerJoinEvent $event) {
        //Clear Session if left over from previous session....
        if (isset($this->sessions[$event->getPlayer()->getName()])) {
            unset($this->sessions[$event->getPlayer()->getName()]);
        }
    }

    public function onPlayerRespawn(PlayerRespawnEvent $event) {
        //TP back to Start

        if (!isset($this->sessions[$event->getPlayer()->getName()]))
            return;

        $parkourname = $this->sessions[$event->getPlayer()->getName()]["parkour"];
        unset($this->sessions[$event->getPlayer()->getName()]);
        //echo ("PK:" . $parkourname) . "\n";

        $pks = $this->search($this->parkour, 'name', $parkourname);
        //get the x y z and level of the Start Sign
        foreach ($pks as $p) {
            //var_dump($p);
            if ($p["type"] === 0) {
                $x = $p["x"];
                $y = $p["y"];
                $z = $p["z"];
                $level = $p["level"];
            }
        }

        $pos = new Position($x, $y, $z, $this->getServer()->getLevelByName($level));
        $event->setRespawnPosition($pos);
        $event->getPlayer()->sendMessage($this->getMessage("start-again"));
    }

//    public function onPlayerDeath(PlayerDeathEvent $event) {
//
//        //TP back to Start
//
//        if (!isset($this->sessions[$event->getPlayer()->getName()])) return;
//
//        $parkourname = $this->sessions[$event->getPlayer()->getName()];
//
//        //echo ("PK:" . $parkourname) . "\n";
//
//        $pks = $this->search($this->parkour, 'name', $parkourname);
//        //get the x y z and level of the Start Sign
//        foreach ($pks as $p){
//            var_dump($p);
//        if ($p["type"] === 0){
//        $x = $p["x"];
//        $y = $p["y"];
//        $z = $p["z"];
//        $level = $p["level"]; 
//        }
//        }
//  
//        $pos = new Vector3($x, $y, $z);
//        $world = $this->getServer()->getLevelByName($level);
//
//        unset($this->sessions[$event->getPlayer()->getName()]); // so teleport works...
//
//        $event->getPlayer()->teleport($world->getSafeSpawn($pos));
//        
//        //Could also be
//        //$event->getPlayer()->teleport(new Position($x, $y, $z, $this->getServer()->getLevelByName($level)));
//
//        //$this->sessions[$event->getPlayer()->getName()] = $parkourname; // set the session again
//        
//    }
    //TOOLS

    public function checkTag($firstLine, $secondLine) {
        if (!$this->tag->exists($secondLine)) {
            return false;
        }
        foreach ($this->tag->get($secondLine) as $key => $data) {
            if ($firstLine === $key) {
                return $data;
            }
        }
        return false;
    }

    public function getMessage($key, $value = ["%1", "%2"]) {
        if ($this->lang->exists($key)) {
            return str_replace(["%1", "%2"], [$value[0], $value[1]], $this->lang->get($key));
        } else {
            return "Language with key \"$key\" does not exist";
        }
    }

//    //Not used for now, same as inline code
//    public function removeElementWithValue($array, $key, $name, $key2, $type) {
//        foreach ($array as $subKey => $subArray) {
//            if ($subArray[$key] == $name && $subArray[$key2] == $type) {
//                unset($array[$subKey]);
//            }
//        }
//        return $array;
//    }

    function search($array, $key, $value) {
        $results = array();

        if (is_array($array)) {
            if (isset($array[$key]) && $array[$key] == $value) {
                $results[] = $array;
            }

            foreach ($array as $subarray) {
                $results = array_merge($results, $this->search($subarray, $key, $value));
            }
        }

        return $results;
    }

    function timeSpent($start, $s) {
        $t = array(//suffixes
            'd' => 86400,
            'h' => 3600,
            'm' => 60,
        );
        $s = abs($s - $start);
        $stringtemp = "";
        foreach ($t as $key => &$val) {
            $$key = floor($s / $val);
            $s -= ($$key * $val);
            $stringtemp .= ($$key == 0) ? '' : $$key . "$key ";
        }
        return $stringtemp . $s . 's';
    }

    function saveParkours() {
        $parkourYml = new Config($this->getDataFolder() . "ParkourData.yml", Config::YAML);
        $parkourYml->setAll($this->parkour);
        $parkourYml->save();
    }

}
