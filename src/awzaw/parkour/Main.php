<?php

namespace awzaw\parkour;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\item\ItemFactory;
use pocketmine\level\Position;
use pocketmine\utils\TextFormat;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerGameModeChangeEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\block\BlockIds;

class Main extends PluginBase implements Listener {

    const SIGNIDS = [Item::SIGN_POST, Item::WALL_SIGN];
    public $parkour;
    public $sessions;
    private $lang, $tag, $cfg, $basichud;
    private $purePerms;
    private $economyAPI;

    public function onEnable() {
        if (!file_exists($this->getDataFolder())) {
            mkdir($this->getDataFolder());
        }

        $this->parkour = [];
        $this->sessions = [];
        if ($this->getServer()->getPluginManager()->getPlugin("BasicHUD")) {
            $this->purePerms = $this->getServer()->getPluginManager()->getPlugin("PurePerms");
            if ($this->purePerms !== null) {
                $this->getLogger()->info("PurePerms is enabled to allow hiding BasicHUD");
            }
        }

        $this->economyAPI = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
        if ($this->economyAPI !== null) {
            $this->getLogger()->info("EconomyAPI is installed, you can use $ amounts as rewards");
        }
        $this->saveResource("language.properties");
        $this->saveResource("parkour.yml");
        $this->saveResource("config.yml");
        $this->lang = new Config($this->getDataFolder() . "language.properties", Config::PROPERTIES);
        $this->tag = new Config($this->getDataFolder() . "parkour.yml", Config::YAML);
        $this->cfg = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $parkourYml = new Config($this->getDataFolder() . "ParkourData.yml", Config::YAML);
        $this->parkour = $parkourYml->getAll();
        $this->basichud = $this->cfg->get("basichud-compatibility");
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onDisable() {
        $this->saveParkours();
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $param): bool {
        switch ($cmd->getName()) {
            case "pk":
                if (!$sender instanceof Player) {
                    $sender->sendMessage(TextFormat::GREEN . $this->getMessage("run-in-game"));
                    break;
                }

                if (isset($param[0]) && (strtolower($param[0]) !== "help")) {

                    switch ($param[0]) {

                        case "list":

                            $pklist = "";
                            foreach ($this->parkour as $parkour => $pkdata) {

                                if ($pkdata["type"] === 0) {
                                    $pkname = $pkdata["name"];
                                    $pkmaker = substr($pkdata["maker"], 0, 5);
                                    $count = [];
                                    foreach ($this->parkour as $p) {
                                        @$count[$p['name']]++;
                                    }
                                    $howmanyparkour = $count[$pkname];
                                    if ($howmanyparkour < 2) {
                                        $pklist = $pklist . TextFormat::RED . $pkname . TextFormat::GRAY . " (" . $pkmaker . ") ";
                                    } else {
                                        $pklist = $pklist . TextFormat::GREEN . $pkname . TextFormat::GRAY . " (" . $pkmaker . ") ";
                                    }
                                }
                            }
                            $sender->sendMessage($pklist);
                            return true;

                        case "go":

                            $pkstarts = $this->search($this->parkour, 'type', 0);
                            $pkends = $this->search($this->parkour, 'type', 1);
                            //get the x y z and level of a random Start Sign

                            $validpk = [];
                            foreach ($pkstarts as $key) {
                                if (!empty($this->search($pkends, 'name', $key["name"]))) {
                                    $validpk[] = $key;
                                }
                            }

                            if (empty($validpk) || (count($validpk) == 0)) {
                                $sender->sendMessage(TextFormat::RED . $this->getMessage("no-parkour"));
                                break;
                            }

                            $pkrand = $validpk[mt_rand(0, count($validpk) - 1)];
                            $parkourname = $pkrand["name"];
                            $x = (int)$pkrand["x"];
                            $y = (int)$pkrand["y"];
                            $z = (int)$pkrand["z"];
                            $level = $pkrand["level"];
                            $pos = new Position($x, $y, $z, $this->getServer()->getLevelByName($level));
                            $sender->teleport($pos);
                            $sender->sendMessage(TextFormat::GREEN . $this->getMessage("random-parkour") . " " . $parkourname);
                            return true;

                        case "killbrick":

                            $pktochange = $param[1];
                            $killbrickID = $param[2];

                            //Set Kill Brick in START Sign
                            foreach (array_keys($this->parkour) as $pk) {
                                if ($this->parkour[$pk]["type"] === 0 && ($this->parkour[$pk]["name"] === $pktochange)) {
                                    if (strtolower($this->parkour[$pk]["maker"]) !== strtolower($sender->getName())) {
                                        $sender->sendMessage(TextFormat::GREEN . $this->getMessage("no-permission-break-others"));
                                        break;
                                    }
                                    if (substr($killbrickID, 0, 2) === "no") {
                                        $this->parkour[$pk]["killbrick"] = null;
                                        unset($this->parkour[$pk]["killbrick"]);
                                        $this->saveParkours();
                                        $sender->sendMessage("KillBrick Removed");
                                        return true;
                                    } else {
                                        $killblock = Item::get($killbrickID);
                                        if (!$killblock instanceof ItemBlock) {
                                            $sender->sendMessage("Invalid ID");
                                            break 2;
                                        }
                                        $idstring = Item::get($killbrickID)->getName();
                                    }
                                    $this->parkour[$pk]["killbrick"] = $killbrickID;
                                    $this->saveParkours();
                                    $sender->sendMessage("KillBrick Set: " . $idstring);
                                    return true;
                                }
                            }
                            break;

                        default:

                            //GO TO A PARKOUR
                            $pktovisit = $param[0];
                            foreach ($this->parkour as $parkour => $pkdata) {

                                if ($pkdata["type"] === 0 && $pkdata["name"] === $pktovisit) {
                                    //Check there is a Sign Pair, if not warn
                                    $count = [];
                                    foreach ($this->parkour as $p) {
                                        @$count[$p['name']]++;
                                    }
                                    $howmanyparkour = $count[$pktovisit];
                                    if ($howmanyparkour < 2) {
                                        $sender->sendMessage(TextFormat::RED . $this->getMessage("incomplete-parkour") . " " . $pktovisit);
                                        break 2;
                                    }
                                    $x = (int)$pkdata["x"];
                                    $y = (int)$pkdata["y"];
                                    $z = (int)$pkdata["z"];
                                    $level = $pkdata["level"];
                                    $pos = new Position($x, $y, $z, $this->getServer()->getLevelByName($level));
                                    $sender->teleport($pos);
                                    $sender->sendMessage(TextFormat::GREEN . $this->getMessage("teleport-start") . " " . $pktovisit);
                                    return true;
                                }
                            }
                            $sender->sendMessage(TextFormat::GREEN . $this->getMessage("invalid-parkour") . " " . $pktovisit);
                    }
                } else {
                    //LIST HELP
                    $sender->sendMessage(TextFormat::GREEN . $this->getMessage("help-title"));
                    $sender->sendMessage(TextFormat::YELLOW . $this->getMessage("help1"));
                    $sender->sendMessage(TextFormat::YELLOW . $this->getMessage("help2"));
                    $sender->sendMessage(TextFormat::YELLOW . $this->getMessage("help3"));
                    $sender->sendMessage(TextFormat::GREEN . $this->getMessage("parkour-commands"));
                    $sender->sendMessage(TextFormat::YELLOW . $this->getMessage("parkour-command1"));
                    $sender->sendMessage(TextFormat::YELLOW . $this->getMessage("parkour-command2"));
                    $sender->sendMessage(TextFormat::YELLOW . $this->getMessage("parkour-command3"));
                    $sender->sendMessage(TextFormat::YELLOW . $this->getMessage("parkour-command4"));
                    return true;
                }
        }
        return false;
    }

    public function onSignChange(SignChangeEvent $event) {
        if ($event->isCancelled()) return;
        if (($data = $this->checkTag($event->getLine(0), $event->getLine(1))) !== false) {
            $player = $event->getPlayer();
            if (!$player->hasPermission("parkour.create")) {
                $player->sendMessage($this->getMessage("no-permission-create"));
                return;
            }

            $block = $event->getBlock();
            $parkourname = $event->getLine(2);

            switch ($event->getLine(1)) {
                case "start":
                    if (isset($this->economyAPI) && strpos($event->getLine(3), "$") > 0) {
                        $id = $idstring = "$";
                        $amount = substr($event->getLine(3), 0, strlen($event->getLine(3)) - 1);
                        if (!is_numeric($amount)) {
                            $amount = 1000;
                        }
                    } else {
                        $idamount = explode(':', $event->getLine(3));
                        //If no amount given, set to 64
                        $amount = (isset($idamount[1]) && $idamount[1] !== '') ? $idamount[1] : 64;
                        //If no reward given, set to 57
                        $id = (isset($idamount[0]) && $idamount[0] !== '') ? $idamount[0] : 57;
                        // Check if the string reward is a valid block
                        if (!is_numeric($id)) {
                            $rewardblock = Item::fromString((string)$id);
                            if (!($rewardblock instanceof Item)) {
                                $player->sendMessage($this->getMessage("reward-invalid"));
                                break;
                            }
                            $idstring = $id;
                            $id = $rewardblock->getId();
                        } else {
                            if ($id === 0) {
                                $id = 57;
                            }
                            $rewardblock = Item::get((int)$id);

                            if (!($rewardblock instanceof Item)) {
                                $player->sendMessage($this->getMessage("reward-invalid"));
                                break;
                            }
                            $idstring = Item::get((int)$id)->getName();
                        }
                    }
                    //Check Target Parkour is valid
                    if (trim($event->getLine(2)) === "") {
                        $player->sendMessage($this->getMessage("no-target-parkour"));
                        break;
                    }

                    //Check if there's already a different START Sign with this name, different coords, and stop if there is
                    foreach (array_keys($this->parkour) as $d) {
                        $issame = $d === $block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName();
                        if (!$issame && $this->parkour[$d]["type"] === 0 && ($this->parkour[$d]["name"] === $parkourname)) {
                            $player->sendMessage($this->getMessage("start-exists") . " " . $parkourname);
                            break 2;
                        }
                    }

                    //Check if there's a FINISH Sign for the message
                    $finishfound = false;
                    foreach (array_keys($this->parkour) as $e) {
                        if ($this->parkour[$e]["type"] === 1 && ($this->parkour[$e]["name"] === $parkourname)) {
                            $player->sendMessage($this->getMessage("start-created-finish"));
                            $finishfound = true;
                            break;
                        }
                    }
                    if (!$finishfound) {
                        $player->sendMessage($this->getMessage("start-created-nofinish"));
                    }
                    $this->parkour[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()] = [
                        "type" => 0,
                        "reward" => $id . ":" . $amount,
                        "name" => ($event->getLine(2)),
                        "x" => (int)$player->x,
                        "y" => (int)$player->y,
                        "z" => (int)$player->z,
                        "level" => $player->getLevel()->getFolderName(),
                        "top" => [],
                        "maker" => $player->getName()
                    ];

                    $this->saveParkours();

                    //Write the START SIGN
                    $signtext = [
                        TextFormat::GREEN . $data[0],
                        TextFormat::WHITE . $data[1],
                        TextFormat::AQUA . str_replace(["%2", "%MONETARY_UNIT%"], [$event->getLine(2)], $data[2]),
                        TextFormat::GOLD . ($idstring !== "$" ? str_replace(["%1"], $idstring . ' x ' . $amount, $data[3]) : $amount . $idstring)
                    ];

                    $v = new Vector3($block->getX(), $block->getY(), $block->getZ());
                    $tile = $block->getLevel()->getTile($v);
                    $task = new SignTask($this, $tile, $signtext);
                    $this->getScheduler()->scheduleDelayedTask($task, 20);
                    break;

                case ("finish"):

                    if (trim($parkourname) === "") {
                        $player->sendMessage($this->getMessage("no-parkour-name"));
                        break;
                    }
                    if (strpos($parkourname, ":")) {
                        $player->sendMessage($this->getMessage("invalid-parkour-name"));
                        break;
                    }

                    //Check if there's already a FINISH Sign
                    foreach (array_keys($this->parkour) as $d) {
                        if ($this->parkour[$d]["type"] === 1 && ($this->parkour[$d]["name"] === $parkourname)) {
                            $player->sendMessage($this->getMessage("finish-exists") . " " . $parkourname);
                            break 2;
                        }
                    }

                    //Message  depends if there is a start sign
                    $startfound = false;
                    foreach (array_keys($this->parkour) as $d) {
                        if ($this->parkour[$d]["type"] === 0 && $this->parkour[$d]["name"] === $parkourname) {
                            $player->sendMessage($this->getMessage("start-exists"));
                            $startfound = true;
                            break;
                        }
                    }

                    if (!$startfound) {
                        $player->sendMessage($this->getMessage("no-start"));
                    }

                    $this->parkour[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()] = [
                        "name" => $event->getLine(2),
                        "type" => 1
                    ];

                    $this->saveParkours();
                    $signtext = [
                        TextFormat::RED . $data[0],
                        TextFormat::WHITE . $data[1],
                        TextFormat::AQUA . str_replace("%1", $event->getLine(2), $data[2]),
                        TextFormat::GOLD . str_replace("%1", $event->getLine(3), $data[3])
                    ];

                    $v = new Vector3($block->getX(), $block->getY(), $block->getZ());
                    $tile = $block->getLevel()->getTile($v);
                    $task = new SignTask($this, $tile, $signtext);
                    $this->getScheduler()->scheduleDelayedTask($task, 20);
                    break;
            }
        }
    }

    public function onInteract(PlayerInteractEvent $event) {
        if ($event->isCancelled()) return;
        if ($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
            return;
        }

        $block = $event->getBlock();
        if (!in_array($block->getID(), self::SIGNIDS)) {
            return;
        }

        $sender = $event->getPlayer();

        //If it's a parkour block that was clicked
        if (isset($this->parkour[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()])) {
            $parkour = $this->parkour[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()];

            if ($parkour["type"] === 1) {

                // Player clicked a FINISH sign
                $parkourname = $this->parkour[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()]["name"];

                //Check there is a Sign Pair, if not warn and exit
                $count = [];
                foreach ($this->parkour as $p) {
                    @$count[$p['name']]++;
                }
                $howmanyparkour = $count[$parkourname];
                if ($howmanyparkour < 2) {
                    $sender->sendMessage(TextFormat::RED . $this->getMessage("no-start-sign") . " " . $parkourname);
                    return;
                }

                //a finish sign was clicked... but no session exists
                if (!isset($this->sessions[$sender->getName()])) {

                    // teleport player to START SIGN
                    $pks = $this->search($this->parkour, 'name', $parkourname);
                    //get the x y z and level of the Start Sign
                    foreach ($pks as $p) {
                        if ($p["type"] === 0) {
                            $x = $p["x"];
                            $y = $p["y"];
                            $z = $p["z"];
                            $level = $p["level"];
                        }
                    }
                    $pos = new Position($x, $y, $z, $this->getServer()->getLevelByName($level));
                    $sender->teleport($pos);
                    $sender->sendMessage(TextFormat::BLUE . $this->getMessage("click-start") . " " . $parkourname);
                    return;
                }

                if ($parkourname == $this->sessions[$sender->getName()]["parkour"]) {

                    // CONGRATULATIONS!! a session exists and it's the same name as the finish pk sign you clicked
                    $endtime = time();
                    $starttime = $this->sessions[$sender->getName()]["start"];
                    $timespent = $this->timeSpent($starttime, $endtime);

                    //Get the reward from the Corresponding Start Sign
                    foreach (array_keys($this->parkour) as $p) {

                        if ($this->parkour[$p]["name"] == $parkourname && $this->parkour[$p]["type"] == 0) {
                            $reward = $this->parkour[$p]["reward"];

                            //Check if Top Score
                            if (!isset($this->parkour[$p]["top"]["besttime"]) || ($endtime - $starttime) < $this->parkour[$p]["top"]["besttime"]) {
                                $this->parkour[$p]["top"] = ["player" => ($sender->getName()), "besttime" => ($endtime - $starttime)];
                                $sender->sendMessage(TextFormat::GOLD . $this->getMessage("best-score") . " " . $timespent);
                            }
                        }
                    }

                    if (!isset($reward)) {
                        $reward = "64:16";
                    }

                    $idamount = explode(':', $reward);
                    $id = $idamount[0];
                    if (!isset($idamount[1])) {
                        $amount = 64;
                    } else {
                        $amount = $idamount[1];
                    }

                    // Convert string rewards to ID
                    if (isset($this->economyAPI) && $id === "$") {
                        $this->economyAPI->addMoney($sender, $amount);
                        $idstring = "$";
                    } else {
                        if (!is_numeric($id)) {
                            $item = Item::fromString($id);
                            if ($item instanceof ItemBlock) {
                                $id = $item->getId();
                            }
                        }
                        $idstring = Item::get($id)->getName();
                        $sender->getInventory()->addItem(ItemFactory::get($id, 0, $amount));
                    }

                    $sender->sendMessage(TextFormat::AQUA . $this->getMessage("parkour-completed") . " " . $parkourname . " for " . $amount . ($idstring === "$" ? "$" : " " . $idstring) . " in " . $timespent);
                    unset($this->sessions[$sender->getName()]);

                    if (isset($this->purePerms) && $this->basichud) {
                        $this->purePerms->getUserDataMgr()->unsetPermission($sender, "-basichud.user");
                    }
                    return;
                }

                // a session exists and it's not the pk the user is currently playing
                if ($parkourname != $this->sessions[$sender->getName()]["parkour"]) {
                    $sender->sendMessage($this->getMessage("already-playing") . " " . $this->sessions[$sender->getName()]["parkour"]);
                    return;
                }
            } else {
                //User Clicked a Start Sign
                //If there's no end sign for the start.. message and return
                $parkourname = $this->parkour[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()]["name"];
                $count = [];
                foreach ($this->parkour as $p) {
                    @$count[$p['name']]++;
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
                    $sender->sendMessage(TextFormat::YELLOW . $this->getMessage("parkour-started") . " " . $parkourplaying . " by " . $maker);

                    //If a best time is set, display it
                    if (isset($parkour["top"]["besttime"])) {
                        $bestplayer = $parkour["top"]["player"];
                        $besttime = $this->timeSpent(0, $parkour["top"]["besttime"]);
                        $sender->sendMessage($this->getMessage("best-parkour") . " " . $besttime . " by " . $bestplayer);
                    }

                    if (!isset($parkour["killbrick"])) {
                        $parkour["killbrick"] = 0;
                    } else {
                        $sender->sendMessage(TEXTFORMAT::RED . "BEWARE THE KILLBRICK: " . Item::get($parkour["killbrick"])->getName());
                    }
                    $task = new MessageTask($this, $sender, 10, $parkour["killbrick"]);
                    if (isset($this->purePerms) && $this->basichud) {
                        $this->purePerms->getUserDataMgr()->setPermission($sender, "-basichud.user");
                    }

                    $taskid = $this->getScheduler()->scheduleRepeatingTask($task, 20);
                    $task->setHandler($taskid);
                    $this->sessions[$sender->getName()] = ["parkour" => $parkourplaying, "start" => time(), "killbrick" => $parkour["killbrick"]];
                } else {
                    $sender->sendMessage(TextFormat::RED . $this->getMessage("already-playing") . " " . $this->sessions[$sender->getName()]["parkour"]);
                }
            }
        }
    }

    public function onBlockBreak(BlockBreakEvent $event) {
        if ($event->isCancelled()) return;
        $block = $event->getBlock();
        if (!in_array($block->getID(), self::SIGNIDS)) {
            return;
        }

        if (isset($this->parkour[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()])) {
            $player = $event->getPlayer();
            $parkour = $this->parkour[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()];
            $parkourname = $parkour["name"];
            $parkourtype = $parkour["type"];

            //Delete this parkour marker & LOOP THROUGH ALL PARKOURS
            foreach ($this->parkour as $subKey => $subArray) {

                if ($parkourtype === 1 && $subArray["name"] == $parkourname && ($subArray["type"] === 0)) {

                    //we are breaking A Finish Sign so deal with the START sign first to check maker
                    $maker = $subArray["maker"];
                    if ($maker === $player->getName()) {
                        if (!$player->hasPermission("parkour.create")) {
                            $player->sendMessage(TextFormat::RED . $this->getMessage("no-permission-break"));
                            $event->setCancelled(true);
                            return;
                        }
                    } else {// If it's someone elses parkour...
                        if (!$player->hasPermission("parkour")) {
                            $player->sendMessage(TextFormat::RED . $this->getMessage("no-permission-break-others"));
                            $event->setCancelled(true);
                            return;
                        }
                    }

                    $this->parkour[$subKey]["top"] = null;
                    unset($this->parkour[$subKey]["top"]); // And clear top scores when the FINISH sign is broken
                }

                if ($parkourtype === 0 && $subArray["name"] == $parkourname && $subArray["type"] === 0) {
                    //We are breaking a Start Parkour, so check the maker

                    $maker = $subArray["maker"];
                    if ($maker === $player->getName()) {
                        if (!$player->hasPermission("parkour.create")) {
                            $player->sendMessage(TextFormat::RED . $this->getMessage("no-permission-break"));
                            $event->setCancelled(true);
                            return;
                        }
                    } else {// If it's someone elses parkour...
                        if (!$player->hasPermission("parkour")) {
                            $player->sendMessage(TextFormat::RED . $this->getMessage("no-permission-break-others"));
                            $event->setCancelled(true);
                            return;
                        }
                    }
                }
                //set to null first - unset() seems to be delayed sometimes?!
                $this->parkour[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()] = null;
                unset($this->parkour[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()]);
            }

            //Delete all sessions for players in this parkour
            foreach ($this->sessions as $key => $sessionarray) {
                if ($sessionarray["parkour"] === $parkourname) {
                    $this->sessions[$key] = null;
                }
                unset($this->sessions[$key]);
            }
            $this->saveParkours();
            $player->sendMessage(TextFormat::RED . $this->getMessage("parkour-removed"));
        }
    }

//ANTICHEATS

    public function onPlayerCommand(PlayerCommandPreprocessEvent $event) {
        if ($event->isCancelled()) return;
        $sender = $event->getPlayer();
        if (!isset($this->sessions[$sender->getName()])) {
            return;
        }
        $message = $event->getMessage();
        if (strtolower(substr($message, 0, 5) === "/kill")) { //Command
            if (isset($this->purePerms) && $this->basichud) {
                $this->purePerms->getUserDataMgr()->unsetPermission($sender, "-basichud.user");
            }
            return;
        } else if (strtolower(substr($message, 0, 1) === "/")) {
            $sender->sendMessage($this->getMessage("no-commands"));
            $event->setCancelled(true);
        }
    }

    public function onPlayerTeleport(EntityTeleportEvent $event) {
        if ($event->isCancelled()) return;
        $entity = $event->getEntity();
        if ($entity instanceof Player) {
            if (isset($this->sessions[$entity->getName()])) {
                $event->setCancelled(true);
            }
        }
    }

    public function onGameModeChange(PlayerGameModeChangeEvent $event) {
        if ($event->isCancelled()) return;
        if (isset($this->sessions[$event->getPlayer()->getName()])) {
            $event->setCancelled(true);
            $event->getPlayer()->sendMessage($this->getMessage("no-cheat"));
        }
    }

    public function onPlayerQuit(PlayerQuitEvent $event) {
        if (isset($this->sessions[$event->getPlayer()->getName()])) {
            unset($this->sessions[$event->getPlayer()->getName()]);
            if (isset($this->purePerms) && $this->basichud) {
                $this->purePerms->getUserDataMgr()->unsetPermission($event->getPlayer(), "-basichud.user");
            }
        }
    }

//Clean up again, why not?
    public function onJoin(PlayerJoinEvent $event) {
        if (isset($this->sessions[$event->getPlayer()->getName()])) {
            unset($this->sessions[$event->getPlayer()->getName()]);
        }
        //WARNING - You can not use the negative -basichud.user permission with basichud-compatibility: true
        $event->getPlayer();
        if (isset($this->purePerms) && $this->basichud) {
            $this->purePerms->getUserDataMgr()->unsetPermission($event->getPlayer(), "-basichud.user");
        }
    }

    public function onPlayerRespawn(PlayerRespawnEvent $event) {
        //TP back to Start
        if (!isset($this->sessions[$event->getPlayer()->getName()])) {
            return;
        }

        $parkourname = $this->sessions[$event->getPlayer()->getName()]["parkour"];
        unset($this->sessions[$event->getPlayer()->getName()]);
        $pks = $this->search($this->parkour, 'name', $parkourname);
        //get the x y z and level of the Start Sign
        foreach ($pks as $p) {
            if ($p["type"] === 0) {
                $x = (int)$p["x"];
                $y = (int)$p["y"];
                $z = (int)$p["z"];
                $level = $p["level"];
            }
        }
        $pos = new Position($x, $y, $z, $this->getServer()->getLevelByName($level));
        $event->setRespawnPosition($pos);
        $event->getPlayer()->sendMessage(TextFormat::YELLOW . $this->getMessage("start-again"));
    }

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

    function search($array, $key, $value) {
        $results = [];
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
        $t = [
            'd' => 86400,
            'h' => 3600,
            'm' => 60
        ];
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
