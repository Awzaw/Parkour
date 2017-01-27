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

use pocketmine\scheduler\PluginTask;
use pocketmine\utils\TextFormat;
use pocketmine\level\Position;

class MessageTask extends PluginTask {

    public function __construct(Main $plugin, $sender, $duration, $killbrick) {
        parent::__construct($plugin);

        $this->plugin = $plugin;
        $this->sender = $sender;
        $this->duration = $duration;
        $this->killbrick = $killbrick;
    }

    public function onRun($tick) {
        $this->plugin = $this->getOwner();

        if (!isset($this->plugin->sessions[$this->sender->getName()])) {
            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        $this->sender->removeAllEffects();     
        
        $this->sender->setFlying(false);  
        
        $blockbelow = $this->sender->getLevel()->getBlock($this->sender->floor()->subtract(0, 1));

        if (($this->killbrick !== 0) && ($blockbelow->getID() == $this->killbrick)) {

            $pkname = $this->plugin->sessions[$this->sender->getName()]["parkour"];
            unset($this->plugin->sessions[$this->sender->getName()]);

            // teleport player to START SIGN
            $pks = $this->plugin->search($this->plugin->parkour, 'name', $pkname);
            //get the x y z and level of the Start Sign
            foreach ($pks as $p) {

                if ($p["type"] === 0) {
                    $x = $p["x"];
                    $y = $p["y"];
                    $z = $p["z"];
                    $level = $p["level"];
                }
            }

            $pos = new Position($x, $y, $z, $this->plugin->getServer()->getLevelByName($level));
            $this->sender->teleport($pos);

            $this->sender->sendMessage(TextFormat::RED . $this->plugin->getMessage("start-again"));
            $this->sender->sendMessage(TextFormat::BLUE . $this->plugin->getMessage("click-start") . " " . $pkname);

            $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        $this->sender->sendPopup(TextFormat::GREEN . (time() - $this->plugin->sessions[$this->sender->getName()]["start"]) . " seconds");
    }

}
