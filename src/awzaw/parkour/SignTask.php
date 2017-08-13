<?php

namespace awzaw\parkour;

use pocketmine\scheduler\PluginTask;
use pocketmine\plugin\Plugin;

class SignTask extends PluginTask{

    public function __construct(Plugin $owner, $tile, $signtext) {
        parent::__construct($owner);
        $this->tile = $tile;
        $this->signtext = $signtext;
    }

    public function onRun(int $currentTick) {
           
        $this->tile->setText($this->signtext[0], $this->signtext[1], $this->signtext[2], $this->signtext[3]);

    }

    public function cancel() {
        $this->getHandler()->cancel();
    }
}