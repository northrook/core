<?php

namespace Northrook\Core\Cache;

use Northrook\Logger\Log;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;

final readonly class StaticArrayCache
{

    public PhpArrayAdapter $adapter;

    public function __construct(
        private string           $file,
        private AdapterInterface $fallback,
    ) {
        $this->adapter = new PhpArrayAdapter(
            $this->file,
            $this->fallback,
        );
    }
    
    public function get( string $key ) : mixed {
        try {
            return $this->adapter->getItem( $key )->get();
        }
        catch ( InvalidArgumentException $e ) {
            Log::error( $e->getMessage() );
            return null;
        }
    }

    public function has( $key ) : bool {
        try {
            return $this->adapter->hasItem( $key );
        }
        catch ( InvalidArgumentException $e ) {
            Log::error( $e->getMessage() );
            return false;
        }
    }

}