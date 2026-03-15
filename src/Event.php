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
 * Immutable value object representing a single Server-Sent Event.
 *
 * Usage from a stream callable:
 *
 *   $send(new Event(data: 'hello'));
 *   $send(new Event(data: 'tick', event: 'clock', id: '42', retry: 5000));
 *   $send(new Event(data: "line one\nline two"));  // multi-line data
 */
final class Event implements EventInterface
{
    public function __construct(
        private readonly string $data,
        private readonly ?string $id = null,
        private readonly ?string $event = null,
        private readonly ?int $retry = null,
        private readonly ?string $comment = null,
    ) {}

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getEvent(): ?string
    {
        return $this->event;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getRetry(): ?int
    {
        return $this->retry;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * Serialises the event into the SSE wire format.
     *
     * Field order follows the SSE specification recommendation:
     *   1. comment  (": <comment>")
     *   2. retry    ("retry: <ms>")
     *   3. id       ("id: <id>")
     *   4. event    ("event: <type>")
     *   5. data     ("data: <line>" — one line per "\n" in the payload)
     *
     * The block is terminated by a blank line ("\n\n") to dispatch the event.
     */
    public function format(): string
    {
        $output = '';

        if ($this->comment !== null) {
            $output .= ': ' . $this->comment . "\n";
        }

        if ($this->retry !== null) {
            $output .= 'retry: ' . $this->retry . "\n";
        }

        if ($this->id !== null) {
            $output .= 'id: ' . $this->id . "\n";
        }

        if ($this->event !== null) {
            $output .= 'event: ' . $this->event . "\n";
        }

        foreach (explode("\n", $this->data) as $line) {
            $output .= 'data: ' . $line . "\n";
        }

        return $output . "\n";
    }
}
