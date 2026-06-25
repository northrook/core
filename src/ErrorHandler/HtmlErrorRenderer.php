<?php

declare(strict_types=1);

namespace Northrook\ErrorHandler;

use Northrook\Contracts\ErrorHandler\ErrorReport;
use Northrook\Contracts\Interfaces\ErrorRendererInterface;
use Northrook\Core;

final class HtmlErrorRenderer implements ErrorRendererInterface
{
    /**
     * @throws \JsonException
     */
    public function render(ErrorReport $report): string
    {
        $json = \json_encode(
            $report,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP,
        );

        $message = \htmlspecialchars($report->message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $class   = \htmlspecialchars($report->class, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $file    = \htmlspecialchars($report->file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>{$class}</title>
                <style>
                    body { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; margin: 2rem; background: #111; color: #eee; }
                    h1 { color: #f66; font-size: 1.25rem; }
                    .meta { color: #aaa; margin-bottom: 1.5rem; }
                    #error-report-mount { margin-top: 2rem; border-top: 1px solid #333; padding-top: 1rem; color: #888; }
                </style>
            </head>
            <body>
                <h1>{$class}</h1>
                <p>{$message}</p>
                <p class="meta">{$file}:{$report->line} &middot; {$report->severity}</p>
                <div id="error-report-mount" data-report-ref="{$report->reference}">Error report loaded.</div>
                <script type="application/json" id="error-report">{$json}</script>
            </body>
            </html>
            HTML;
    }

    public function supports(ErrorReport $report): bool
    {
        return ! Core::isCli();
    }
}
