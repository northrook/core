<?php

use Exceptions\InvalidPropertyException;
use Exceptions\MissingPropertyException;

/**
 * @property Status $status
 */
trait CoreServiceTrait
{

    /**
     * @throws MissingPropertyException
     * @throws InvalidPropertyException
     */
    final public function getStatus() : Status {

        if ( !property_exists( $this, 'status' ) ) {
            throw new MissingPropertyException( Status::class );
        }

        if ( !isset( $this->status )) {
            $this->status = new Status();
        }

        if ( !$this->status instanceof Status ) {
            throw new InvalidPropertyException(
                $this->status::class,
                Status::class,
            );
        }

        return $this->status;
    }
}