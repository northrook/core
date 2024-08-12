<?php

namespace Acme\Tests\Benchmark;

use Northrook\Settings;
use PhpBench\Attributes\{BeforeMethods, Iterations, RetryThreshold, Revs};

#[BeforeMethods( 'setUp' )]
#[Revs( 128 )]
#[Iterations( 50 )]
#[RetryThreshold( 2 )]
class TrieArrayBench
{
    const KEYS = [
        'dir.root',
        'dir.var',
        'dir.cache',
        'dir.storage',
        'dir.uploads',
        'dir.assets',
        'dir.public',
        'dir.public.assets',
        'dir.public.uploads',
    ];

    const PARAMETERS = [
        'dir.root'               => 'C:\laragon\www\northrook-dev',
        'dir.var'                => 'C:\laragon\www\northrook-dev\var',
        'dir.cache'              => 'C:\laragon\www\northrook-dev\var\cache',
        'dir.cache.latte'        => 'C:\laragon\www\northrook-dev\var\cache\dev\latte',
        'dir.manifest'           => 'C:\laragon\www\northrook-dev\var\manifest',
        'dir.config'             => 'C:\laragon\www\northrook-dev\config',
        'dir.src'                => 'C:\laragon\www\northrook-dev\src',
        'dir.assets'             => 'C:\laragon\www\northrook-dev\assets',
        'dir.public'             => 'C:\laragon\www\northrook-dev\public',
        'dir.templates'          => 'C:\laragon\www\northrook-dev\templates',
        'dir.core.templates'     => 'C:\laragon\www\northrook-dev\vendor\northrook\symfony-core-bundle\templates',
        'dir.public.assets'      => 'C:\laragon\www\northrook-dev\public\assets',
        'dir.core.assets'        => 'C:\laragon\www\northrook-dev\vendor\northrook\symfony-core-bundle\assets',
        'dir.asset.storage'      => 'C:\laragon\www\northrook-dev\var\assets',
        'path.public.stylesheet' => 'C:\laragon\www\northrook-dev\assets\stylesheet.css',
        'path.admin.stylesheet'  => 'C:\laragon\www\northrook-dev\assets\admin.css',
    ];


    public readonly Settings $settings;

    private function randomArray() : array {

        $array = [];

        for ( $i = 0; $i < 256; $i++ ) {

            $key = 'key' . $i;

            if ( \str_contains( $i, '1' ) ) {
                $key = 'dir.private.' . base64_encode( $i );
            }
            elseif ( \str_contains( $i, '2' ) ) {
                $key = 'path.public.' . base64_encode( $i );
            }
            elseif ( \str_contains( $i, '3' ) ) {
                $key = 'dir.asset.' . base64_encode( $i );
            }
            elseif ( \str_contains( $i, '4' ) ) {
                $key = 'test.string' . base64_encode( $i );
            }
            elseif ( \str_contains( $i, '5' ) ) {
                $key = 'key.' . base64_encode( $i );
            }
            elseif ( \str_contains( $i, '6' ) ) {
                $key = 'banana.' . base64_encode( $i );
            }
            elseif ( \str_contains( $i, '7' ) ) {
                $key = 'other.' . base64_encode( $i );
            }
            elseif ( \str_contains( $i, '8' ) ) {
                $key = 'noise.that.might.take.a.minute' . base64_encode( $i );
            }
            elseif ( \str_contains( $i, '9' ) ) {
                $key = 'invalid.' . base64_encode( $i );
            }
            elseif ( \str_contains( $i, '0' ) ) {
                $key = 'juice.' . base64_encode( $i );
            }

            $array[ $key ] = base64_encode( (string) random_bytes( (int) ( 1 + $i * 1.1 ) ) );
        }

        $array = array_merge(
            $this::PARAMETERS,
            $array,
        );


        shuffle( $array );
        return $array;
    }

    public function setUp() {


        // var_dump( $this->randomArray() );

        $this->settings = new Settings(
            $this->randomArray(),
        // false,
        );

    }

    public function benchUsing_in_array() {
        $url = $this::PARAMETERS;
        shuffle( $url );
        foreach ( $url as $value ) {
            Settings::set( $value, __METHOD__, true );
        }
    }

    public function benchUsing_trie() {
        $url = $this::PARAMETERS;
        shuffle( $url );
        foreach ( $url as $value ) {
            Settings::set( $value, __METHOD__ );
        }
    }
}