<?php

namespace {

    defined( 'CHARSET' )                || define( 'CHARSET', 'UTF-8' );
    defined( 'DIR_SEP' )                || define( 'DIR_SEP', '/' );
    defined( 'TAB' )                    || define( 'TAB', "\t" );
    defined( 'NEWLINE' )                || define( 'NEWLINE', "\n" );
    defined( 'EMPTY_STRING' )           || define( 'EMPTY_STRING', '' );
    defined( 'WHITESPACE' )             || define( 'WHITESPACE', ' ' );
    defined( 'ARRAY_FILTER_USE_VALUE' ) || define( 'ARRAY_FILTER_USE_VALUE', 0 );
}

namespace Support {

    /** Indicates a `default` value will be used unless provided */
    const AUTO = null;

    /** Value is `required`, but can be inferred at runtime if none is provided */
    const INFER = null;

    /** Line Feed  */
    const LF = "\n";
    /** Carriage Return */
    const CR = "\r";
    /** Carriage Return and Line Feed */
    const CRLF = "\r\n";

    const PLACEHOLDER_ARGS   = [[]];
    const PLACEHOLDER_ARG    = [];
    const PLACEHOLDER_ARRAY  = [];
    const PLACEHOLDER_STRING = '';
    const PLACEHOLDER_NULL   = null;
    const PLACEHOLDER_BOOL   = false;
    const PLACEHOLDER_INT    = 0;
    const PLACEHOLDER_FLOAT  = 0;

    /**
     * Log levels, following Monolog and [RFC 5424](https://datatracker.ietf.org/doc/html/rfc5424)
     */
    const LOG_LEVEL = [
        'debug'     => 100,
        'info'      => 200,
        'notice'    => 250,
        'warning'   => 300,
        'error'     => 400,
        'critical'  => 500,
        'alert'     => 550,
        'emergency' => 600,
    ];

    /** Do not cache at all */
    const CACHE_DISABLED = -2;
    /** In-memory runtime cache */
    const CACHE_EPHEMERAL = -1;
    /** Follow Adapter rules */
    const CACHE_AUTO = null;
    /** CNo expiration time */
    const CACHE_FOREVER = 0;

    /** Space */
    const ENCODED_SPACE = '&#32;';
    /** Horizontal Tab `\t` */
    const ENCODED_TAB = '&#9;';
    /** Line Feed `\n` */
    const ENCODED_LF = '&#10;';
    /** Carriage Return `\r` */
    const ENCODED_CR = '&#13;';
    /** Double quote `"` */
    const ENCODED_QUOTE = '&#34;';
    /** Single quote `'` */
    const ENCODED_APOSTROPHE = '&#39;';
    /** Backtick `` ` `` */
    const ENCODED_BACKTICK = '&#96;';
    /** Double quote `#` */
    const ENCODED_HASHTAG = '&#35;';
    /** Dollar `$` */
    const ENCODED_DOLLAR = '&#36;';
    /** Bang|Exclamation `!` */
    const ENCODED_BANG = '&#33;';
    /** Ampersand `&` */
    const ENCODED_AMP = '&#38;';
    /** Equals `=` */
    const ENCODED_EQUALS = '&#61;';
    /** Less-than `<` */
    const ENCODED_LT = '&#60;';
    /** Greater-than `>` */
    const ENCODED_GT = '&#62;';
    /** Slash `/` */
    const ENCODED_SLASH = '&#47;';
    /** Backslash `\` */
    const ENCODED_BACKSLASH = '&#92;';

    // @formatter:off
    const TAG_STRUCTURE = [
        'html', 'head', 'body', 'title', 'style', 'script',
        'link', 'noscript', 'template', 'iframe',
    ];
    const TAG_CONTENT = [
        'header', 'footer', 'aside', 'main', 'section', 'article',
        'div', 'p', 'address', 'blockquote', 'details', 'dialog', 'dl', 'hr',
        // : Media
        'svg', 'canvas', 'object', 'source', 'video', 'audio', 'embed', 'picture',
        'figcaption', 'figure', 'caption', 'pre',
        // : List
        'ol', 'ul', 'li', 'nav', 'dropdown', 'menu', 'modal', 'tooltip',
        // : Form
        'form', 'field', 'fieldset', 'optgroup', 'input', 'label', 'legend',
        'input', 'textarea', 'select', 'option', 'datalist', 'button',
        'progress', 'meter', 'output',
        // : Table
        'table', 'thead', 'tbody', 'tfoot',
        'td', 'th', 'tr', 'col', 'colgroup',
    ];
    const TAG_HEADING = [
        'hgroup', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    ];
    const TAG_INLINE = [
        'a', 'b', 'i', 's', 'em', 'u', 'small', 'strong', 'span',
        'mark', 'code', 'kbd', 'var', 'samp', 'cite', 'q', 'abbr',
        'dfn', 'time', 'data', 'wbr', 'sub', 'sup', 'bdi', 'bdo',
        'wbr', 'br',
    ];
    const TAG_SELF_CLOSING = [
        'meta', 'link', 'img', 'input', 'wbr', 'hr', 'br',
        'col', 'area', 'base', 'source', 'embed', 'track',
    ];
    // @formatter:on
}

