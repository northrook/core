<?php

namespace Acme\Tests\Benchmark;

use PhpBench\Attributes\{Iterations, Revs};
use function Northrook\escapeUrl;
use function Northrook\filterUrl;

#[Revs( 128 )]
#[Iterations( 5 )]
class FrozenKeysBench
{
    const STRING = 'core.local, Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:127.0) Gecko/20100101 Firefox/127.0, text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8, en-GB,en;q=0.5, gzip, deflate, keep-alive, 1, u=1, C:\Program Files\Alacritty\;C:\Program Files\Lapce\;C:\Windows\system32;C:\Windows;C:\Windows\System32\Wbem;C:\Windows\System32\WindowsPowerShell\v1.0\;C:\Windows\System32\OpenSSH\;C:\Program Files (x86)\NVIDIA Corporation\PhysX\Common;C:\Program Files\NVIDIA Corporation\NVIDIA NvDLISR;C:\Program Files (x86)\dotnet\;C:\Program Files\dotnet\;C:\WINDOWS\system32;C:\WINDOWS;C:\WINDOWS\System32\Wbem;C:\WINDOWS\System32\WindowsPowerShell\v1.0\;C:\WINDOWS\System32\OpenSSH\;C:\Program Files\PuTTY\;C:\ProgramData\chocolatey\bin;C:\Program Files\Docker\Docker\resources\bin;C:\laragon\bin\php\php-8.2.10-Win32-vs16-x64;C:\ProgramData\ComposerSetup\bin;C:\Program Files\nodejs\;C:\Program Files (x86)\ZeroTier\One\;C:\Program Files\Git\cmd;D:\_dev\ttfautohint;C:\Users\martin\AppData\Local\pnpm;C:\laragon\bin;C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin;C:\laragon\bin\composer;C:\laragon\bin\git\bin;C:\laragon\bin\git\cmd;C:\laragon\bin\git\mingw64\bin;C:\laragon\bin\git\usr\bin;C:\laragon\bin\laragon\utils;C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin;C:\laragon\bin\nginx\nginx-1.22.0;C:\laragon\bin\ngrok;C:\laragon\bin\nodejs\node-v18;C:\laragon\bin\notepad++;C:\laragon\bin\php\php-8.2.10-Win32-vs16-x64;C:\laragon\bin\python\python-3.10;C:\laragon\bin\python\python-3.10\Scripts;C:\laragon\bin\redis\redis-x64-5.0.14.1;C:\laragon\bin\telnet;C:\laragon\usr\bin;C:\Users\martin\AppData\Local\Yarn\config\global\node_modules\.bin;C:\Users\martin\AppData\Roaming\Composer\vendor\bin;C:\Users\martin\AppData\Roaming\npm;C:\Users\martin\scoop\shims;C:\Users\martin\AppData\Local\Microsoft\WindowsApps;C:\Program Files\nodejs;C:\Users\martin\AppData\Local\GitHubDesktop\bin;C:\Users\martin\AppData\Local\JetBrains\Toolbox\scripts;C:\Users\martin\AppData\Local\Programs\VSCodium\bin;C:\Program Files\JetBrains\PhpStorm 2022.2.5\bin;;D:\_dev\ttfautohint;, C:\WINDOWS, C:\WINDOWS\system32\cmd.exe, .COM;.EXE;.BAT;.CMD;.VBS;.VBE;.JS;.JSE;.WSF;.WSH;.MSC, C:\WINDOWS, , Apache/2.4.54 (Win64) OpenSSL/1.1.1q PHP/8.2.10, core.local, ::1, 80, ::1, C:/laragon/www/core, http, , C:/laragon/www/core, admin@example.com, C:/laragon/www/core/index.php, 14022, CGI/1.1, HTTP/1.1, GET, , /, /index.php, /index.php, 1721368971.3065, 1721368971';

    const URL_TYPE = [
        "http://example.com/<script>alert('XSS')</script>",
        "javascript:alert('XSS')",
        "http://example.com/?param=<img src='x' onerror='alert(1)'>",
        "http://example.com/?q=<script>document.location='http://badsite.com'</script>",
        "normal@example.com",
        "test@example.com<script>alert('XSS')</script>",
        "xss@example.com<img src='x' onerror='alert(1)'>",
        "<script>alert('email')</script>@example.com",
    ];

    public function benchFilterUrl() {
        $url = $this::URL_TYPE;
        shuffle( $url );
        foreach ( $url as $value ) {
            filterUrl( $value );
        }
    }

    public function benchEscapeUrl() {
        $url = $this::URL_TYPE;
        shuffle( $url );
        foreach ( $url as $value ) {
            escapeUrl( $value );
        }
    }
}