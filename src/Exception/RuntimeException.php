<?php

declare(strict_types=1);

namespace Core\Exception;

use Throwable;
use function Support\get_throw_call;

abstract class RuntimeException extends \RuntimeException
{
    public readonly string $caller;

    public function __construct(
        ?string    $message,
        ?Throwable $previous = null,
    ) {
        $this->caller = get_throw_call() ?? $this->getTraceAsString();

        parent::__construct(
            $message ?? 'A recoverable runtime error occurred.',
            E_RECOVERABLE_ERROR,
            $previous ?? ErrorException::getLast(),
        );
    }
}
