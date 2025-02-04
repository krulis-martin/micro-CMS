<?php

namespace uCMS;

use uCMS\Processors\IProcessor;
use uCMS\Helpers\Strings;
use Latte;
use Exception;

/**
 * The application is both the container and executor of the rendering algorithm.
 */
class App
{
    // HTTP exit codes
    public const ERROR_CODE_404_NOT_FOUND = 404;
    public const ERROR_CODE_500_INTERNAL_ERROR = 500;

    // Incomplete list of HTTP error code translations
    public const ERROR_CODE_MESSAGES = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
    ];


    // Key used for URL params and cookies to store selected language.
    public const LANG_KEY = 'uCMS_lang';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var string|null
     * Original URI resolved by execute method.
     */
    private $uri = null;

    /**
     * @var string|null
     * Path extracted from original uri.
     */
    private $rawPath = null;

    /**
     * @var string|null
     * Path extracted from original uri without the base path.
     */
    private $relativePath = null;

    /**
     * @var array
     * Path without prefix and splitted by '/'.
     */
    private $path = [];

    /**
     * @var array
     * Parsed URI query (identical to $_GET, if current request URI is processed).
     */
    private $query = [];

    /**
     * @var string|null
     * Identifier of selected language (null if i18n is switched off).
     */
    private $currentLang = null;

    /**
     * Initialize container with given configuration.
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Read-only accessor to private values.
     */
    public function __get($name)
    {
        if (isset($this->$name)) {
            return $this->$name;
        } else {
            return null;
        }
    }

    /**
     * Isset tester that accompanies the read-only private values accessor.
     */
    public function __isset($name)
    {
        return isset($this->$name);
    }

    /*
     * Internationalization Support
     */

    public function getBaseUri()
    {
        return $this->config->value('baseUri', '/');
    }

    /**
     * Preprocess the string URI (remove prefix and query) and split it by '/'.
     * @param string $uri URI to be preprocessed
     */
    private function preprocessUri(string $uri)
    {
        $this->uri = $uri;
        if (strpos($uri, '?') !== false) {
            list($this->rawPath, $queryStr) = explode('?', $uri, 2);
            if ($queryStr) {
                parse_str($queryStr, $this->query);
            }
        } else {
            $this->rawPath = $uri;
        }

        $baseUri = $this->getBaseUri();
        $trimmedPath = Strings::removePrefix($this->rawPath, $baseUri);
        if ($baseUri && $trimmedPath == $this->rawPath) { // nothing was trimmed...
            throw new Exception("Given URI path '$this->rawPath' is not prefixed with base URI path '$baseUri'.");
        }

        $this->path = array_values(array_filter(explode('/', $trimmedPath)));
        $this->relativePath = implode('/', $this->path);

        if ($this->path && is_dir($this->getPath())) {
            $this->path[] = 'index'; // default file in a directory
        }
    }

    /**
     * Convert parsed URI path (array of tokens) into actual path.
     * @param string[]|null $path Array of string tokens (steps of URI path). If missing, $this->path is used instead.
     * @return string
     */
    private function getPath(array $path = null): string
    {
        if ($path === null) {
            $path = $this->path;
        }
        return __DIR__ . '/../' . implode('/', $path);
    }

    /**
     * Are locales present in configuration (internationalization is active).
     */
    public function getLangs(): ?array
    {
        $langs = $this->config->value('langs', []);
        return ($langs && count($langs) > 1) ? $langs : null;
    }

    /**
     * Attempt to get the best available language from Accept-Language HTTP header.
     * @return string|null Language identifier, null if no suitable lang was found.
     */
    private function getLangFromHTTP(): ?string
    {
        $langs = $this->getLangs();
        $acceptLangs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        foreach ($acceptLangs as $lang) {
            $lang = preg_replace('/;q=.*$/', '', trim($lang)); // trim and remove quality...
            if (in_array($lang, $langs)) {
                return $lang;
            }

            // try also generic locale (e.g. 'en' instead of 'en-US')
            $lang = preg_replace('/-.*$/', '', $lang);
            if (in_array($lang, $langs)) {
                return $lang;
            }
        }

        return null;
    }

    /**
     * Process langs config and fingure out current language.
     * Handle language change (from query data).
     */
    private function initLangs()
    {
        $langs = $this->getLangs();
        if (!$langs) {
            return;
        }

        // If the lang is set in query parameters...
        if (array_key_exists(self::LANG_KEY, $this->query)) {
            // ... and it is valid -> change the current language.
            // Yes, we are violating semantics of GET request here, but the operation is idempotent.
            if (in_array($this->query[self::LANG_KEY], $langs)) {
                $this->currentLang = $this->query[self::LANG_KEY];
                setcookie(self::LANG_KEY, $this->currentLang);
            }

            // Remove the lang from query parameters and redirect to self...
            unset($this->query[self::LANG_KEY]);
            $url = $this->rawPath;
            if ($this->query) {
                $url .= '?' . http_build_query($this->query);
            }
            header("Location: $url");
            exit;
        }

        if (array_key_exists(self::LANG_KEY, $_COOKIE) && in_array($_COOKIE[self::LANG_KEY], $langs)) {
            // If the lang is set in cookies ...
            $this->currentLang = $_COOKIE[self::LANG_KEY];
        } elseif (($httpLang = $this->getLangFromHTTP()) !== null) {
            // Try to get the language from Accept-Language HTTP header...
            $this->currentLang = $httpLang;
        } elseif (in_array('en', $langs)) {
            // English is prefered default...
            $this->currentLang = 'en';
        } else {
            // If everything fails, get first lang of the config...
            $this->currentLang = reset($langs);
        }
    }

    /**
     * Get path to template file for given HTTP error.
     * @param int $code HTTP error code
     * @return string|null Path or null if no template is available
     */
    private function getErrorTemplate(int $code): ?string
    {
        $templateDir = $this->getTemplatesDirectory();
        $candidates = ["$templateDir/error_$code.latte", "$templateDir/error.latte"];
        foreach ($candidates as $template) {
            if (is_file($template) && is_readable($template)) {
                return $template;
            }
        }
        return null;
    }

    /**
     * Show page with error HTTP response (4xx or 5xx) and terminate.
     * @param int $code HTTP response code to be set
     */
    public function showErrorPage(int $code = self::ERROR_CODE_500_INTERNAL_ERROR)
    {
        http_response_code($code);
        $message = array_key_exists($code, self::ERROR_CODE_MESSAGES) ? self::ERROR_CODE_MESSAGES[$code] : '';

        $template = $this->getErrorTemplate($code);
        if ($template) {
            $latte = $this->createLatteEngine();
            $latte->render($template, ['code' => $code, 'message' => $message]);
        } else {
            header('Content-Type: text/plain');
            echo "HTTP Response: $code $message";
        }
        exit;
    }


    /**
     * Get content processor object from configuration.
     * @param mixed $key Key from the config. array of processors
     * @param mixed $value Value from the config. array of processors
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
        $this->preprocessUri($uri);
        $this->initLangs();

        if (!$this->path) {
            $this->showErrorPage(self::ERROR_CODE_404_NOT_FOUND); // and die
        }

        $rootDir = $this->path[0];
        $processors = $this->config->directories->value($rootDir, null);
        if ($processors === null) {
            // Trying to access directory not explicitly listed in config
            $this->showErrorPage(self::ERROR_CODE_404_NOT_FOUND); // and die
        }

        // Prepare the response...
        $response = new Response($this, $this->getPath(), $this->currentLang);
        $handled = false;

        foreach ($processors as $key => $value) {
            // Get the processor class based on config parameters
            $processor = $this->getProcessor($key, $value);
            if (!$processor) {
                throw new Exception("Unable to determine processor class from configuration of '$rootDir'.");
            }

            // Call the processor to augment the response
            // (the processor may also choose to complete the response and die).
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

    /**
     * Return directory where the templates should be found.
     * @return string
     */
    public function getTemplatesDirectory(): string
    {
        return __DIR__ . '/../' . $this->config->value('templates', 'templates');
    }

    /**
     * Factory for Latte engine objects.
     */
    public function createLatteEngine()
    {
        $latte = new Latte\Engine();
        $tmpDir = $this->config->value('tmpDir', 'tmp');
        if ($tmpDir[0] !== '/') {
            $tmpDir = __DIR__ . '/../' . $tmpDir . '/latte';
        }
        $latte->setTempDirectory($tmpDir);
        return $latte;
    }
}
