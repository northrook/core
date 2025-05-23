<?php

namespace Core\Interface;

/**
 * Indicates that this class is a `DataTransferObject`.
 *
 * The class must follow these rules:
 * - All properties are `public`.
 * - {@see self::__construct} ingests typed arguments.
 * - No internal logic outside handling ingested data.
 *
 * The class may add `get` methods for conditional property retrieval.
 *
 * @author Martin Nielsen <mn@northrook.com>
 */
interface DataInterface {}
