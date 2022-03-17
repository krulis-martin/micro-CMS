<?php

namespace uCMS\Processors;

use uCMS\Response;
use Parsedown;

/**
 * Preprocessor that converts Markdown files into HTML content.
 */
class Markdown implements IProcessor
{
    public const MARKDOWN_FILE_EXTENSIONS = [ 'md' ];

    public function process(Response $response): bool
    {
        if ($response->contents) {
            return false;
        }

        if ($response->tryFilePathExtensions(self::MARKDOWN_FILE_EXTENSIONS)) {
            $response->loadContents();
            $parsedown = new Parsedown();
            $response->contents = $parsedown->text($response->contents);
            return true; // we have handled this
        }

        return false;
    }
}
