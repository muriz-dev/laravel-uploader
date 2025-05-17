<?php

namespace Overtrue\LaravelUploader;

use Illuminate\Http\Request;

class StrategyResolver
{
    /**
     * @var ConfigurationLoader
     */
    protected static $configLoader;
    
    /**
     * @var RequestValidator
     */
    protected static $requestValidator;
    
    /**
     * @var StrategyFactory
     */
    protected static $strategyFactory;
    
    /**
     * Initialize dependencies
     */
    protected static function initDependencies()
    {
        if (!isset(self::$configLoader)) {
            self::$configLoader = new ConfigurationLoader();
        }
        
        if (!isset(self::$requestValidator)) {
            self::$requestValidator = new RequestValidator();
        }
        
        if (!isset(self::$strategyFactory)) {
            self::$strategyFactory = new StrategyFactory();
        }
    }
    
    /**
     * Resolve strategy from HTTP request
     *
     * @param Request $request
     * @param string|null $name
     * @return Strategy
     */
    public static function resolveFromRequest(Request $request, string $name = null)
    {
        self::initDependencies();
        
        // Get configuration for the specified strategy
        $config = self::$configLoader->load($name);
        
        // Validate the request has the required file
        $formName = $config['name'] ?? 'file';
        self::$requestValidator->validateFileExists($request, $formName);
        
        // Create and return a Strategy instance
        return self::$strategyFactory->create($config, $request->file($formName));
    }
    
    /**
     * Set configuration loader (mainly for testing purposes)
     *
     * @param ConfigurationLoader $loader
     */
    public static function setConfigLoader(ConfigurationLoader $loader)
    {
        self::$configLoader = $loader;
    }
    
    /**
     * Set request validator (mainly for testing purposes)
     *
     * @param RequestValidator $validator
     */
    public static function setRequestValidator(RequestValidator $validator)
    {
        self::$requestValidator = $validator;
    }
    
    /**
     * Set strategy factory (mainly for testing purposes)
     *
     * @param StrategyFactory $factory
     */
    public static function setStrategyFactory(StrategyFactory $factory)
    {
        self::$strategyFactory = $factory;
    }
}

class ConfigurationLoader
{
    /**
     * Load configuration for a specified strategy
     *
     * @param string|null $name
     * @return array
     */
    public function load(?string $name = null): array
    {
        // Get default configuration
        $defaultConfig = config('uploader.strategies.default', []);
        
        // If no strategy specified, return default config
        if (empty($name) || $name === 'default') {
            return $defaultConfig;
        }
        
        // Get strategy-specific configuration
        $strategyConfig = config("uploader.strategies.{$name}", []);
        
        // Merge configurations (strategy config overrides default config)
        return \array_replace_recursive($defaultConfig, $strategyConfig);
    }
}

class RequestValidator
{
    /**
     * Validate that the request contains the specified file
     *
     * @param Request $request
     * @param string $formName
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function validateFileExists(Request $request, string $formName): void
    {
        \abort_if(
            !$request->hasFile($formName), 
            422, 
            \sprintf('No file "%s" uploaded.', $formName)
        );
    }
}

class StrategyFactory
{
    /**
     * Create a Strategy instance
     *
     * @param array $config
     * @param \Illuminate\Http\UploadedFile $file
     * @return Strategy
     */
    public function create(array $config, \Illuminate\Http\UploadedFile $file): Strategy
    {
        return new Strategy($config, $file);
    }
}
