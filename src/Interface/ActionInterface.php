<?php

declare(strict_types=1);

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
 * @method __invoke()
 *
 * @author Martin Nielsen <mn@northrook.com>
 */
interface ActionInterface {}
