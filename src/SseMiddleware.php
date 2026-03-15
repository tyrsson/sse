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

use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function explode;
use function in_array;
use function trim;

final class SseMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $accept = $request->getHeaderLine('Accept');

        if (! in_array('text/event-stream', $this->parseAccept($accept), true)) {
            return new TextResponse('Not Acceptable', 406);
        }

        return $handler->handle($request);
    }

    /**
     * Parse the Accept header into a list of media-type tokens.
     *
     * @return list<string>
     */
    private function parseAccept(string $accept): array
    {
        if ($accept === '') {
            return [];
        }

        $types = [];
        foreach (explode(',', $accept) as $part) {
            // Strip quality factors (e.g. "text/event-stream;q=0.9")
            $mediaType = trim(explode(';', $part)[0]);
            if ($mediaType !== '') {
                $types[] = $mediaType;
            }
        }

        return $types;
    }
}
