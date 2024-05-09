<?php

namespace Northrook\Core\Interface;

interface Printable extends \Stringable
{
    /**
     * Prints the resulting HTML, or null if the element is not printable.
     *
     * - Must handle all parsing, optimization, escaping, and encoding.
     *
     *  ```
     *  // example
     *
     *  public function __toString() : string {
     *      return trim( $this->build() )
     * }
     *
     *  public function print() : string {
     *      return $this->__toString();
     *  }
     *  ```
     *
     * @return string
     */
    public function print() : string;
}