<?php

namespace uCMS;

use Exception;
use uCMS\Processors\IProcessor;
use uCMS\Helpers\Strings;


/**
 * The application is both the container and executor of the rendering algorithm.
 */
class App
{
    const ERROR_CODE_404_NOT_FOUND = 404;
    const ERROR_CODE_500_INTERNAL_ERROR = 500;

    /**
     * @var Config
     */
    private $config;


    public function __construct(Config $config)
    {
        $this->config = $config;
    }

   
    /**
     * Preprocess the string URI (remove prefix and query) and split it by '/'.
     * @param string $uri URI to be preprocessed
     * @return array
     */
    private function preprocessUri(string $uri): array
    {
        $baseUri = $this->config->value('baseUri', '');
        if (!$baseUri) return $uri;

        $trimmedUri = Strings::removePrefix($uri, $baseUri);
        if ($baseUri && $trimmedUri == $uri) { // nothing was trimmed...
            throw new Exception("Given URI '$uri' is not prefixed with base URI '$baseUri'.");
        }
        
        $trimmedUri = preg_replace('/[?].*$/', '', $trimmedUri);
        $parsed = explode('/', $trimmedUri);
        return array_values(array_filter($parsed));
    }


    /**
     * Convert parsed URI (array of tokens) into actual path.
     * @param string[] $parsedUri Array of string tokens (steps of URI path).
     * @return string
     */
    private function getPath(array $parsedUri): string
    {
        return __DIR__ . '/../' . implode('/', $parsedUri);
    }


    /**
     * Show page with error HTTP response (4xx or 5xx).
     * @param int $code HTTP response code to be set
     */
    public function showErrorPage(int $code = self::ERROR_CODE_500_INTERNAL_ERROR)
    {
        http_response_code($code);
        echo "Response Code $code (TODO)";   // TODO
        exit;
    }


    /**
     * Get processor object from configuration.
     * @param mixed $key Key from the config. array of processors
     * @param mixed $key Value from the config. array of processors
     * @return IProcessor|null
     */
    private function getProcessor($key, $value): ?IProcessor
    {
        if (is_string($value)) { // only the class name
            $className = "uCMS\\Processors\\$value";
            return new $className();
        } elseif (is_array($value)) { // class name and constructor parameters
            $params = reset($value);
            $key = key($value);
            $className = "uCMS\\Processors\\$key";
            return new $className(...$params);
        }

        return null;
    }


    /**
     * Main routine that process the URI and show appropriate page.
     * @param string $uri
     */
    public function execute(string $uri)
    {
        $parsedUri = $this->preprocessUri($uri);
        if (!$parsedUri) {
            $this->showErrorPage(self::ERROR_CODE_404_NOT_FOUND); // and die
        }

        if (is_dir($this->getPath($parsedUri))) {
            $parsedUri[] = 'index'; // default file in a directory
        }

        $rootDir = $parsedUri[0];
        $processors = $this->config->directories->value($rootDir, null);
        if ($processors === null) {
            // Trying to access directory not explicitly listed in config
            $this->showErrorPage(self::ERROR_CODE_404_NOT_FOUND); // and die
        }

        // Prepare the response...
        $response = new Response($this->config, $this->getPath($parsedUri));
        $handled = false;

        foreach ($processors as $key => $value) {
            // Get the processor class based on config parameters
            $processor = $this->getProcessor($key, $value);
            if (!$processor) {
                throw new Exception("Unable to determine processor class from configuration of '$rootDir'.");
            }

            // Call the processor to augment the response (the processor may also choose to complete the response and die).
            if (($handled = $processor->process($response))) {
                break;  // processor signals that the contents has been handled
            }
        }

        if ($response->isReady()) {
            if (!$handled) {
                readfile($response->filePath);
            } else {
                $response->finalize();
            }
        } else {
            $this->showErrorPage(self::ERROR_CODE_404_NOT_FOUND); // and die
        }
    }
}