namespace Time {

    const
        MINUTE  = 60,
        HOUR_1  = 3_600,
        HOUR_4  = 14_400,
        HOUR_8  = 28_800,
        HOUR_12 = 43_200,
        DAY     = 86_400,
        WEEK    = 604_800,
        MONTH   = 2_592_000,
        YEAR    = 31_536_000;
}

/**
 * HTTP response codes.
 *
 * Lifted from Symfony\Component\HttpFoundation\Response.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */

namespace HTTP {

    const CONTINUE_100                             = 100;
    const SWITCHING_PROTOCOLS_101                  = 101;
    const PROCESSING_102                           = 102;            // RFC2518
    const EARLY_HINTS_103                          = 103;            // RFC8297
    const OK_200                                   = 200;
    const CREATED_201                              = 201;
    const ACCEPTED_202                             = 202;
    const NON_AUTHORITATIVE_INFORMATION_203        = 203;
    const NO_CONTENT_204                           = 204;
    const RESET_CONTENT_205                        = 205;
    const PARTIAL_CONTENT_206                      = 206;
    const MULTI_STATUS_207                         = 207;               // RFC4918
    const ALREADY_REPORTED_208                     = 208;               // RFC5842
    const IM_USED_226                              = 226;               // RFC3229
    const MULTIPLE_CHOICES_300                     = 300;
    const MOVED_PERMANENTLY_301                    = 301;
    const FOUND_302                                = 302;
    const SEE_OTHER_303                            = 303;
    const NOT_MODIFIED_304                         = 304;
    const USE_PROXY_305                            = 305;
    const RESERVED_306                             = 306;
    const TEMPORARY_REDIRECT_307                   = 307;
    const PERMANENTLY_REDIRECT_308                 = 308;  // RFC7238
    const BAD_REQUEST_400                          = 400;
    const UNAUTHORIZED_401                         = 401;
    const PAYMENT_REQUIRED_402                     = 402;
    const FORBIDDEN_403                            = 403;
    const NOT_FOUND_404                            = 404;
    const METHOD_NOT_ALLOWED_405                   = 405;
    const NOT_ACCEPTABLE_406                       = 406;
    const PROXY_AUTHENTICATION_REQUIRED_407        = 407;
    const REQUEST_TIMEOUT_408                      = 408;
    const CONFLICT_409                             = 409;
    const GONE_410                                 = 410;
    const LENGTH_REQUIRED_411                      = 411;
    const PRECONDITION_FAILED_412                  = 412;
    const REQUEST_ENTITY_TOO_LARGE_413             = 413;
    const REQUEST_URI_TOO_LONG_414                 = 414;
    const UNSUPPORTED_MEDIA_TYPE_415               = 415;
    const REQUESTED_RANGE_NOT_SATISFIABLE_416      = 416;
    const EXPECTATION_FAILED_417                   = 417;
    const I_AM_A_TEAPOT_418                        = 418;                                                      // RFC2324
    const MISDIRECTED_REQUEST_421                  = 421;                                                      // RFC7540
    const UNPROCESSABLE_ENTITY_422                 = 422;                                                      // RFC4918
    const LOCKED_423                               = 423;                                                      // RFC4918
    const FAILED_DEPENDENCY_424                    = 424;                                                      // RFC4918
    const TOO_EARLY_425                            = 425;                                                      // RFC-ietf-httpbis-replay-04
    const UPGRADE_REQUIRED_426                     = 426;                                                      // RFC2817
    const PRECONDITION_REQUIRED_428                = 428;                                                      // RFC6585
    const TOO_MANY_REQUESTS_429                    = 429;                                                      // RFC6585
    const REQUEST_HEADER_FIELDS_TOO_LARGE_431      = 431;                                                      // RFC6585
    const UNAVAILABLE_FOR_LEGAL_REASONS_451        = 451;                                                      // RFC7725
    const INTERNAL_SERVER_ERROR_500                = 500;
    const NOT_IMPLEMENTED_501                      = 501;
    const BAD_GATEWAY_502                          = 502;
    const SERVICE_UNAVAILABLE_503                  = 503;
    const GATEWAY_TIMEOUT_504                      = 504;
    const VERSION_NOT_SUPPORTED_505                = 505;
    const VARIANT_ALSO_NEGOTIATES_EXPERIMENTAL_506 = 506;                                                // RFC2295
    const INSUFFICIENT_STORAGE_507                 = 507;                                                // RFC4918
    const LOOP_DETECTED_508                        = 508;                                                // RFC5842
    const NOT_EXTENDED_510                         = 510;                                                // RFC2774
    const NETWORK_AUTHENTICATION_REQUIRED_511      = 511;                                                // RFC6585
}
