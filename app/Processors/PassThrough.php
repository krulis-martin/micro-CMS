<?php

namespace uCMS\Processors;

use uCMS\Response;

/**
 * Simply passes the file avoiding the template and without any modifications.
 */
class PassThrough implements IProcessor
{
    public const DEFAULT_EXTENSIONS = [
        'aac'    => 'audio/aac',
        'avi'    => 'video/x-msvideo',
        'bin'    => 'application/octet-stream',
        'bmp'    => 'image/bmp',
        'bz'     => 'application/x-bzip',
        'bz2'    => 'application/x-bzip2',
        'css'    => 'text/css',
        'csv'    => 'text/csv',
        'doc'    => 'application/msword',
        'docx'   => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'eot'    => 'application/vnd.ms-fontobject',
        'gz'     => ' application/gzip',
        'gif'    => 'image/gif',
        'ico'    => 'image/vnd.microsoft.icon',
        'ics'    => 'text/calendar',
        'jar'    => 'application/java-archive',
        'jpeg'   => 'image/jpeg',
        'jpg'    => 'image/jpeg',
        'js'     => 'text/javascript',
        'json'   => 'application/json',
        'jsonld' => 'application/ld+json',
        'mid'    => 'audio/midi',
        'midi'   => 'audio/midi',
        'mp3'    => 'audio/mpeg',
        'mpeg'   => 'video/mpeg',
        'odp'    => 'application/vnd.oasis.opendocument.presentation',
        'ods'    => 'application/vnd.oasis.opendocument.spreadsheet',
        'odt'    => 'application/vnd.oasis.opendocument.text',
        'oga'    => 'audio/ogg',
        'ogv'    => 'video/ogg',
        'ogx'    => 'application/ogg',
        'opus'   => 'audio/opus',
        'otf'    => 'font/otf',
        'png'    => 'image/png',
        'pdf'    => 'application/pdf',
        'ppt'    => 'application/vnd.ms-powerpoint',
        'pptx'   => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'rar'    => 'application/x-rar-compressed',
        'rtf'    => 'application/rtf',
        'svg'    => 'image/svg+xml',
        'tar'    => 'application/x-tar',
        'tif'    => 'image/tiff',
        'tiff'   => 'image/tiff',
        'ttf'    => 'font/ttf',
        'txt'    => 'text/plain',
        'vsd'    => 'application/vnd.visio',
        'wav'    => 'audio/wav',
        'weba'   => 'audio/webm',
        'webm'   => 'video/webm',
        'webp'   => 'image/webp',
        'woff'   => 'font/woff',
        'woff2'  => 'font/woff2',
        'xls'    => 'application/vnd.ms-excel',
        'xlsx'   => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xml'    => 'application/xml',
        'zip'    => 'application/zip',
        '7z'     => 'application/x-7z-compressed',
    ];

    /**
     * @var array
     */
    private $extensions;

    /**
     * Constructor expects the list of recognized extensions and their MIME types.
     * @param array|null $extensions Array [extension => MIME], default list is used if omited
     */
    public function __construct(array $extensions = null)
    {
        $this->extensions = $extensions !== null ? $extensions : self::DEFAULT_EXTENSIONS;
    }


    public function process(Response $response): bool
    {
        if ($response->contents || !$response->isFilePathValid()) {
            return false;
        }

        $ext = $response->getFilePathExtension();
        if (array_key_exists($ext, $this->extensions)) {
            $response->setContentType($this->extensions[$ext]);
            $response->noTemplate();
            return true; // we have handled this
        }

        return false;
    }
}
