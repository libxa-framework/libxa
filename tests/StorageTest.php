<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Libxa\Storage\Storage;
use Libxa\Foundation\Application;

class StorageTest extends TestCase
{
    protected function setUp(): void
    {
        // Mock or initialize application if needed, but here we assume the container is enough
        $app = Application::getInstance();
        $app->singleton('storage', function($app) {
            return new \Libxa\Storage\StorageManager($app);
        });
    }

    public function test_storage_put_and_get()
    {
        $path = 'test_file.txt';
        $content = 'Hello Libxa Storage';
        
        Storage::put($path, $content);
        
        $this->assertTrue(Storage::exists($path));
        $this->assertEquals($content, Storage::get($path));
        
        Storage::delete($path);
        $this->assertFalse(Storage::exists($path));
    }

    public function test_storage_move_and_copy()
    {
        Storage::put('source.txt', 'test');
        
        Storage::copy('source.txt', 'copy.txt');
        $this->assertTrue(Storage::exists('copy.txt'));
        
        Storage::move('copy.txt', 'moved.txt');
        $this->assertFalse(Storage::exists('copy.txt'));
        $this->assertTrue(Storage::exists('moved.txt'));
        
        Storage::delete(['source.txt', 'moved.txt']);
    }

    public function test_storage_mime_type()
    {
        Storage::put('test.txt', 'test');
        $this->assertEquals('text/plain', Storage::mimeType('test.txt'));
        Storage::delete('test.txt');
    }
}
