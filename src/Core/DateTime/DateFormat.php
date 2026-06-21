<?php

declare(strict_types=1);

namespace Northrook\Core\DateTime;

enum DateFormat: string
{
    // App conventions
    case SORTABLE = 'Y-m-d H:i:s';
    case DATE     = 'Y-m-d';
    case TIME     = 'H:i:s';

    // Interchange
    case RFC3339          = 'Y-m-d\TH:i:sP';
    case RFC3339_EXTENDED = 'Y-m-d\TH:i:s.vP';

    // Protocols
    case RSS    = 'D, d M Y H:i:s O';
    case COOKIE = 'l, d-M-Y H:i:s T';
}
