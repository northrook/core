<?php

namespace Acme\Tests\Benchmark;

use Northrook\Env;
use PhpBench\Attributes\{Iterations, Revs};

#[Revs( 1280 )]
#[Iterations( 50 )]
class StaticCachedBench
{
    public function benchRaw() {
        return Env::isCLI();
    }

    public function benchCached() {
        return Env::isCLIcached();
    }
}