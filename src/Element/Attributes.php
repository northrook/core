<?php

namespace Northrook\Core\Element;

use Northrook\Core\Support\Arr;
use Northrook\Core\Support\Sort;

final class Attributes
{

    public static function classes( string | array $classes ) : array {

        if ( !is_array( $classes ) ) {
            $classes = array_filter( explode( ' ', $classes ) );
        }

        return Arr::unique( $classes );
    }

    public static function inlineStyles( string | array $styles ) : array {

        if ( !is_array( $styles ) ) {
            $styles = explode( ';', $styles );
        }

        foreach ( $styles as $key => $style ) {
            $styles[ $key ] = "$key: $style;";
        }

        return $styles;
    }

    public static function from( array $content, bool $raw = false ) : array {

        $attributes = [];

        foreach ( $content as $attribute => $value ) {

            $attribute = strtolower( $attribute );

            if ( 'id' === $attribute && !$value ) {
                continue;
            }

            if ( 'class' === $attribute ) {
                $value = Attributes::classes( $value );
            }

            if ( 'style' === $attribute ) {
                $value = Attributes::inlineStyles( $value );
            }

            $value = match ( gettype( $value ) ) {
                'string'  => $value,
                'boolean' => $value ? 'true' : 'false',
                'array'   => implode( ' ', array_filter( $value ) ),
                'object'  => method_exists( $value, '__toString' ) ? $value->__toString() : null,
                'NULL'    => null,
                default   => (string) $value,
            };

            if ( in_array( $attribute, [ 'disabled', 'readonly', 'required', 'checked', 'hidden' ] ) ) {
                $value = null;
            }

            if ( $raw ) {
                $attributes[ $attribute ] = $value;
                continue;
            }

            $attributes[ $attribute ] = ( null === $value ) ? $attribute : "$attribute=\"$value\"";
        }

        return $raw ? $attributes : Sort::elementAttributes( $attributes );
    }
}