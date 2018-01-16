<?php

namespace Sitecake\Services\Upload;

use League\Flysystem\FilesystemInterface;
use Sitecake\Exception\Http\BadRequestException;
use Sitecake\Services\Service;
use Sitecake\Util\Utils;

class UploadService extends Service
{
    protected static $forbidden;

    /**
     * @var FilesystemInterface
     */
    protected $fs;
    /**
     * @var \Sitecake\Site
     */
    protected $site;

    public function __construct($ctx)
    {
        $this->fs = $ctx['fs'];
        $this->site = $ctx['site'];

        self::$forbidden = $ctx['FORBIDDEN_FILE_EXTENSIONS'];
    }

    public function upload($request)
    {
        if (!$request->headers->has('x-filename')) {
            throw new BadRequestException('Filename is missing (header X-FILENAME)');
        }
        $filename = base64_decode($request->headers->get('x-filename'));
        $pathInfo = pathinfo($filename);
        $destinationPath = Utils::resourceUrl(
            $this->site->draftPath() . '/files',
            Utils::sanitizeFilename($pathInfo['filename']),
            null,
            null,
            $pathInfo['extension']
        );

        if (!$this->isSafeExtension($pathInfo['extension'])) {
            return $this->json($request, [
                'status' => 1,
                'errMessage' => 'Forbidden file extension ' . $pathInfo['extension']
            ], 200);
        }

        $res = $this->fs->writeStream($destinationPath, fopen("php://input", 'r'));

        if ($res === false) {
            return $this->json($request, [
                'status' => 1,
                'errMessage' => 'Unable to upload file ' . $pathInfo['filename'] . '.' . $pathInfo['extension']
            ], 200);
        } else {
            $this->site->saveLastModified($destinationPath);
            $this->site->markPathDirty($destinationPath);

            $referer = parse_url($request->headers->get('referer'), PHP_URL_QUERY);

            $path = '';
            if (!empty($referer) && strpos($referer, 'scpage=') !== false) {
                parse_str($referer, $query);
                $path = $query['scpage'];
            }

            return $this->json($request, [
                'status' => 0,
                'url' => $this->site->pathToUrl($this->site->stripDraftPath($destinationPath), $path)
            ], 200);
        }
    }

    protected function isSafeExtension($ext)
    {
        return !in_array(strtolower($ext), self::$forbidden);
    }
}
