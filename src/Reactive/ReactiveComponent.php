<?php

declare(strict_types=1);

namespace Libxa\Reactive;

/**
 * Reactive Component Base Class
 *
 * Extend this to create server-driven reactive UI components.
 * State changes are automatically diffed and pushed to the browser via Workerman.
 *
 * Usage:
 *   class Counter extends ReactiveComponent
 *   {
 *       public int $count = 0;
 *
 *       public function increment(): void { $this->count++; }
 *       public function decrement(): void { $this->count--; }
 *
 *       public function render(): string
 *       {
 *           return view('components.counter', ['count' => $this->count]);
 *       }
 *   }
 *
 * In Blade:
 *   @reactive('App\Components\Counter')
 */
abstract class ReactiveComponent
{
    /** Unique instance ID (set by WsServer on mount) */
    public string $componentId = '';

    /** Debounce timer in ms for rapid method calls */
    protected int $debounce = 0;

    /** Properties that should NOT be sent to the client */
    protected array $protected = [];

    // ─────────────────────────────────────────────────────────────────
    //  Lifecycle
    // ─────────────────────────────────────────────────────────────────

    public function __construct(array $props = [])
    {
        foreach ($props as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Called once when the component is first mounted.
     * Override to run initialization logic (DB queries, etc.).
     */
    public function mount(array $props = []): void {}

    /**
     * Called before a method is executed.
     */
    public function beforeUpdate(string $method): void {}

    /**
     * Called after state is updated.
     */
    public function afterUpdate(string $method): void {}

    // ─────────────────────────────────────────────────────────────────
    //  Rendering
    // ─────────────────────────────────────────────────────────────────

    /**
     * Return the view name or raw HTML for this component.
     */
    abstract public function render(): string;

    /**
     * Render the component to HTML.
     */
    public function renderHtml(): string
    {
        $result = $this->render();

        // If it's a view name (contains dots/slashes), render it through Blade
        if (! str_contains($result, '<') && (str_contains($result, '.') || str_contains($result, '/'))) {
            $app = \Libxa\Foundation\Application::getInstance();
            return $app?->make('blade')->render($result, $this->getPublicState()) ?? $result;
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────
    //  State
    // ─────────────────────────────────────────────────────────────────

    /**
     * Get all public properties as the component state.
     */
    public function getPublicState(): array
    {
        $reflector = new \ReflectionClass($this);
        $state     = [];

        foreach ($reflector->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();

            if ($name === 'componentId') continue;
            if (in_array($name, $this->protected)) continue;

            $state[$name] = $this->$name;
        }

        return $state;
    }

    public function toSnapshot(): array
    {
        return [
            'state' => $this->getPublicState(),
            'html'  => $this->renderHtml(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    //  Dispatch (emit events to JS)
    // ─────────────────────────────────────────────────────────────────

    protected array $dispatchedEvents = [];

    protected function dispatch(string $event, array $data = []): void
    {
        $this->dispatchedEvents[] = compact('event', 'data');
    }

    public function popDispatchedEvents(): array
    {
        $events = $this->dispatchedEvents;
        $this->dispatchedEvents = [];
        return $events;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Validation helper
    // ─────────────────────────────────────────────────────────────────

    protected function validate(array $rules): array
    {
        $validator = new \Libxa\Validation\Validator($this->getPublicState(), $rules);

        if ($validator->fails()) {
            throw new \Libxa\Validation\ValidationException($validator->errors());
        }

        return $validator->validated();
    }
}
