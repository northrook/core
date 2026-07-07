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
        $lines[] = $format->string( '<red>' . $report->class . '</red>: ' . $report->message);
        $lines[] = $format->string( '<gray>  at ' . $report->file . ':' . $report->line . '</gray>');

        $lines[] = $format->string( '<yellow>  severity: ' . $report->severity . '</yellow>');

        if ($report->phpError !== null) {
            $lines[] = $format->string(
                '<gray>  php error type: ' . $report->phpError['type'] . '</gray>',
            );
        }

        if (\count($report->phpErrors) > 1) {
            $lines[] = $format->string(
                '<yellow>  php errors (' . \count($report->phpErrors) . '):</yellow>',
            );

            foreach ($report->phpErrors as $i => $error) {
                $lines[] = $format->string(
                    '<gray>    [' . $i . '] ' . $error['file'] . ':' . $error['line'] . ' ' . $error['message'] . '</gray>',
                );
            }
        }

        if ($report->meta !== []) {
            $lines[] = $format->string(
                '<gray>  meta: ' . \json_encode($report->meta, JSON_UNESCAPED_UNICODE) . '</gray>',
            );
        }

        $lines[] = '';
        $lines[] = $format->string( '<cyan>Stack trace:</cyan>');

        foreach ($report->trace as $index => $frame) {
            $lines[] = $this->formatFrame($index, $frame);
        }

        if ($report->dumps !== []) {
            $lines[] = '';
            $lines[] = $format->string(
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

        return $this->formatter->string(
            '<gray>' . \sprintf('#%d %s %s()', $index, $location, $callable) . '</gray>',
        );
    }
}
