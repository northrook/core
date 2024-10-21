<?php

declare(strict_types=1);

namespace Northrook\Exception;

use JetBrains\PhpStorm\Deprecated;
use LogicException;

/**
 * The exception occurred during Component compilation.
 */
#[Deprecated]
class ClassStructureException extends LogicException
{
    /**
     * Construct the exception. Note: The message is NOT binary safe.
     * @link https://php.net/manual/en/exception.construct.php
     *
     * @param string      $message [optional] The Exception message to throw
     * @param null|string $string
     * @param int         $code    [optional] The Exception code
     */
    public function __construct(
        string                  $message = '',
        public readonly ?string $string = null,
        int                     $code = 422,
    ) {
        dd( $this::class.' is deprecated' );
        parent::__construct( $message, $code );
        $this->message = $message;
        $this->code    = $code;
    }
}
