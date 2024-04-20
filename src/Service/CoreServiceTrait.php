<?php

namespace Nortkrook\Core\Service;

use Northrook\Core\Exception as Core;

/**
 * @property Status $status
 */
trait CoreServiceTrait
{

    /**
     * @throws Core\MissingPropertyException
     * @throws Core\InvalidPropertyException
     */
    final public function getStatus() : Status {

        if ( !property_exists( $this, 'status' ) ) {
            throw new Core\MissingPropertyException( Status::class );
        }

        if ( !isset( $this->status )) {
            $this->status = new Status();
        }

        if ( !$this->status instanceof Status ) {
            throw new Core\InvalidPropertyException(
                $this->status::class,
                Status::class,
            );
        }

        return $this->status;
    }
}