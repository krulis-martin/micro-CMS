<?php

namespace uCMS\Processors;

use uCMS\Response;

/**
 * Interface for all content (response) processors.
 */
interface IProcessor
{
    /**
     * Process (augment) the response, which is an object being edited in place.
     * @param Response $response The response object to be processed
     * @return bool True, if other processors should also attempt to process the response;
     *              False, if the chain of responsibility should be immediately interrupted.
     */
    public function process(Response $response): bool;
}
