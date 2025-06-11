<?php

namespace Core\Exception;

use LogicException;
use Throwable;
use function Support\{cli_format, is_cli};

final class CompilerException extends LogicException
{
    public function __construct(
        string                 $message = '',
        public readonly string $label = 'CompilerException',
        ?Throwable             $previous = null,
    ) {
        parent::__construct(
            $message,
            E_RECOVERABLE_ERROR,
            $previous ?? ErrorException::getLast(),
        );
    }

    public static function error(
        string $message,
        string $label = 'Error',
        bool   $continue = false,
    ) : void {
        if ( is_cli() ) {
            echo cli_format(
                ' '.\trim( $label ).'   ',
                'black',
                'bg_red',
                'bold',
            )." {$message}\n";
        }

        if ( $continue === false ) {
            throw new CompilerException( $message, $label );
        }
    }

    public static function warning(
        string $message,
        string $label = 'Warning',
        bool   $continue = false,
    ) : void {
        if ( is_cli() ) {
            echo cli_format(
                ' '.\trim( $label ).' ',
                'yellow',
                'bg_black',
                'bold',
            )." {$message}\n";
        }

        if ( $continue === false ) {
            throw new CompilerException( $message, $label );
        }
    }
}
