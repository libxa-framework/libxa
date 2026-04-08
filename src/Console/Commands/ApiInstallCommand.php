<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class ApiInstallCommand extends Command
{
    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('api:run')
            ->setAliases(['api:install', 'Libxasecure:install'])
            ->setDescription('Scaffolds the LibxaSecure robust API implementation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("<info>Installing LibxaSecure API implementation...</info>");

        $this->createMigration($output);
        $this->createApiRoutes($output);
        $this->createTraits($output);
        $this->updateAuthConfig($output);

        $output->writeln("<info>LibxaSecure successfully installed!\nRemember to run 'php Libxa migrate' to build the token tables.</info>");
        
        return Command::SUCCESS;
    }

    protected function createMigration(OutputInterface $output): void
    {
        $migrationFile = $this->app->basePath('src/database/migrations/' . date('Y_m_d_His') . '_create_personal_access_tokens_table.php');
        $content = <<<'PHP'
<?php

use Libxa\Atlas\Schema\Blueprint;
use Libxa\Atlas\DB;

class CreatePersonalAccessTokensTable
{
    public function up(): void
    {
        DB::schema()->create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type');
            $table->integer('tokenable_id');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->string('refresh_token', 64)->nullable()->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        DB::schema()->dropIfExists('personal_access_tokens');
    }
}
PHP;
        file_put_contents($migrationFile, $content);
        $output->writeln("<comment>Created</comment> personal_access_tokens migration.");
    }

    protected function createApiRoutes(OutputInterface $output): void
    {
        $routeDir = $this->app->basePath('src/routes');
        if (!is_dir($routeDir)) {
            mkdir($routeDir, 0755, true);
        }

        $routeFile = $routeDir . '/api.php';
        if (!file_exists($routeFile)) {
            $content = <<<'PHP'
<?php

use Libxa\Router\Router;
use Libxa\Http\Request;

/** @var Router $router */

// The 'api' guard securely uses the Bearer token lookup from personal_access_tokens
$router->group(['middleware' => 'auth:api'], function ($router) {
    
    // Example: Fetch currently authenticated user
    $router->get('/api/user', function (Request $request) {
        return [
            'status' => 'success',
            'user' => auth('api')->user()
        ];
    });

});
PHP;
            file_put_contents($routeFile, $content);
            $output->writeln("<comment>Created</comment> default src/routes/api.php.");
        }
    }

    protected function createTraits(OutputInterface $output): void
    {
        $traitPath = $this->app->appPath('Models/Traits');
        if (!is_dir($traitPath)) {
            mkdir($traitPath, 0755, true);
        }

        $traitFile = $traitPath . '/HasApiTokens.php';
        
        $content = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Models\Traits;

use Libxa\Auth\LibxaSecure;
use Libxa\Atlas\DB;

/**
 * HasApiTokens Trait
 *
 * Provides LibxaSecure token management for models by delegating to the framework core.
 */
trait HasApiTokens
{
    /** The current access token */
    protected $accessToken;

    /**
     * Issue a new personal access token for the user.
     * Delegates security logic to LibxaSecure core.
     */
    public function createToken(string $name, array $abilities = ['*'], ?int $expiresInMinutes = null)
    {
        return LibxaSecure::createToken($this, $name, $abilities, $expiresInMinutes);
    }

    /**
     * Check if the current token has a given ability.
     */
    public function tokenCan(string $ability): bool
    {
        if (! $this->accessToken) {
            return false;
        }

        $abilities = json_decode($this->accessToken->abilities ?? '[]', true);

        return in_array('*', $abilities) || in_array($ability, $abilities);
    }

    /**
     * Query builder for all API tokens associated with this user.
     */
    public function tokens()
    {
        return DB::table('personal_access_tokens')
            ->where('tokenable_id', $this->id)
            ->where('tokenable_type', ltrim(str_replace('\\\\', '\\', get_class($this)), '\\'));
    }

    /**
     * Revoke all tokens for the model.
     */
    public function revokeAllTokens(): bool
    {
        return (bool) $this->tokens()->deleteRecord();
    }

    /**
     * Set the current access token for the model.
     * (Called by LibxaSecureGuard)
     */
    public function withAccessToken($token): self
    {
        $this->accessToken = $token;
        return $this;
    }

    /**
     * Get the current access token.
     */
    public function currentAccessToken()
    {
        return $this->accessToken;
    }
}
PHP;
        file_put_contents($traitFile, $content);
        $output->writeln("<comment>Created</comment> HasApiTokens trait dynamically in App\Models\Traits.");
    }

    protected function updateAuthConfig(OutputInterface $output): void
    {
        $configPath = $this->app->configPath('auth.php');
        if (file_exists($configPath)) {
            $content = file_get_contents($configPath);
            if (str_contains($content, "'driver' => 'token'")) {
                $content = str_replace("'driver' => 'token'", "'driver' => 'Libxasecure'", $content);
                file_put_contents($configPath, $content);
                $output->writeln("<comment>Configured</comment> config/auth.php standard API guard to use 'Libxasecure'.");
            }
        }
    }
}
