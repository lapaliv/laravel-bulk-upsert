<?php

namespace Lapaliv\BulkUpsert\Events;

use DateTime;
use Illuminate\Container\Container;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;

/**
 * @internal
 */
class BulkEventDispatcher
{
    private array $listeners = [];
    private ?array $enabledEvents = null;

    public function __construct(private BulkModel $model)
    {
        //
    }

    public function listen(string $event, mixed $listener, bool $once = false): ?string
    {
        $listener = self::convertListenerToClosure($listener);

        if ($listener === null) {
            return null;
        }

        $key = hash('crc32c', (new DateTime())->format('Y-m-d H:i:s.u'));
        $this->listeners[$event] ??= [];
        $this->listeners[$event][$key] = [
            'once' => $once,
            'listener' => $listener,
        ];

        return $key;
    }

    public function once(string $event, ?callable $listener): ?string
    {
        return $this->listen($event, $listener, true);
    }

    public function forget(string $event, ?string $key): static
    {
        if ($key !== null) {
            unset($this->listeners[$event][$key]);
        }

        return $this;
    }

    public function dispatch(string $event, ...$payload): mixed
    {
        if ($this->hasListener($event) === false) {
            return null;
        }

        $isHalt = $this->isHaltEvent($event);
        $dispatcher = $this->model::getEventDispatcher();
        $nativeEvent = $this->getEloquentNativeEventName($event);

        if (in_array($event, BulkEventEnum::model())) {
            $response = $dispatcher->dispatch($nativeEvent, $payload[0], $isHalt);

            if ($isHalt && $response === false) {
                return false;
            }
        } elseif (method_exists($dispatcher, 'getListeners')) {
            foreach ($dispatcher->getListeners($nativeEvent) as $listener) {
                $response = $listener($nativeEvent, $payload);

                if ($isHalt && $response === false) {
                    return false;
                }
            }
        }

        if (array_key_exists($event, $this->listeners) === false) {
            return null;
        }

        foreach ($this->listeners[$event] as $key => $config) {
            ['once' => $once, 'listener' => $listener] = $config;
            $response = $listener(...$payload);

            if ($once) {
                unset($this->listeners[$event][$key]);
            }

            if ($isHalt && $response === false) {
                return false;
            }
        }

        return null;
    }

    public function hasListeners(array $events): bool
    {
        foreach ($events as $event) {
            $nativeEvent = $this->getEloquentNativeEventName($event);

            if ($this->model::getEventDispatcher()->hasListeners($nativeEvent)) {
                return true;
            }

            if (array_key_exists($event, $this->listeners)) {
                if (is_array($this->enabledEvents)
                    && in_array($event, $this->enabledEvents, true) === false
                ) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    public function hasListener(string $event): bool
    {
        return $this->hasListeners([$event]);
    }

    public function restrict(array $events): static
    {
        $this->enabledEvents = $events;

        return $this;
    }

    private function isHaltEvent(string $event): bool
    {
        return in_array($event, BulkEventEnum::halt(), true);
    }

    private static function convertListenerToClosure(mixed $listener): mixed
    {
        if ($listener === null) {
            return null;
        }

        if (is_string($listener) && str_contains($listener, '@')) {
            [$class, $method] = explode('@', $listener);
            $listener = [Container::getInstance()->make($class), $method];
        }

        return $listener;
    }

    private function getEloquentNativeEventName(string $event): string
    {
        return sprintf('eloquent.%s: %s', $event, get_class($this->model));
    }
}
