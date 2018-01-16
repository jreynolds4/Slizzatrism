<?php

namespace Sitecake\Services\Image;

use Sitecake\Exception\Http\BadRequestException;
use Sitecake\Services\Service;
use Sitecake\Util\Utils;
use WideImage\WideImage;

class ImageService extends Service
{
    protected static $imageExtensions;

    protected $context;

    /**
     * ImageService constructor.
     *
     * @param \Silex\Application $ctx
     */
    public function __construct($ctx)
    {
        $this->context = $ctx;

        self::$imageExtensions = $ctx['ALLOWED_FILE_EXTENSIONS'];
    }

    /**
     * Upload service
     *
     * @param $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function upload($request)
    {

        // obtain the uploaded file, load image and get its details (filename, extension)
        if (!$request->headers->has('x-filename')) {
            throw new BadRequestException('Filename is missing (header X-FILENAME)');
        }
        $filename = base64_decode($request->headers->get('x-filename'));
        $pathInfo = pathinfo($filename);

        if (!in_array(strtolower($pathInfo['extension']), self::$imageExtensions)) {
            return $this->json($request, [
                'status' => 1,
                'errMessage' => "$filename is not supported image file"
            ], 200);
        }

        $filename = Utils::sanitizeFilename($pathInfo['filename']);
        $ext = $pathInfo['extension'];
        $img = WideImage::load("php://input");

        // generate image set
        $res = $this->generateImageSet($img, $filename, $ext);

        $res = [
            'status' => 0,
            'srcset' => $res['srcset'],
            'ratio' => $res['ratio']
        ];

        return $this->json($request, $res, 200);
    }

    /**
     * Generates different image sizes for passed image based on defined 'image.srcset_widths' and
     * 'image.srcset_width_maxdiff' values from configuration
     *
     * @param \WideImage\Image $img
     * @param string $filename
     * @param string $ext
     *
     * @return array Array of generated images information and images ratio
     *               Images information contains its width, height and url (relative path)
     */
    protected function generateImageSet($img, $filename, $ext)
    {
        $width = $img->getWidth();
        $ratio = $width / $img->getHeight();

        $widths = $this->context['image.srcset_widths'];
        $maxDiff = $this->context['image.srcset_width_maxdiff'];
        rsort($widths);

        $maxWidth = $widths[0];
        if ($width > $maxWidth) {
            $width = $maxWidth;
        }

        $id = uniqid();

        $srcset = [];
        foreach ($this->__neededWidths($width, $widths, $maxDiff) as $targetWidth) {
            $targetPath = Utils::resourceUrl($this->__imgDir(), $filename, $id, '-' . $targetWidth, $ext);
            $targetImage = $img->resize($targetWidth);
            $targetHeight = $targetImage->getHeight();
            $this->context['fs']->write($targetPath, $targetImage->asString($ext));
            $this->context['site']->saveLastModified($targetPath);
            $this->context['site']->markPathDirty($targetPath);
            unset($targetImage);
            array_push($srcset, [
                'width' => $targetWidth,
                'height' => $targetHeight,
                'url' => $this->context['site']->base() . $targetPath
            ]);
        }

        return ['srcset' => $srcset, 'ratio' => $ratio];
    }

    /**
     * Returns array of widths base on starting (maximum) width, list of possible widths and
     * maximum difference (in percents) between two image widths in pixels so they could be considered similar
     *
     * @param float $startWidth
     * @param array $widths
     * @param float $maxDiff
     *
     * @return array
     */
    private function __neededWidths($startWidth, $widths, $maxDiff)
    {
        $res = [$startWidth];
        rsort($widths);
        $first = true;
        foreach ($widths as $i => $width) {
            if (!$first || ($first && ($startWidth - $width) / $startWidth > $maxDiff / 100)) {
                array_push($res, $width);
                $first = false;
            }
        }

        return $res;
    }

    /**
     * Returns image draft directory path
     *
     * @return string
     */
    private function __imgDir()
    {
        return $this->context['site']->draftPath() . '/images';
    }

    /**
     * External upload service
     *
     * @param $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function uploadExternal($request)
    {
        if (!$request->request->has('src')) {
            throw new BadRequestException('Image URI is missing');
        }

        $uri = $request->request->get('src');
        $referer = substr($uri, 0, strrpos($uri, '/'));
        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_REFERER, $referer);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        try {
            $img = WideImage::loadFromString($output);
        } catch (\Exception $e) {
            throw new BadRequestException(sprintf('Unable to load image from %s (referer: %s)', $uri, $referer));
        }
        unset($output);

        $urlInfo = parse_url($uri);
        $pathInfo = pathinfo($urlInfo['path']);
        $filename = $pathInfo['filename'];
        $ext = $pathInfo['extension'];

        // generate image set
        $res = $this->generateImageSet($img, $filename, $ext);

        $res = [
            'status' => 0,
            'srcset' => $res['srcset'],
            'ratio' => $res['ratio']
        ];

        return $this->json($request, $res, 200);
    }

    /**
     * Transform image service
     *
     * @param $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function image($request)
    {
        if (!$request->request->has('image')) {
            throw new BadRequestException('Image URI is missing');
        }
        $uri = $this->context['site']->stripBase($request->request->get('image'));

        if (!$request->request->has('data')) {
            throw new BadRequestException('Image transformation data is missing');
        }
        $data = $request->request->get('data');

        if (!$this->context['fs']->has($uri)) {
            throw new BadRequestException(sprintf('Source image not found (%s)', $uri));
        }
        $img = WideImage::loadFromString($this->context['fs']->read($uri));

        if (Utils::isScResourceUrl($uri)) {
            $info = Utils::resourceUrlInfo($uri);
        } else {
            $pathInfo = pathinfo($uri);
            $info = ['name' => $pathInfo['filename'], 'ext' => $pathInfo['extension']];
        }

        $data = explode(':', $data);
        $left = $data[0];
        $top = $data[1];
        $width = $data[2];
        $height = $data[3];
        $filename = $info['name'];
        $ext = $info['ext'];

        $img = $this->transformImage($img, $top, $left, $width, $height);

        // generate image set
        $res = $this->generateImageSet($img, $filename, $ext);

        $res = [
            'status' => 0,
            'srcset' => $res['srcset'],
            'ratio' => $res['ratio']
        ];

        return $this->json($request, $res, 200);
    }

    /**
     * Wrapper method for WideImage\Image::crop method
     *
     * @param \WideImage\Image $img
     * @param float $top
     * @param float $left
     * @param float $width
     * @param float $height
     *
     * @return \WideImage\Image
     */
    protected function transformImage($img, $top, $left, $width, $height)
    {
        return $img->crop($left . '%', $top . '%', $width . '%', $height . '%');
    }
}
