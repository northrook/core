<?php

declare( strict_types = 1 );

namespace Northrook\Core\Process;

use Northrook\Core\Attribute\ExitPoint;
use Northrook\Core\Trait\PropertyAccessor;

/**
 * A simple result object.
 *
 * @property-read mixed  $value
 * @property-read bool   $completed
 * @property-read string $step
 * @property-read array  $steps
 *
 * @author  Martin Nielsen <mn@northrook.com>
 *
 * @link    https://github.com/northrook/core
 */
final class Status
{
    use PropertyAccessor;

    private const STATUS = [ 'uninitialized', 'processing', 'completed' ];

    public const LEVEL = [ 'success', 'error', 'warning', 'notice', 'info' ];

    public const SUCCESS = 'success';
    public const ERROR   = 'error';
    public const WARNING = 'warning';
    public const NOTICE  = 'notice';
    public const INFO    = 'info';

    private string $status = 'uninitialized'; // uninitialized | $step | $status::LEVEL[ $any ]

    /** @var array<string,Step> */
    private array $steps = [];

    private array $report = [
        'message' => null,
        'error'   => [],
        'warning' => [],
        'notice'  => [],
        'infos'   => [],
    ];

    /**
     * @param string|class-string  $service
     */
    public function __construct(
        public readonly string $service,
        private readonly mixed $value = null,
    ) {}

    public function __get( string $property ) : string | array | bool | null {
        return match ( $property ) {
            'step'      => $this->status,                 // Get the current step
            'steps'     => $this->steps,                  // Get all steps
            'value'     => $this->value,                  // Get the value, if any
            'completed' => $this->status === 'completed', // Check if the process is completed
            default     => null
        };
    }

    public function step( string $name, ?string $message = null ) : Step {

        $this->status = $name;

        $step = $this->steps[ $name ] ?? new Step( $name );

        if ( $message ) {
            $step->message( $message );
        }

        return $this->steps[ $name ] = $step;
    }

    /**
     * Consider the Process as completed.
     *
     * Run a report and set the status accordingly.
     *
     * @return self
     */
    #[ExitPoint]
    public function report() : self {

        $status = null;

        foreach ( $this->steps as $step ) {
            $status = ( $step->end( 'success' ) )->status;

            if ( $status === 'success' ) {
                continue;
            }

            $this->report[ $status ][ $step->name ] = $step;


            // if ( !( $step->end( 'success' ) )->completed ) {
            //     $this->status = $step->status;
            // };
        }

        $report = [
            'error'   => count( $this->report[ 'error' ] ),
            'warning' => count( $this->report[ 'warning' ] ),
            'notice'  => count( $this->report[ 'notice' ] ),
        ];

        foreach ( $report as $level => $count ) {
            if ( $count === 0 ) {
                unset( $report[ $level ] );
                continue;
            }
            $report[ $level ] = $count === 1 ? "$count $level" : "$count {$level}s";
        }

        if ( $status === 'success' && count( $report ) === 0 ) {
            $this->report[ 'message' ] = 'All steps completed successfully.';
        }
        elseif ( $status === 'error' ) {
            $this->report[ 'message' ] =
                "The $this->service failed, did not complete successfully, reporting " . implode( ', ', $report ) . '.';
        }
        else {
            $this->report[ 'message' ] = "The $this->service completed with " . implode( ', ', $report ) . '.';
        }


        return $this;
    }

}