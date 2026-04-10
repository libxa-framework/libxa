<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Libxa\Atlas\AI\AiQueryBridge;
use Libxa\Atlas\AI\AiQueryResult;
use Libxa\Foundation\Application;

class AtlasAiTest extends TestCase
{
    private $app;

    protected function setUp(): void
    {
        $this->app = $this->createMock(Application::class);
        Application::setInstance($this->app);
    }
    protected function tearDown(): void
    {
        // Null is not accepted directly, clear instances using another method or omit
    }

    public function test_ai_query_bridge_is_disabled_by_default()
    {
        $this->app->method('env')->willReturnMap([
            ['ATLAS_AI_ENABLED', 'false', 'false'],
        ]);

        $result = AiQueryBridge::ask('find all users');

        $this->assertInstanceOf(AiQueryResult::class, $result);
        $this->assertFalse($result->safe);
        $this->assertEquals('disabled', $result->status);
    }

    public function test_ai_query_bridge_returns_no_driver_when_invalid()
    {
        $this->app->method('env')->willReturnMap([
            ['ATLAS_AI_ENABLED', 'false', 'true'],
            ['ATLAS_AI_PROVIDER', 'openai', 'invalid_provider'],
        ]);

        $result = AiQueryBridge::ask('find all users');

        $this->assertFalse($result->safe);
        $this->assertEquals('no_driver', $result->status);
    }
}
