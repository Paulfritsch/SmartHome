<?php

namespace SmartSave\Observer;

interface SubjectInterface
{
    public function attach(ObserverInterface $observer): void;

    public function detach(ObserverInterface $observer): void;

    public function notify(): void;
}
