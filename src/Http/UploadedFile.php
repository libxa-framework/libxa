<?php

declare(strict_types=1);

namespace Libxa\Http;

class UploadedFile
{
    public function __construct(
        protected string $name,
        protected string $type,
        protected string $tmpName,
        protected int $error,
        protected int $size
    ) {
    }

    public function getClientOriginalName(): string
    {
        return $this->name;
    }

    public function getClientOriginalExtension(): string
    {
        return pathinfo($this->name, PATHINFO_EXTENSION);
    }

    public function getMimeType(): string
    {
        return $this->type;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function isValid(): bool
    {
        return $this->error === \UPLOAD_ERR_OK && is_uploaded_file($this->tmpName);
    }

    public function storePosition(string $path, string $fileName): string|false
    {
        if (!$this->isValid()) {
            return false;
        }

        $directory = app()->basePath("src/storage/app/{$path}");
        
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $target = rtrim($directory, '/') . '/' . ltrim($fileName, '/');

        if (move_uploaded_file($this->tmpName, $target)) {
            return ltrim($path, '/') . '/' . ltrim($fileName, '/');
        }

        return false;
    }

    public function store(string $path = 'public'): string|false
    {
        // Auto-generate safe unique filename
        $fileName = bin2hex(random_bytes(16)) . '.' . $this->getClientOriginalExtension();
        return $this->storePosition($path, $fileName);
    }

    public function storeAs(string $path, string $name): string|false
    {
        return $this->storePosition($path, $name);
    }
}
