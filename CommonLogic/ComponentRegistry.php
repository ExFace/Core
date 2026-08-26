<?php

namespace exface\Core\CommonLogic;

use exface\Core\DataTypes\JsonDataType;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\ComponentRegistryInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Facades\MarkdownInstancePrinterInterface;
use exface\Core\Interfaces\Facades\MarkdownPrinterInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Templates\BracketHashStringTemplateRenderer;
use exface\Core\Templates\Placeholders\ArrayPlaceholders;
use exface\Core\Templates\Placeholders\ConfigPlaceholders;
use exface\Core\Templates\Placeholders\FormulaPlaceholders;

class ComponentRegistry implements ComponentRegistryInterface
{
    private WorkbenchInterface $workbench;
    private array $config;
    
    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
        $this->config = [];
        $baseConfigFile = $this->getWorkbench()->getCoreApp()->getDirectoryAbsolutePath() . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'ComponentRegistry.config.json';
        if (is_readable($baseConfigFile)) {
            $json = JsonDataType::decodeJson(file_get_contents($baseConfigFile), true);
            $this->config = array_merge_recursive($this->config, $json);
        }
    }

    /**
     * {@inheritDoc}
     * @see ComponentRegistryInterface::getComponentKeys()
     */
    public function getComponentKeys(?string $havingKey = null) : array
    {
        $keys = array_keys($this->config);
        if ($havingKey !== null) {
            $keys = array_filter($keys, function($key) use ($havingKey) {
                return array_key_exists($havingKey, $this->config[$key]);
            });
        }
        return $keys;
    }
    
    protected function canInstantiate(string $component) : bool
    {
        return null !== $this->getSelectorFactory($component);
    }
    
    protected function getSelectorFactory($component) : ?callable
    {
        $factoryClass = (($this->config[$component] ?? [])['selector'] ?? [])['factory'] ?? null;
        switch (true) {
            case null === $factoryClass:
                return null;
            case is_string($factoryClass):
                $callable = explode('::', rtrim($factoryClass, "()"));
                break;
            case is_callable($factoryClass):
                $callable = $factoryClass;
                break;
            default:
                throw new RuntimeException('Invalid selector_factory_class for component ' . $component);
        }
        return $callable;
    }

    protected function getSelectorClass(string $component) : ?string
    {
        return (($this->config[$component] ?? [])['selector'] ?? null)['class'] ?? null;
    }
    
    protected function instantiate(string $component, string $selector) : object
    {
        $selectorClass = $this->getselectorClass($component);
        if (! $selectorClass) {
            throw new RuntimeException("Cannot find selector class for '{$component}'");
        }
        $selector = new $selectorClass($this->getWorkbench(), $selector);
        $factory = $this->getSelectorFactory($component);
        if (! $factory) {
            throw new RuntimeException('Cannot instantiate ' . $component . ' `' . $selector . '` - no selector factory found');
        }
        return $factory($selector);
    }

    /**
     * {@inheritDoc}
     * @see ComponentRegistryInterface::getMarkdownPrinter()
     */
    public function getDocsForSelector(string $component, string $selector) : ?string
    {
        switch (true) {
            case $this->canInstantiate($component):
                $config = $this->config[$component] ?? null;
                if (
                    !$config
                    || !array_key_exists('documentation', $config)
                    || !array_key_exists('markdown_printer_class', $config['documentation'])
                ) {
                    return null;
                }

                $printerClass = $config['documentation']['markdown_printer_class'];
                if (! is_a($printerClass, MarkdownInstancePrinterInterface::class, true)) {
                    throw new RuntimeException('Cannot use ' . $printerClass . ' for documentation of ' . $component);
                }
                
                $instance = $this->instantiate($component, $selector);
                $printer = $printerClass::constructForInstance($instance);
                break;
            default:
                return null;
        }
        return $printer->getMarkdown();
    }
    
    public function findComponentKey(string $aliasOrUxonProperty) : ?string
    {
        if (array_key_exists($aliasOrUxonProperty, $this->config)) {
            return $aliasOrUxonProperty;
        }
        foreach ($this->config as $component) {
            if ($component['uxon_properties'] && in_array($aliasOrUxonProperty, $component['uxon_properties'], true)) {
                return $component['class'];
            }
        }
        return null;
    }

    /**
     * {@inheritDoc}
     * @see WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    protected function getComponentModel(string $component) : ?array
    {
        $component = mb_strtolower($component);
        return $this->config[$component] ?? null;
    }
    
    /**
     * {@inheritDoc}
     * @see ComponentRegistryInterface::searchPrototypes()
     */
    public function searchPrototypes(string $searchTerm, string $component, ?int $limit = null, int $offset = null) : DataSheetInterface
    {
        $compModel = $this->getComponentModel($component);
        if (! $compModel) {
            throw new InvalidArgumentException('Cannot search prototypes for unknown component "' . $component . '"');
        }
        $dataSheetUxon = new UxonObject($compModel['search_prototype_data'] ?? []);
        if ($dataSheetUxon->isEmpty()) {
            throw new InvalidArgumentException('Cannot search prototypes for component "' . $component . '" - no prototype search data defined');
        }
        
        // Render placeholders
        $dataSheetJson = $dataSheetUxon->toJson();
        $renderer = new BracketHashStringTemplateRenderer($this->getWorkbench());
        $renderer->addPlaceholder(new ArrayPlaceholders([
            '~query' => $searchTerm
        ]));
        $renderer->addPlaceholder(new ConfigPlaceholders($this->getWorkbench()));
        $renderer->addPlaceholder(new FormulaPlaceholders($this->getWorkbench()));
        $renderedJson = $renderer->render($dataSheetJson);
        $dataSheetUxon = UxonObject::fromJson($renderedJson);
        
        $dataSheet = DataSheetFactory::createFromUxon($this->getWorkbench(), $dataSheetUxon);
        $dataSheet->dataRead($limit, $offset);
        return $dataSheet;
    }
}