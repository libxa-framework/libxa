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
        if ($this->error !== \UPLOAD_ERR_OK) {
            return false;
        }

        // Standard PHP upload check
        if (is_uploaded_file($this->tmpName)) {
            return true;
        }

        // If it was already moved by our framework (persistent token), 
        // we check if it still exists in our internal storage.
        return file_exists($this->tmpName);
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

        // Choose move method based on whether it's a fresh PHP upload or a persistent file
        if (is_uploaded_file($this->tmpName)) {
            $success = move_uploaded_file($this->tmpName, $target);
        } else {
            // For persistent files, we copy then delete to simulate moving across disks if needed
            $success = copy($this->tmpName, $target);
            if ($success) {
                unlink($this->tmpName);
            }
        }

        if ($success) {
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
