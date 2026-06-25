<?php

declare(strict_types=1);

namespace Northrook\ErrorHandler;

use Northrook\Contracts\ErrorHandler\ErrorReport;
use Northrook\Contracts\Interfaces\ErrorRendererInterface;
use Northrook\Core;

final readonly class CompositeErrorRenderer implements ErrorRendererInterface
{
    /** @var ErrorRendererInterface[] */
    private array $renderers;

    /**
     * @param null|ErrorRendererInterface[] $renderers
     */
    public function __construct(
        null|array $renderers = null,
    ) {
        $this->renderers =
            $renderers
            ?? (
                Core::isCli()
                    ? [new CliErrorRenderer(), new JsonErrorRenderer()]
                    : [new HtmlErrorRenderer(), new JsonErrorRenderer()]
            );
    }

    /**
     * @throws \JsonException
     */
    public function render(
        ErrorReport $report,
    ): string {
        foreach ($this->renderers as $renderer) {
            if ($renderer->supports($report)) {
                return $renderer->render($report);
            }
        }

        return new JsonErrorRenderer()->render($report);
    }

    public function supports(
        ErrorReport $report,
    ): bool {
        return true;
    }
}
