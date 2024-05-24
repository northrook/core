<?php

namespace Northrook\Core\Support;

final class Arr
{


    // -- STATIC METHODS ----------------------------------------------------------------------------

    /**
     * Get a boolean option from an array of options.
     *
     * ⚠️ Be careful if passing other nullable values, as they will be converted to booleans.
     *
     * - Pass an array of options, `get_defined_vars()` is recommended.
     * - All 'nullable' values will be converted to booleans.
     * - `true` options set all others to false.
     * - `false` options set all others to true.
     * - Use the `$default` parameter to set value for all if none are set.
     *
     * @param array  $array    Array of options, `get_defined_vars()` is recommended
     * @param bool   $default  Default value for all options
     *
     * @return array<string, bool>
     */
    public static function booleanValues( array $array, bool $default = true ) : array {

        // Isolate the options
        $array = array_filter( $array, static fn ( $value ) => is_bool( $value ) || is_null( $value ) );

        // If any option is true, set all others to false
        if ( in_array( true, $array, true ) ) {
            return array_map( static fn ( $option ) => $option === true, $array );
        }

        // If any option is false, set all others to true
        if ( in_array( false, $array, true ) ) {
            return array_map( static fn ( $option ) => $option !== false, $array );
        }

        // If none are true or false, set all to the default
        return array_map( static fn ( $option ) => $default, $array );
    }
}