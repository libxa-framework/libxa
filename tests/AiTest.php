<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Libxa\Ai\AiManager;
use Libxa\Foundation\Application;
use Libxa\Http\Client;

class AiTest extends TestCase
{
    public function test_ai_manager_initialization()
    {
        $app = $this->createMock(Application::class);
        $app->method('env')->willReturnMap([
            ['AI_PROVIDER', 'openai', 'openai'],
            ['OPENAI_API_KEY', '', 'test-key'],
            ['AI_MODEL', 'gpt-4o-mini', 'gpt-4o-mini'],
        ]);

        $manager = new AiManager($app);
        $this->assertInstanceOf(AiManager::class, $manager);
    }

    // Additional tests would involve mocking the Libxa\Http\Client
    // which requires a more complex setup in this framework.
}
