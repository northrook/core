<?php

namespace Northrook\Core\Service;

use Northrook\Core\Exception as Core;

/**
 * @property ?Status $status
 */
trait CoreServiceTrait
{

    /**
     * @throws Core\MissingPropertyException
     * @throws Core\InvalidPropertyException
     */
    final public function getStatus() : Status {

        if ( !property_exists( $this, 'status' ) ) {
            throw new Core\MissingPropertyException( 'status' );
        }

        if ( !( $this->status ?? null ) instanceof Status ) {
            throw new Core\InvalidPropertyException(
                $this->status ?? null,
                Status::class,
            );
        }

        return $this->status;
    }
}