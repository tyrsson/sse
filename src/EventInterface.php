<?php

declare(strict_types=1);

namespace Webware\SSE;

interface EventInterface
{
    /**
     * Returns the event id, or null if not set.
     */
    public function getId(): ?string;

    /**
     * Returns the named event type, or null for a generic message event.
     */
    public function getEvent(): ?string;

    /**
     * Returns the event payload. Multi-line strings are supported and will be
     * split into individual "data:" lines by format().
     */
    public function getData(): string;

    /**
     * Returns the reconnection timeout in milliseconds, or null if not set.
     */
    public function getRetry(): ?int;

    /**
     * Returns an inline SSE comment (": <comment>") or null if not set.
     * Comments are invisible to the browser event listener but useful for
     * keep-alive and debugging.
     */
    public function getComment(): ?string;

    /**
     * Serialises the event into the SSE wire format ready to be flushed to the
     * client. The returned string always ends with a blank line ("\n\n").
     */
    public function format(): string;
}
