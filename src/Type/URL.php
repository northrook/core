<?php

namespace Northrook\Core\Type;

use Northrook\Core\Exception\InvalidTypeException;
use Northrook\Core\Interface\Validated;
use Northrook\Core\Type;

/**
 * @property string $value
 * @property bool   $exists
 * @property bool   $isValid
 * @property bool   $isExternal
 */
final class URL extends Type implements Validated
{
    private string                 $value;
    private readonly array | false $headers;
    private ?bool                  $isExternal = null;
    private bool                   $isValid;

    public function __construct(
        string | URL             $value,
        private readonly ?string $scheme = 'https',
        private readonly ?string $host = null,
        private readonly bool    $strict = false,
    ) {
        $this->value = $value instanceof URL ? $value->value : $value;
    }

    public function __toString() : string {

        $this->validate();

        if ( !$this->isValid && $this->strict ) {
            throw new InvalidTypeException( 'The URL is not valid.', $this->value );
        }

        return $this->value;
    }

    public function __get( string $name ) {
        return match ( $name ) {
            'value'      => $this->value,
            'exists'     => $this->getUrlStatus(),
            'isValid'    => $this->validate(),
            'isExternal' => $this->isUrlExternal(),
            default      => null,
        };
    }

    public function validate() : bool {

        if ( isset( $this->isValid ) ) {
            return $this->isValid;
        }

        // Validate defined scheme
        if ( $this->scheme && !str_starts_with( $this->value, $this->scheme . '://' ) ) {
            return false;
        }

        // A URL must contain a scheme, and a host somewhere
        if ( !( str_contains( $this->value, "//" ) && str_contains( $this->value, '.' ) ) ) {
            return false;
        };

        $url = filter_var( $this->value, FILTER_VALIDATE_URL );

        if ( $url === false ) {
            return $this->isValid = false;
        }

        $this->value = $url;

        $this->isUrlExternal();

        if ( $this->isExternal ) {
            $headers = get_headers( $this->value, 1 );

            if ( false === $headers || false === str_contains( $headers[ 0 ], '200' ) ) {
                return $this->isValid = false;
            }

            return $this->isValid = true;
        }

        return $this->isValid = $this->getUrlStatus();
    }

    private function getUrlStatus() : bool {

        $this->headers ??= get_headers( $this->value, true );

        if ( false === $this->headers ) {
            return false;
        }

        if ( str_contains( $this->headers[ 0 ], '200' ) ) {
            return true;
        }

        return false;
    }


    private function isUrlExternal() : ?bool {

        if ( is_bool( $this->isExternal ) ) {
            return $this->isExternal;
        }

        if ( null === $this->host ) {
            return null;
        }

        $host = parse_url( $this->host, PHP_URL_HOST );
        $url  = parse_url( $this->value, PHP_URL_PATH );

        if ( !$host || !$url ) {
            return null;
        }

        if ( !isset( $host[ 'host' ], $url[ 'host' ] ) ) {
            return null;
        }

        return $this->isExternal = $host[ 'host' ] === $url[ 'host' ];
    }

}