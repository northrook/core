<?php

declare(strict_types=1);

namespace Northrook\ErrorHandler;

use Framework\Dev\Dump\HtmlStylesheet;
use Framework\Dev\Dump\OriginHtml;
use Framework\Dev\VarDump\Origin;
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
        $styles  = $this->styles();
        $origin  = $this->exceptionOrigin($report);
        $dumps   = $this->dumpBlocks($report);

        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="color-scheme" content="light dark">
                <title>{$class}</title>
                <style>{$styles}</style>
            </head>
            <body id="error-report">
                <section error-exception="{$report->reference}">
                    <h1 error-class>{$class}</h1>
                    <p error-message>{$message}</p>
                    <p error-meta>{$report->severity}</p>
                    {$origin}
                </section>
                {$dumps}
                <script type="application/json" id="error-report">{$json}</script>
            </body>
            </html>
            HTML;
    }

    public function supports(ErrorReport $report): bool
    {
        return ! Core::isCli();
    }

    private function styles(): string
    {
        if (\class_exists(HtmlStylesheet::class)) {
            return HtmlStylesheet::inline() . "\n"
                . <<<'CSS'
                body#error-report {
                    margin: 0;
                    padding: 2rem;
                }
                [error-exception] {
                    margin-bottom: 2rem;
                }
                [error-exception] h1 {
                    margin: 0 0 .5rem;
                    font: 600 1.25rem/1.4 ui-sans-serif, system-ui, sans-serif;
                    color: var(--danger);
                }
                [error-exception] [error-message] {
                    margin: 0 0 .5rem;
                    font: 14px/1.5 ui-monospace, monospace;
                    white-space: pre-wrap;
                }
                [error-exception] [error-meta] {
                    margin: 0 0 1rem;
                    color: var(--muted);
                    font: 12px/1.4 ui-monospace, monospace;
                    text-transform: uppercase;
                    letter-spacing: .04em;
                }
                [error-dumps] {
                    display: grid;
                    gap: 1rem;
                }
                CSS;
        }

        return <<<'CSS'
            body { font-family: ui-monospace, monospace; margin: 2rem; background: #111; color: #eee; }
            h1 { color: #f66; font-size: 1.25rem; }
            CSS;
    }

    private function exceptionOrigin(ErrorReport $report): string
    {
        if (! \class_exists(Origin::class) || ! \class_exists(OriginHtml::class)) {
            $file = \htmlspecialchars($report->file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return '<p error-meta>' . $file . ':' . $report->line . '</p>';
        }

        if (! \is_readable($report->file)) {
            $file = \htmlspecialchars($report->file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return '<p error-meta>' . $file . ':' . $report->line . '</p>';
        }

        try {
            return OriginHtml::render(
                Origin::at($report->file, $report->line, $report->class),
                $report->reference . '-origin',
            );
        } catch (\Throwable) {
            $file = \htmlspecialchars($report->file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return '<p error-meta>' . $file . ':' . $report->line . '</p>';
        }
    }

    private function dumpBlocks(ErrorReport $report): string
    {
        if ($report->dumps === []) {
            return '';
        }

        $blocks = [];

        foreach ($report->dumps as $dump) {
            if (! \is_array($dump)) {
                continue;
            }

            if (\is_string($dump['html'] ?? null)) {
                $blocks[] = $dump['html'];
            }
        }

        if ($blocks === []) {
            return '';
        }

        return '<section error-dumps>' . \implode("\n", $blocks) . '</section>';
    }
}
