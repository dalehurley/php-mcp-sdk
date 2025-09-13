<?php

declare(strict_types=1);

namespace MCP\Laravel\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class McpToolMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'mcp:make-tool';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new MCP tool class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Tool';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return __DIR__ . '/../../../stubs/tool.stub';
    }

    /**
     * Get the default namespace for the class.
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\Mcp\Tools';
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the tool class'],
        ];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the tool already exists'],
        ];
    }

    /**
     * Build the class with the given name.
     */
    protected function buildClass($name): string
    {
        $stub = $this->files->get($this->getStub());

        $toolName = $this->getToolNameFromClass($name);
        
        return $this->replaceNamespace($stub, $name)
            ->replaceToolName($stub, $toolName)
            ->replaceClass($stub, $name);
    }

    /**
     * Replace the tool name in the stub.
     */
    protected function replaceToolName(&$stub, $name): self
    {
        $stub = str_replace('{{ toolName }}', $name, $stub);

        return $this;
    }

    /**
     * Get the tool name from the class name.
     */
    protected function getToolNameFromClass(string $name): string
    {
        $className = class_basename($name);
        $toolName = str_replace('Tool', '', $className);
        
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $toolName));
    }
}