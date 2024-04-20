<?php

namespace Northrook\Core\Service;

interface ServiceInterface
{
    public function getStatus() : Status;
}