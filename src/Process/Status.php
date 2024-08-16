<?php

declare( strict_types = 1 );

namespace Northrook\Process;

use Northrook\Attribute\ExitPoint;
use Northrook\Trait\PropertyAccessor;
use Symfony\Component\Stopwatch\Stopwatch;

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
        'info'    => [],
    ];


    /**
     * @param null|string|class-string  $name           Provide a name for the instance, or null to guess from the caller
     * @param ?Stopwatch                $stopwatch      Pass in a Stopwatch to use, or null to instantiate a new one
     * @param bool                      $morePrecision  Only applies to self-instantiated {@see Stopwatch}
     */
    public function __construct(
        private ?string    $name = null,
        private ?Stopwatch $stopwatch = null,
        bool               $morePrecision = false,
    ) {
        \trigger_deprecation(
            'northrook\core',
            'dev',
            $this::class . ' is deprecated.',
        );
        // Use provided name, or guess the name from the caller
        $this->name ??= debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 )[ 1 ][ 'class' ]
                        ?? throw new \LogicException( 'No name provided for ' . Status::class . ' instance.' );

        // Use provided stopwatch, or create a new one
        $this->stopwatch ??= new Stopwatch( $morePrecision );

        // Open a new section, to be closed in the report
        $this->stopwatch->openSection();
    }

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

        $step = $this->steps[ $name ] ?? new Step( $name, $message, $this->stopwatch, $this->name );

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


        foreach ( $this->steps as $step ) {
            $this->status = ( $step->end() )->status;

            if ( $this->status === 'success' ) {
                continue;
            }

            $this->report[ $this->status ][ $step->name ] = $step;
        }

        $report = [
            'error'   => count( $this->report[ 'error' ] ),
            'warning' => count( $this->report[ 'warning' ] ),
            'notice'  => count( $this->report[ 'notice' ] ),
        ];


        echo '<pre>';
        echo 'Report:' . PHP_EOL;
        foreach ( $report as $level => $count ) {
            if ( $count === 0 ) {
                unset( $report[ $level ] );
                continue;
            }
            $report[ $level ] = $count === 1 ? "$count $level" : "$count {$level}s";
            echo $level . ':' . $report[ $level ] . PHP_EOL;
        }
        echo '</pre>';

        if ( $this->status === 'success' && count( $report ) === 0 ) {
            $this->report[ 'message' ] = 'All steps completed successfully.';
        }
        elseif ( $this->status === 'error' ) {
            $this->report[ 'message' ] =
                "The $this->name failed, did not complete successfully, reporting " . implode( ', ', $report ) . '.';
        }
        else {
            $this->report[ 'message' ] = "The $this->name completed with " . implode( ', ', $report ) . '.';
        }
        $this->stopwatch->stopSection( "Status:$this->name" );
        return $this;
    }

}