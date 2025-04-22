<?php

namespace Core\Interface;

/**
 * The primary `action` must be through the `__invoke` method.
 *
 * ```
 * // example using a Controller Route
 * #[Route( '/{route}' )]
 * public function index( string $route, Service $action ) : void {
 *     $action( "Service invoked by {$route}!" );
 * }
 * ```
 *
 * @require-method  __invoke()
 *
 * @author          Martin Nielsen <mn@northrook.com>
 */
interface ActionInterface {}
