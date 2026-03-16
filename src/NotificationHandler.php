<?php

declare(strict_types=1);

/**
 * This file is part of the Tyrsson Webinertia package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webware\SSE;

use Axleus\Message\SystemMessengerInterface;
use Laminas\View\Helper\Partial;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class NotificationHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly Partial $partialHelper,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $lastEventId = $request->getHeaderLine('ID');

        $messenger = $request->getAttribute(SystemMessengerInterface::class);

        // $messenger?->sendNow(
        //     'This is a notification message.',
        //     MessageLevel::Info,
        // );
        $eventStream = '';

        foreach ($messenger->getMessages() as $level => $data) {
            if (is_array($data)) {
                $message = (string) ($data['message'] ?? '');
            } elseif (is_string($data)) {
                $message = $data;
            } else {
                continue;
            }

            $html = ($this->partialHelper)('sse::'.$level, [
                'level'   => (string) $level,
                'message' => $message,
            ]);

            $eventStream .= new Event(
                data: $html,
                event: $level
            );
        }

        return new SseResponse($eventStream);
    }
}
