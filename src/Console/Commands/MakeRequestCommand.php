<?php

declare(strict_types=1);

namespace Libxa\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libxa\Foundation\Application;

class MakeRequestCommand extends Command
{
    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('make:request')
             ->setDescription('Create a new FormRequest class')
             ->addArgument('name', InputArgument::REQUIRED, 'The name of the request');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        if (! str_ends_with($name, 'Request')) {
            $name .= 'Request';
        }

        $path = $this->app->appPath("Http/Requests/{$name}.php");

        if (file_exists($path)) {
            $output->writeln("<error>Request already exists!</error>");
            return Command::FAILURE;
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Libxa\Http\FormRequest;

class {$name} extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // 'title' => 'required|min:5',
            // 'body'  => 'required',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            // 'title.required' => 'A title is required',
        ];
    }
}
PHP;

        file_put_contents($path, $template);

        $output->writeln("<info>Request created successfully:</info> {$path}");

        return Command::SUCCESS;
    }
}
