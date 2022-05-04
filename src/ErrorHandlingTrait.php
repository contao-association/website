<?php

declare(strict_types=1);

namespace App;

use Sentry\Event;
use Sentry\EventHint;
use function Sentry\captureEvent;

trait ErrorHandlingTrait
{
    private function sentryOrThrow(string $message, \Exception $exception = null, array $contexts = []): void
    {
        $event = Event::createEvent();
        $event->setMessage($message);

        foreach ($contexts as $name => $data) {
            $event->setContext($name, $data);
        }

        if (null === captureEvent($event, EventHint::fromArray(['exception' => $exception]))) {
            throw new \RuntimeException($message, 0, $exception);
        }
    }
}
