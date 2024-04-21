<?php

namespace Northrook\Core\Service;

interface ServiceStatusInterface
{
    public function getStatus() : Status;
}