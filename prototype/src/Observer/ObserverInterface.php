<?php

namespace SmartSave\Observer;

use SmartSave\Device;

interface ObserverInterface
{
    public function update(Device $device): void;
}
