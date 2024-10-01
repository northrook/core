<?php

declare(strict_types=1);

namespace Northrook\Exception;

use RuntimeException;
use Throwable;

final class ValueError extends RuntimeException
{
    public function __construct(
        string           $message = '',
        protected string $file = '',
        protected int    $line = 0,
        int              $code = 0,
        ?Throwable       $previous = null,
    ) {
        if ( ! $this->file ) {
            $this->file = \debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS )[0]['file'];
        }
        if ( ! $this->line ) {
            $this->line = \debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS )[0]['line'];
        }
        parent::__construct( $message, $code, $previous );
    }
}
