<?php

declare(strict_types=1);

namespace Northrook\ErrorHandler;

use Northrook\Contracts\ErrorHandler\ErrorReport;
use Northrook\Contracts\ErrorHandler\StackFrame;
use Northrook\Contracts\Interfaces\ErrorRendererInterface;
use Northrook\Core;
use Northrook\Core\AnsiFormatter;

final class CliErrorRenderer implements ErrorRendererInterface
{
    private readonly AnsiFormatter $formatter;

    public function __construct(
        null|AnsiFormatter $formatter = null,
    ) {
        $this->formatter = $formatter ?? new AnsiFormatter();
    }

    public function render(
        ErrorReport $report,
    ): string {
        $format = $this->formatter;

        $lines   = [];
        $lines[] = $format->colorizeString('<red>' . $report->class . '</red>: ' . $report->message);
        $lines[] = $format->colorizeString('<gray>  at ' . $report->file . ':' . $report->line . '</gray>');

        $lines[] = $format->colorizeString('<yellow>  severity: ' . $report->severity . '</yellow>');

        if ($report->phpError !== null) {
            $lines[] = $format->colorizeString(
                '<gray>  php error type: ' . $report->phpError['type'] . '</gray>',
            );
        }

        if ($report->meta !== []) {
            $lines[] = $format->colorizeString(
                '<gray>  meta: ' . \json_encode($report->meta, JSON_UNESCAPED_UNICODE) . '</gray>',
            );
        }

        $lines[] = '';
        $lines[] = $format->colorizeString('<cyan>Stack trace:</cyan>');

        foreach ($report->trace as $index => $frame) {
            $lines[] = $this->formatFrame($index, $frame);
        }

        if ($report->dumps !== []) {
            $lines[] = '';
            $lines[] = $format->colorizeString(
                '<cyan>Dumps: ' . \implode(', ', \array_keys($report->dumps)) . '</cyan>',
            );
        }

        return \implode(PHP_EOL, $lines) . PHP_EOL;
    }

    public function supports(ErrorReport $report): bool
    {
        return Core::isCli();
    }

    private function formatFrame(
        int $index,
        StackFrame $frame,
    ): string {
        $location =
            $frame->file !== null && $frame->line !== null
                ? $frame->file . ':' . $frame->line
                : '[internal]';

        $callable = $frame->class !== null
            ? $frame->class . ( $frame->type ?? '' ) . ( $frame->function ?? '' )
            : $frame->function ?? 'unknown';

        return $this->formatter->colorizeString(
            '<gray>' . \sprintf('#%d %s %s()', $index, $location, $callable) . '</gray>',
        );
    }
}
