<?php

namespace uCMS\Processors;

use uCMS\Response;

/**
 * Preprocessor that handles PHP scriptlets (that generate HTML fragment embedded in layout template).
 */
class Php implements IProcessor
{
    public const PHP_FILE_EXTENSIONS = [ 'php' ];

    /**
     * Internal function that uses output buffering to gather the results of a PHP script
     * and set it as response contents.
     */
    private function executePhpScript(Response $response)
    {
        ob_start();
        require $response->filePath;
        $response->contents = ob_get_contents();
        ob_end_clean();
    }

    public function process(Response $response): bool
    {
        if ($response->contents) {
            return false;
        }

        if ($response->tryFilePathExtensions(self::PHP_FILE_EXTENSIONS)) {
            $this->executePhpScript($response);
            return true; // we have handled this
        }

        return false;
    }
}
