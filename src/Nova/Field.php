<?php

declare(strict_types=1);

namespace Libxa\Nova;

class Field
{
    public function __construct(
        public string $name,
        public string $attribute,
        public string $type = 'text',
        public bool $showOnIndex = true,
        public bool $showOnDetail = true,
        public bool $showOnForm = true,
        public bool $sortable = false,
    ) {}

    public static function make(string $name, ?string $attribute = null): static
    {
        return new static($name, $attribute ?? strtolower($name));
    }

    public function sortable(): static
    {
        $this->sortable = true;
        return $this;
    }

    public function hideFromIndex(): static
    {
        $this->showOnIndex = false;
        return $this;
    }
}
