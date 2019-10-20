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

        // Let's try appending allowed extensions using path as prefix.
        foreach ($extensions as $ext) {
            if ($this->isFilePathValid(".$ext")) {
                $this->filePath .= ".$ext";
                return true;
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

    /*
     * Internal interface (called by App)
     */

    /**
     * @var Config
     */
    private $config;

    /**
     * Initialize the response using configuration and file path extracted from URI.
     * @param Config $config
     * @param string $filePath
     */
    public function __construct(Config $config, string $filePath)
    {
        $this->config = $config;
        $this->filePath = $filePath;

        $templateDir = $config->value('templates', null);
        $template = __DIR__ . '/../' . $templateDir . '/default.latte';
        if ($templateDir && is_file($template) && is_readable($template)) {
            $this->latteTemplate = $template;
            $this->latteParameters = $config->value('project', []);
        }
    }

    /**
     * Whether the response is ready to be finalized (it has contents or existing path).
     * @return bool
     */
    public function isReady(): bool
    {
        return $this->contents || (is_file($this->filePath) && is_readable($this->filePath));
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
            $latte = new Latte\Engine;

            $tmpDir = $this->config->value('tmpDir', 'tmp');
            if ($tmpDir[0] !== '/') {
                $tmpDir = __DIR__ . '/../' . $tmpDir . '/latte';
            }
            $latte->setTempDirectory($tmpDir);

            // Add the contents to the template parameters...
            $this->latteParameters['contents'] = $this->contents
                ? (string)$this->contents
                : file_get_contents($this->filePath);

            $latte->render($this->latteTemplate, $this->latteParameters);
        } else {
            // Render contents as is...
            foreach ($this->additionalHeaders as $header => $value) {
                header("$header: $value");
            }

            if ($this->contents) {
                echo $this->contents;
            } else {
                readfile($this->filePath);
            }
        }
    }
}
