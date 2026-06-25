<?php

declare(strict_types=1);

namespace Northrook\ErrorHandler;

use Northrook\Contracts\ErrorHandler\ErrorReport;
use Northrook\Contracts\Interfaces\ErrorRendererInterface;

final class JsonErrorRenderer implements ErrorRendererInterface
{
    /**
     * @throws \JsonException
     */
    public function render(
        ErrorReport $report,
    ): string {
        return \json_encode(
            $report,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    public function supports(
        ErrorReport $report,
    ): bool {
        return true;
    }
}
