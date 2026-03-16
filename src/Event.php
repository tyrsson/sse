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

use Override;

use PHP_EOL;

/**
 * Immutable value object representing a single Server-Sent Event.
 */
final class Event implements EventInterface
{
    public const FIELD_EVENT = 'event: ';

    public const FIELD_DATA = 'data: ';

    public const FIELD_ID = 'id: ';

    public const FIELD_RETRY = 'retry: ';

    public const FIELD_COMMENT = ': ';

    public function __construct(
        private readonly string $data,
        private readonly ?string $event = null,
        private readonly ?string $id = null,
        private readonly ?int $retry = null,
        private readonly ?string $comment = null,
    ) {}

    #[Override]
    public function __toString(): string
    {
        return $this->format();
    }

    #[Override]
    public function getId(): ?string
    {
        return $this->id;
    }

    #[Override]
    public function getEvent(): ?string
    {
        return $this->event;
    }

    #[Override]
    public function getData(): string
    {
        return $this->data;
    }

    #[Override]
    public function getRetry(): ?int
    {
        return $this->retry;
    }

    #[Override]
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * Serialises the event into the SSE wire format.
     *
     * Field order follows the SSE specification recommendation:
     *   - event
     *   - data
     *   - id
     *   - retry
     *   - comment
     *
     * The block is terminated by a blank line (PHP_EOL . PHP_EOL) to dispatch the event.
     */
    #[Override]
    public function format(): string
    {
        $output = '';

        if ($this->event !== null) {
            $output .= self::FIELD_EVENT . $this->event . PHP_EOL;
        }

        foreach (explode(PHP_EOL, $this->data) as $line) {
            $output .= self::FIELD_DATA . $line . PHP_EOL;
        }

        if ($this->id !== null) {
            $output .= self::FIELD_ID . $this->id . PHP_EOL;
        }

        if ($this->retry !== null) {
            $output .= self::FIELD_RETRY . $this->retry . PHP_EOL;
        }

        if ($this->comment !== null) {
            $output .= self::FIELD_COMMENT . $this->comment . PHP_EOL;
        }

        return $output . PHP_EOL;
    }
}
