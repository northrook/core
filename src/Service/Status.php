<?php

namespace Nortkrook\Core\Service;

use JetBrains\PhpStorm\ExpectedValues;
use Northrook\Logger\Debug;
use Northrook\Logger\Log;
use Northrook\Logger\Log\Level;


/**
 * @property string      $set
 * @property-read bool   $success
 * @property-read string $status
 *
 * @property string      $message
 */
class Status
{
    private const STATUS = [ 'success', 'error', 'warning', 'notice', 'info' ];

    public const SUCCESS = 'success';
    public const ERROR   = 'error';
    public const WARNING = 'warning';
    public const NOTICE  = 'notice';
    public const INFO    = 'info';

    private array  $actions = [];
    public ?string $message = null;

    public readonly string $id;

    public function __construct(
        ?string        $id = null,
        private string $status = 'uninitialized',
    ) {
        $this->id = $id ?? Debug::backtrace()->getCaller();
    }


    /**
     * * Status is considered as {@see Status::$success} if set as `success|notice|info`.
     *
     * @param string  $name
     *
     * @return bool|string
     */
    public function __get( string $name ) : bool | string {
        return match ( strtolower( $name ) ) {
            'success' => in_array( $name, [ Status::SUCCESS, Status::NOTICE, Status::INFO ], true ),
            'status'  => $this->status,
            default   => false
        };
    }


    /**
     * Update the {@see Status::$status} with {@see $set}.
     *
     * @param string  $name
     * @param string  $value
     *
     * @return void
     */
    public function __set(
        string $name,
        #[ExpectedValues( "success", "error", "warning", "notice", "info" )]
        string $value,
    ) : void {
        $name = strtolower( $name );
        if ( 'set' === $name ) {
            if ( !in_array( $value, Status::STATUS, true ) ) {
                Log::Warning(
                    'Unexpected status {status} set in {id}.',
                    [ 'id' => $this->id, 'status' => $value ],
                );
            }
            $this->status = $value;
        }
        else {
            Log::Error(
                'Attempting to set {value} as unknown property {name}, in {id}.',
                [ 'name' => $name, 'value' => $value, 'id' => $this->id ],
            );
        }
    }


    public function __isset( string $name ) : bool {
        return isset( $this->$name );
    }

    public function setAction(
        string $name,
        #[ExpectedValues( "success", "error", "warning", "notice", "info" )]
        string $status,
    ) : self {
        $this->actions[ $name ] = $status;
        return $this;
    }

    public function hasAction( string $action ) : bool {
        return isset( $this->actions[ $action ] );
    }

    public function getAction( string $action ) : ?string {
        return $this->actions[ $action ] ?? null;
    }

    public function getReport( bool $asArray ) : string | array{
        $report = [];

        foreach ( $this->actions as $action => $status ) {
            $report[] = "<span class=\"action\">$action</span><span class=\"status {$status}\">$status</span>";

        }

        return $asArray ? implode( '', $report ) : $report;
    }

}