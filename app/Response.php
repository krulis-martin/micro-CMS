<?php

namespace uCMS;

use Exception;
use Latte;

/**
 * Wrapper that holds the contents of the response.
 * This in not a HTTP response in PSR-7 sense.
 */
class Response
{
    /*
     * External interface (used by Processors)
     */

    /**
     * Path to the file to be loaded as contents.
     * @var string
     */
    public $filePath;

    /**
     * The contents of the page. The contents may virtually anything,
     * but at the end it is casted to string.
     * If the contents is null at the end, the file is loaded without any processing.
     * @var mixed
     */
    public $contents = null;

    /**
     * Additional HTTP headers that should be set before the contents is sent.
     * These headers are set only if no latte template is used.
     */
    public $additionalHeaders = [];

    /**
     * Path to the latte template to be used for rendering.
     * If null, the contents is passed out directly.
     * @var string|null
     */
    public $latteTemplate = null;

    /**
     * Parameters for the latte template.
     * @var array
     */
    public $latteParameters;

    /**
     * Validate whether actual file path (with optional suffix) is valid.
     * I.e., it points to a file that exists and is readable.
     * @param string $suffix Optional suffix to be appedned to the path
     * @return bool
     */
    public function isFilePathValid(string $suffix = ''): bool
    {
        $filePath = $this->filePath . $suffix;
        return is_file($filePath) && is_readable($filePath);
    }

    /**
     * Return the extension of the file path.
     * @return string
     */
    public function getFilePathExtension(): string
    {
        return pathinfo($this->filePath, PATHINFO_EXTENSION);
    }

    /**
     * Algorithm that tries to match the path with possible allowed extensions.
     * If one of the extensions create valid path, the path is updated.
     * The path is never updated if it was valid at the beginning.
     * @param string[] $extensions Allowed extensions
     * @return bool True, if the path (after possible update) holds allowed extension.
     */
    public function tryFilePathExtensions(array $extensions): bool
    {
        if ($this->isFilePathValid()) {
            // The file path is an exact match.
            $ext = $this->getFilePathExtension();
            return $ext && in_array($ext, $extensions);
        }

        // Prepare language suffixes (like `_en`) for the possible translations
        if ($this->lang !== null) {
            $langs = [ "_$this->lang", '' /* no translations is tested right after exact match */ ];
            foreach ($this->app->getLangs() as $lang) {
                if ($lang !== $this->lang) {
                    $langs[] = "_$lang";
                }
            }
        } else {
            $langs = [ '' ]; // no suffix
        }

        // Let's try appending allowed extensions using path as prefix.
        foreach ($langs as $lang) {
            foreach ($extensions as $ext) {
                if ($this->isFilePathValid("$lang.$ext")) {
                    $this->filePath .= "$lang.$ext";
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Add specific content type header with MIME declaration.
     * @param string $mime
     */
    public function setContentType(string $mime)
    {
        $this->additionalHeaders['Content-Type'] = $mime;
    }

    /**
     * Explicitly load the contents of the target path.
     */
    public function loadContents()
    {
        $this->contents = file_get_contents($this->filePath);
    }

    /**
     * Remove the Latte layout template.
     */
    public function noTemplate()
    {
        $this->latteTemplate = null;
        $this->latteParameters = [];
    }

    /**
     * Helper method that retrieves localized config value.
     * @param mixed $value Either string value, or a map [ lang => translation ]
     * @return string
     */
    public function getLocalizedValue($value): string
    {
        // No localization (simple string)
        if (is_string($value)) {
            return $value;
        }

        if (is_object($value)) {
            $value = (array)$value;
        }
        if (is_array($value)) {
            if (!$this->lang) {
                return reset($value); // no translations -> pick first
            }

            // Exact match for current language found.
            if (array_key_exists($this->lang, $value)) {
                return $value[$this->lang];
            }

            // Let's try all languages in the order in which they are given (first match is taken)
            foreach ($this->app->getLangs() as $lang) {
                if (array_key_exists($lang, $value)) {
                    return $value[$lang];
                }
            }
        }
        throw new Exception("Unable to retrieve localized value.");
    }

    /*
     * Internal interface (called by App)
     */

    /**
     * @var App
     */
    private $app;

    /**
     * @var string|null
     * Current language (null if i18n is not enabled)
     */
    private $lang = null;

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

    /**
     * Initialize the response
     * @param App $app Reference to application container
     * @param string $filePath Actual path to file being served (or its prefix)
     * @param string|null $lang Requested translation, null if i18n is not active
     */
    public function __construct(App $app, string $filePath, string $lang = null)
    {
        $this->app = $app;
        $this->lang = $lang;
        $this->filePath = $filePath;

        $template = $app->getTemplatesDirectory() . '/default.latte';
        if (is_file($template) && is_readable($template)) {
            $this->latteTemplate = $template;
            foreach ($app->config->value('project', []) as $key => $value) {
                $this->latteParameters[$key] = $this->getLocalizedValue($value);
            }
            $this->latteParameters['currentLanguage'] = $lang;
            $this->latteParameters['languages'] = $app->getLangs();
            $this->latteParameters['baseUri'] = $app->getBaseUri();
        }
    }

    /**
     * Whether the response is ready to be finalized (it has contents or existing path).
     * @return bool
     */
    public function isReady(): bool
    {
        return $this->contents !== null || (is_file($this->filePath) && is_readable($this->filePath));
    }

    /**
     * Assemble and print out the response.
     */
    public function finalize()
    {
        if (!$this->isReady()) {
            throw new Exception("Response contents file '$this->filePath' does not exist.");
        }

        if ($this->latteTemplate) {
            // Render contents using latte template...
            $latte = $this->app->createLatteEngine();
            $this->latteParameters['contents'] = $this->contents !== null
                ? (string)$this->contents
                : file_get_contents($this->filePath);
            $latte->render($this->latteTemplate, $this->latteParameters);
        } else {
            // Render contents as is...
            foreach ($this->additionalHeaders as $header => $value) {
                header("$header: $value");
            }

            if ($this->contents !== null) {
                echo $this->contents;
            } else {
                readfile($this->filePath);
            }
        }
    }
}
