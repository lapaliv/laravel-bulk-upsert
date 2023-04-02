<?php

namespace Lapaliv\BulkUpsert\Events;

use Closure;
use Illuminate\Container\Container;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;

/**
 * @internal
 */
class BulkEventDispatcher
{
    /**
     * @var array<string, array<string, Closure[]>>
     */
    private static array $globalListeners = [];
    private array $localListeners = [];
    private ?array $enabledEvents = null;

    public function __construct(private string $model)
    {
        //
    }

    public static function registerListener(string $model, string $event, string|callable $listener): void
    {
        self::$globalListeners[$model] ??= [];
        self::$globalListeners[$model][$event] ??= [];
        self::$globalListeners[$model][$event][] = self::convertListenerToClosure($listener);
    }

    public static function flush(): void
    {
        self::$globalListeners = [];
    }

    public function listen(string $event, string|callable|null $listener, bool $once = false): static
    {
        $listener = self::convertListenerToClosure($listener);

        if ($listener === null) {
            return $this;
        }

        $this->localListeners[$event] ??= [];
        $this->localListeners[$event][] = [
            'once' => $once,
            'listener' => is_callable($listener)
                ? Closure::fromCallable($listener)
                : $listener,
        ];

        return $this;
    }

    public function once(string $event, ?callable $listener): static
    {
        return $this->listen($event, $listener, true);
    }

    public function dispatch(string $event, ...$payload): mixed
    {
        if ($this->hasListeners([$event]) === false) {
            return null;
        }

        $isHalt = $this->isHaltEvent($event);
        $globalListeners = self::$globalListeners[$this->model][$event] ?? [];

        foreach ($globalListeners as $listener) {
            $response = $listener(...$payload);

            if ($isHalt && $response === false) {
                return false;
            }
        }

        $localListeners = $this->localListeners[$event] ?? [];

        foreach ($localListeners as $key => $config) {
            ['once' => $once, 'listener' => $listener] = $config;
            $response = $listener(...$payload);

            if ($once) {
                unset($this->localListeners[$event][$key]);
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
            $listeners = [
                ...self::$globalListeners[$this->model][$event] ?? [],
                ...$this->localListeners[$event] ?? [],
            ];

            if (empty($listeners)) {
                continue;
            }

            if (is_array($this->enabledEvents)
                && in_array($event, $this->enabledEvents, true) === false
            ) {
                continue;
            }

            return true;
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

    private static function convertListenerToClosure(mixed $listener): ?Closure
    {
        if ($listener === null) {
            return null;
        }

        if (is_string($listener) && str_contains($listener, '@')) {
            [$class, $method] = explode('@', $listener);
            $listener = [Container::getInstance()->make($class), $method];
        }

        return is_callable($listener)
            ? Closure::fromCallable($listener)
            : $listener;
    }

    private static function convertArrayToCallable(array $callback): callable
    {
        return $callback;
    }
}
