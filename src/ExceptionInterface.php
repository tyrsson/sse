<?php

declare(strict_types=1);

namespace Webware\SSE;

/**
 * Marker interface for all exceptions thrown by the webware/sse package.
 *
 * Catch this interface to handle any SSE-specific exception:
 *
 *   try { ... } catch (ExceptionInterface $e) { ... }
 */
interface ExceptionInterface extends \Throwable
{
}
