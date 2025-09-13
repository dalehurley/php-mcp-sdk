<?php

declare(strict_types=1);

namespace MCP\Laravel\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class McpResourceMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'mcp:make-resource';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new MCP resource class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Resource';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return $this->option('template') 
            ? __DIR__ . '/../../../stubs/resource-template.stub'
            : __DIR__ . '/../../../stubs/resource.stub';
    }

    /**
     * Get the default namespace for the class.
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\Mcp\Resources';
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the resource class'],
        ];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['template', 't', InputOption::VALUE_NONE, 'Create a resource template instead of a static resource'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the resource already exists'],
        ];
    }

    /**
     * Build the class with the given name.
     */
    protected function buildClass($name): string
    {
        $stub = $this->files->get($this->getStub());

        $resourceName = $this->getResourceNameFromClass($name);
        
        return $this->replaceNamespace($stub, $name)
            ->replaceResourceName($stub, $resourceName)
            ->replaceClass($stub, $name);
    }

    /**
     * Replace the resource name in the stub.
     */
    protected function replaceResourceName(&$stub, $name): self
    {
        $stub = str_replace('{{ resourceName }}', $name, $stub);

        return $this;
    }

    /**
     * Get the resource name from the class name.
     */
    protected function getResourceNameFromClass(string $name): string
    {
        $className = class_basename($name);
        $resourceName = str_replace('Resource', '', $className);
        
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $resourceName));
    }
}