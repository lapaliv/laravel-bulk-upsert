<?php

namespace Lapaliv\BulkUpsert\EventDispatchers;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use UnexpectedValueException;

/**
 * Dispatches the package events to the Eloquent listeners and to its own listeners.
 *
 * @internal
 */
class BulkEventDispatcher
{
    /**
     * The Illuminate dispatcher which is used instead of the dispatcher of the model.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher|null
     */
    private static ?Dispatcher $illuminateEventDispatcher = null;

    /**
     * The own listeners grouped by the event value and keyed by the listener key.
     *
     * @var array<string, array<string, array{once: bool, listener: mixed}>>
     */
    private array $listeners = [];

    /**
     * The events which are allowed to be dispatched, or `null` when all are allowed.
     *
     * @var \Lapaliv\BulkUpsert\Enums\BulkEventEnum[]|null
     */
    private ?array $enabledEvents = null;

    /**
     * The number of the own listeners added so far, which makes their keys unique.
     *
     * A timestamp is not unique: two listeners added within the same microsecond
     * would get the same key, and the second one would replace the first one.
     *
     * @var int
     */
    private int $listenerCounter = 0;

    /**
     * The model whose events are dispatched.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    private Model $model;

    /**
     * Create a new event dispatcher for the given model.
     *
     * @param \Illuminate\Database\Eloquent\Model|string $model
     *
     * @return void
     */
    public function __construct(Model|string $model)
    {
        if (is_string($model)) {
            /** @var \Illuminate\Database\Eloquent\Model $model */
            $model = Container::getInstance()->make($model);
        }

        $this->model = $model;
    }

    /**
     * Add an own listener for the given event.
     * The method returns the key of the listener, which can be given to `forget()`, or
     * `null` when the listener is `null`.
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $event
     * @param mixed $listener
     * @param bool $once
     *
     * @return string|null
     */
    public function listen(BulkEventEnum $event, mixed $listener, bool $once = false): ?string
    {
        /** @psalm-suppress MixedAssignment */
        $listener = self::convertListenerToClosure($listener);

        if ($listener === null) {
            return null;
        }

        $this->listenerCounter++;
        $key = 'listener_' . $this->listenerCounter;
        $this->listeners[$event->value] ??= [];
        $this->listeners[$event->value][$key] = [
            'once' => $once,
            'listener' => $listener,
        ];

        return $key;
    }

    /**
     * Add an own listener which is removed after the first dispatch.
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $event
     * @param callable|null $listener
     *
     * @return string|null
     *
     * @psalm-api
     */
    public function once(BulkEventEnum $event, ?callable $listener): ?string
    {
        return $this->listen($event, $listener, true);
    }

    /**
     * Remove the own listener with the given key.
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $event
     * @param string|null $key
     *
     * @return $this
     *
     * @psalm-api
     */
    public function forget(BulkEventEnum $event, ?string $key): static
    {
        if ($key !== null) {
            unset($this->listeners[$event->value][$key]);
        }

        return $this;
    }

    /**
     * Dispatch the given event to the Eloquent listeners and to the own listeners.
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $event
     * @param mixed ...$payload
     *
     * @return mixed
     *
     * @example dispatch(BulkEventEnum::creating, $model)
     * @example dispatch(BulkEventEnum::creatingMany, $collection)
     */
    public function dispatch(BulkEventEnum $event, ...$payload): mixed
    {
        if (!$this->hasListener($event)) {
            return null;
        }

        $isHalt = $this->isHaltEvent($event);
        $nativeEvent = $this->getEloquentNativeEventName($event);

        /** @var array|false|null $response */
        $response = $this->getEventDispatcher()->dispatch($nativeEvent, $payload, $isHalt);

        if ($isHalt && $response === false) {
            return false;
        }

        if (!array_key_exists($event->value, $this->listeners)) {
            return null;
        }

        foreach ($this->listeners[$event->value] as $key => $config) {
            ['once' => $once, 'listener' => $listener] = $config;

            /** @psalm-suppress MixedAssignment, MixedFunctionCall */
            $response = $listener(...$payload);

            if ($once) {
                unset($this->listeners[$event->value][$key]);
            }

            if ($isHalt && $response === false) {
                return false;
            }
        }

        return null;
    }

    /**
     * Determine whether at least one of the given events has a listener.
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum[] $events
     *
     * @return bool
     */
    public function hasListeners(array $events): bool
    {
        if (is_array($this->enabledEvents) && empty($this->enabledEvents)) {
            return false;
        }

        foreach ($events as $event) {
            if (is_array($this->enabledEvents)
                && !in_array($event, $this->enabledEvents, true)
            ) {
                continue;
            }

            $nativeEvent = $this->getEloquentNativeEventName($event);

            if ($this->getEventDispatcher()->hasListeners($nativeEvent)) {
                return true;
            }

            if (array_key_exists($event->value, $this->listeners)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the given event has a listener.
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $event
     *
     * @return bool
     */
    public function hasListener(BulkEventEnum $event): bool
    {
        return $this->hasListeners([$event]);
    }

    /**
     * Allow only the given events to be dispatched.
     *
     * @param array<array-key, string|\Lapaliv\BulkUpsert\Enums\BulkEventEnum> $events
     * @return $this
     *
     * @throws \ValueError When one of the given values does not name an event.
     * @example restrict(\Lapaliv\BulkUpsert\Enums\BulkEventEnum::creating)
     * @example restrict('creating')
     *
     */
    public function restrict(array $events): static
    {
        $this->enabledEvents = array_map(
            static fn(BulkEventEnum|string $event): BulkEventEnum => is_string($event)
                ? BulkEventEnum::from($event)
                : $event,
            array_values($events)
        );

        return $this;
    }

    /**
     * Get the events which are allowed to be dispatched.
     *
     * @return \Lapaliv\BulkUpsert\Enums\BulkEventEnum[]|null
     */
    public function getEnabledEvents(): ?array
    {
        return $this->enabledEvents;
    }

    /**
     * Set the Illuminate dispatcher which every instance of this class uses.
     *
     * @param \Illuminate\Contracts\Events\Dispatcher $dispatcher
     *
     * @return void
     */
    public static function setIlluminateEventDispatcher(Dispatcher $dispatcher): void
    {
        self::$illuminateEventDispatcher = $dispatcher;
    }

    /**
     * Determine whether the given event can stop the process.
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $event
     *
     * @return bool
     */
    private function isHaltEvent(BulkEventEnum $event): bool
    {
        return in_array($event, BulkEventEnum::halt(), true);
    }

    /**
     * Get the Illuminate dispatcher which the events are sent to.
     *
     * The dispatcher of the class is preferred, otherwise the one of the model is used.
     *
     * @return \Illuminate\Contracts\Events\Dispatcher
     *
     * @throws \UnexpectedValueException
     */
    private function getEventDispatcher(): Dispatcher
    {
        $result = self::$illuminateEventDispatcher ?? $this->model::getEventDispatcher();

        if ($result === null) {
            throw new UnexpectedValueException();
        }

        return $result;
    }

    /**
     * Convert the given listener into a callable value.
     *
     * A `Class@method` string becomes an array with the instance from the container and
     * the method name. Any other value is returned as it is.
     *
     * @param mixed $listener
     *
     * @return mixed
     */
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

    /**
     * Build the native Eloquent name of the given event.
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $event
     *
     * @return string
     */
    private function getEloquentNativeEventName(BulkEventEnum $event): string
    {
        return sprintf('eloquent.%s: %s', $event->value, get_class($this->model));
    }
}
