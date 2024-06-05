<?php

namespace Northrook\Core\Process;

use Northrook\Core\Attribute\ExitPoint;
use Northrook\Core\Trait\PropertyAccessor;

/**
 * @template Timestamp of int
 * @template Milliseconds of float
 *
 * @property-read bool $completed
 *
 * A simple result object.
 */
final class Step
{
    use PropertyAccessor;

    /** @var int<Timestamp> */
    private readonly int $timestamp;

    private ?bool $completed = null;

    /** @var array<string,array{time:int<Timestamp>,message:string}> */
    private array $messages = [];

    public readonly string  $name;
    public readonly ?string $message;
    public readonly string  $status; // success | error | warning | notice | info

    /** @var float<Milliseconds> Total time in milliseconds */
    public readonly float $completedMs;

    public function __construct(
        string  $name,
        ?string $message = null,
    ) {
        $this->name      = $name;
        $this->timestamp = (int) hrtime( true );
    }

    public function __get( string $property ) : bool | string {
        return $this->$property;
    }

    /**
     * Add a message to the step.
     *
     * - Each message provides a timestamp since the step was started.
     *
     * @param string  $string
     *
     * @return self
     */
    public function message( string $string ) : self {
        $this->messages[] = [
            'time'    => $this->timestamp(),
            'message' => $string,
        ];
        return $this;
    }

    /**
     * End the step and set the message.
     *
     * @param ?string      $status  = Status::LEVEL[ $any ]
     * @param null|string  $message
     *
     * @return self
     */
    #[ExitPoint]
    public function end( ?string $status = null, ?string $message = null ) : self {

        if ( $this->completed !== null ) {
            return $this;
        }

        $this->completed   = $status === 'success';
        $this->status      = $status;
        $this->message     = $message ?? end( $this->messages )[ 'message' ] ?: null;
        $this->completedMs = $this->timestamp();
        return $this;
    }

    /**
     * Get the total time since Step was initialized in milliseconds.
     *
     * @return float<Milliseconds>
     */
    private function timestamp() : float {
        $timestamp = hrtime( true ) - $this->timestamp;
        return ltrim( number_format( $timestamp / 1_000_000, 3 ), '0' );
    }
}