<?php

namespace Northrook\Core\Process;

use Northrook\Core\Attribute\ExitPoint;
use Northrook\Trait\PropertyAccessor;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @template Timestamp of int
 * @template Milliseconds of float
 *
 * @property-read string $status
 * @property-read string $message
 * @property-read bool   $completed
 *
 * A simple result object.
 */
final class Step
{
    use PropertyAccessor;

    private ?bool $completed = null;

    /** @var array<string,array{time:int<Timestamp>,message:string}> */
    private array           $messages = [];
    private readonly string $status;

    /** @var float<Milliseconds> Total time in milliseconds */
    private readonly float $completedMs;

    public readonly string $name;

    /**
     * @param string               $name
     * @param null|string          $message
     * @param Stopwatch            $stopwatch
     * @param string|class-string  $section
     */
    public function __construct(
        string                     $name,
        ?string                    $message,
        private readonly Stopwatch $stopwatch,
        private readonly string    $section,
    ) {
        $this->name = $name;
        $this->stopwatch->start( $name, $this->section );

        if ( $message ) {
            $this->message( $message );
        }
    }

    public function __get( string $property ) : null | bool | string | float {
        return match ( $property ) {
            'status'      => $this->status ?? "$this->name is not yet completed",
            'completed'   => $this->completed ?? false,
            'message'     => end( $this->messages )[ 'message' ] ?? null,
            'completedMs' => $this->completedMs ?? null,
            default       => false
        };
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
        $period           = $this->stopwatch->lap( $this->name )->getPeriods();
        $this->messages[] = [
            'time'    => end( $period ),
            'message' => $string,
        ];
        return $this;
    }

    /**
     * End the step and set the message.
     *
     * @param ?string      $status  = [ 'success', 'error', 'warning', 'notice', 'info' ][ $any ]
     * @param null|string  $message
     *
     * @return self
     */
    #[ExitPoint]
    public function end( ?string $status = null, ?string $message = null ) : self {

        if ( $this->completed !== null ) {
            return $this;
        }

        if ( $message ) {
            $this->message( $message );
        }

        $this->completed   = $status ??= 'success';
        $this->status      = $status;
        $this->completedMs = $this->stopwatch->stop( $this->name )->getDuration();
        return $this;
    }
}