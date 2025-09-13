<?php

declare(strict_types=1);

namespace MCP\Laravel\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class McpPromptMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'mcp:make-prompt';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new MCP prompt class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Prompt';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return __DIR__ . '/../../../stubs/prompt.stub';
    }

    /**
     * Get the default namespace for the class.
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\Mcp\Prompts';
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the prompt class'],
        ];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the prompt already exists'],
        ];
    }

    /**
     * Build the class with the given name.
     */
    protected function buildClass($name): string
    {
        $stub = $this->files->get($this->getStub());

        $promptName = $this->getPromptNameFromClass($name);
        
        return $this->replaceNamespace($stub, $name)
            ->replacePromptName($stub, $promptName)
            ->replaceClass($stub, $name);
    }

    /**
     * Replace the prompt name in the stub.
     */
    protected function replacePromptName(&$stub, $name): self
    {
        $stub = str_replace('{{ promptName }}', $name, $stub);

        return $this;
    }

    /**
     * Get the prompt name from the class name.
     */
    protected function getPromptNameFromClass(string $name): string
    {
        $className = class_basename($name);
        $promptName = str_replace('Prompt', '', $className);
        
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $promptName));
    }
}