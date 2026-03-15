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

use JsonException;
use JsonSerializable;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final readonly class Event
{
    /**
     * @param JsonSerializable|string $data Raw HTML string or JSON-serializable payload.
     *                                      Strings are sent as-is; objects are JSON-encoded.
     * @param string|null $event Named SSE event type (htmx uses this for targeting).
     * @param string|null $id Event ID for Last-Event-ID reconnect tracking.
     * @param int|null $retry Reconnection hint in milliseconds.
     */
    public function __construct(
        public readonly string|JsonSerializable $data,
        public readonly ?string $event = null,
        public readonly ?string $id = null,
        public readonly ?int $retry = null,
    ) {}

    /**
     * Serialize the event to the SSE wire format.
     *
     * @throws JsonException
     */
    public function toWireFormat(): string
    {
        $output = '';

        if ($this->id !== null) {
            $output .= 'id: ' . $this->id . "\n";
        }

        if ($this->event !== null) {
            $output .= 'event: ' . $this->event . "\n";
        }

        $data = $this->data instanceof JsonSerializable
            ? json_encode($this->data, JSON_THROW_ON_ERROR)
            : $this->data;

        $output .= 'data: ' . $data . "\n";

        if ($this->retry !== null) {
            $output .= 'retry: ' . $this->retry . "\n";
        }

        $output .= "\n";

        return $output;
    }
}
