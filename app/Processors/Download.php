<?php

namespace uCMS\Processors;

use uCMS\Response;

/**
 * Properly prepares the response as downloadable contents.
 */
class Download implements IProcessor
{
    public function process(Response $response): bool
    {
        if ($response->contents || !$response->isFilePathValid()) return false;

        $response->setContentType('application/octet-stream');
        $fileName = pathinfo($response->filePath, PATHINFO_BASENAME);;
        $response->additionalHeaders['Content-Disposition'] = "attachment; filename=\"$fileName\"";
        $response->noTemplate();

        return true;
    }
}
