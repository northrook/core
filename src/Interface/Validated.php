<?php

declare( strict_types = 1 );

namespace Northrook\Core\Interface;

interface Validated
{
    public function validate() : bool;
}