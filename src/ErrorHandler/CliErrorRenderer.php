<?php

declare(strict_types=1);

namespace Northrook\ErrorHandler;

use Northrook\Contracts\ErrorHandler\ErrorReport;
use Northrook\Contracts\ErrorHandler\StackFrame;
use Northrook\Contracts\Interfaces\ErrorRendererInterface;
use Northrook\Core;

final class CliErrorRenderer implements ErrorRendererInterface
{
    private const string RESET  = "\033[0m";
    private const string RED    = "\033[31m";
    private const string YELLOW = "\033[33m";
    private const string CYAN   = "\033[36m";
    private const string GRAY   = "\033[90m";

    public function render(
        ErrorReport $report,
    ): string {
        $lines   = [];
        $lines[] = self::RED . $report->class . self::RESET . ': ' . $report->message;
        $lines[] = self::GRAY . '  at ' . $report->file . ':' . $report->line . self::RESET;
        $lines[] = self::YELLOW . '  severity: ' . $report->severity . self::RESET;

        if ($report->phpError !== null) {
            $lines[] = self::GRAY . '  php error type: ' . $report->phpError['type'] . self::RESET;
        }

        if ($report->meta !== []) {
            $lines[] = self::GRAY . '  meta: ' . \json_encode($report->meta, JSON_UNESCAPED_UNICODE) . self::RESET;
        }

        $lines[] = '';
        $lines[] = self::CYAN . 'Stack trace:' . self::RESET;

        foreach ($report->trace as $index => $frame) {
            $lines[] = $this->formatFrame($index, $frame);
        }

        if ($report->dumps !== []) {
            $lines[] = '';
            $lines[] = self::CYAN . 'Dumps: ' . \implode(', ', \array_keys($report->dumps)) . self::RESET;
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

        return self::GRAY . \sprintf('#%d %s %s()', $index, $location, $callable) . self::RESET;
    }
}
