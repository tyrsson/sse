<?php

declare(strict_types=1);

/**
 * This file is part of the Webware Sse package.
 *
 * Copyright (c) 2026 Joey (aka Tyrsson) Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webware\SSE;

use Throwable;

/**
 * Marker interface for all exceptions thrown by the webware/sse package.
 *
 * Catch this interface to handle any SSE-specific exception:
 *
 *   try { ... } catch (ExceptionInterface $e) { ... }
 */
interface ExceptionInterface extends Throwable {}
