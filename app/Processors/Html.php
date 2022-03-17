<?php

namespace uCMS\Processors;

use uCMS\Response;

/**
 * Very simple preprocessor that directly loads files with known HTML extensions.
 */
class Html implements IProcessor
{
    public const HTML_FILE_EXTENSIONS = [ 'html', 'htm' ];

    public function process(Response $response): bool
    {
        if ($response->contents) {
            return false;
        }

        if ($response->tryFilePathExtensions(self::HTML_FILE_EXTENSIONS)) {
            $response->loadContents();
            return true; // we have handled this
        }

        return false;
    }
}
