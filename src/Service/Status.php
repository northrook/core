<?php

namespace Northrook\Core\Service;

use JetBrains\PhpStorm\ExpectedValues;
use Northrook\Core\Debug\Backtrace;


/**
 * @property-read string  $status
 * @property-read bool    $success
 * @property-read  string $message
 *
 */
class Status
{
    private const STATUS = [ 'success', 'error', 'warning', 'notice', 'info' ];

    public const SUCCESS = 'success';
    public const ERROR   = 'error';
    public const WARNING = 'warning';
    public const NOTICE  = 'notice';
    public const INFO    = 'info';

    private array $actions  = [];
    private array $messages = [
        'uninitialized' => null,
        'success'       => null,
        'error'         => null,
        'warning'       => null,
        'notice'        => null,
        'info'          => null,
    ];

    public readonly string $id;

    public function __construct(
        array          $messages = [],
        ?string        $id = null,
        private string $status = 'uninitialized',
    ) {
        $this->id = $id ?? Backtrace::get()->caller;
        $this->setMessages( $messages );
    }

    /**
     * {@see Status::$status} is considered as {@see Status::$success} if set as `success|notice|info`.
     *
     * @param string  $name
     *
     * @return bool|string
     */
    public function __get( string $name ) : bool | string {
        return match ( strtolower( $name ) ) {
            'success' => in_array( $name, [ Status::SUCCESS, Status::NOTICE, Status::INFO ], true ),
            'status'  => $this->status,
            'message' => $this->getMessage( $this->status ),
            default   => false
        };
    }

    /**
     * {@see Status::__set} is not supported.
     *
     * @param string  $name
     * @param mixed   $value
     *
     * @return void
     */
    public function __set( string $name, mixed $value ) : void {
        trigger_error( Status::class . '::__set() is not supported.', E_USER_NOTICE );
    }

    /**
     * Check if a given property is set.
     *
     * @param string  $name
     *
     * @return bool
     */
    public function __isset( string $name ) : bool {
        return isset( $this->$name );
    }

    private function setMessages( array $messages ) : void {
        $caller         = Backtrace::get()->getClass();
        $this->messages = array_merge(
            [
                'uninitialized' => "Status for $caller is uninitialized",
                'success'       => "$caller successful",
                'error'         => "$caller halted due to an error",
                'warning'       => "$caller completed with a warning",
                'notice'        => null,
                'info'          => null,
            ], $messages,
        );

    }

    private function getMessage(
        ?string $for = null,
    ) : ?string {
        return $this->messages[ $for ?? $this->status ] ?? null;
    }

    /**
     * Update the {@see Status::$status} with {@see $set}.
     *
     * @param string  $status
     *
     * @return Status
     */
    public function set(
        #[ExpectedValues( self::STATUS )]
        string $status,
    ) : Status {
        $this->status = $status;

        return $this;
    }


    public function addAction(
        string $name,
        #[ExpectedValues( self::STATUS )]
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

    public function getReport( bool $asArray = false ) : string | array {
        $report = [];

        foreach ( $this->actions as $action => $status ) {
            $report[] = "<span class=\"action\">$action</span><span class=\"status {$status}\">$status</span>";

        }

        return $asArray ? implode( '', $report ) : $report;
    }

}