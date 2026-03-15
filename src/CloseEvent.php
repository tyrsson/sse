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

/**
 * Terminal event that signals to a connected client that the stream has ended
 * and the browser should not attempt to reconnect.
 *
 * Sends the following SSE block:
 *
 *   event: stream-close
 *   data:
 *
 * Because the browser's EventSource reconnects automatically after the
 * connection closes, the client must explicitly call source.close() when it
 * receives this event.  Add the following listener once on the client side:
 *
 *   source.addEventListener('stream-close', () => source.close());
 *
 * Usage inside stream():
 *
 *   $send(new CloseEvent());
 *   return; // stream() ends — PHP closes the connection
 */
final class CloseEvent implements EventInterface
{
    /** The SSE named-event string sent to the client. */
    public const EVENT_NAME = 'stream-close';

    public function getId(): ?string
    {
        return null;
    }

    public function getEvent(): string
    {
        return self::EVENT_NAME;
    }

    public function getData(): string
    {
        return '';
    }

    public function getRetry(): ?int
    {
        return null;
    }

    public function getComment(): ?string
    {
        return null;
    }

    public function format(): string
    {
        return 'event: ' . self::EVENT_NAME . "\ndata: \n\n";
    }
}
