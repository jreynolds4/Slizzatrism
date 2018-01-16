<?php

namespace Sitecake\Util;

use Sitecake\Exception\BadArgumentException;
use Sitecake\Exception\Http\BadRequestException;
use WideImage\WideImage;

class Image
{
    /**
     * @var \WideImage\Image|\WideImage\PaletteImage|\WideImage\TrueColorImage
     */
    protected $_img;

    /**
     * @var string
     */
    protected $_source;

    /**
     * @var string
     */
    protected $_path = '';

    /**
     * @var bool
     */
    protected $_isVirtual;

    /**
     * @var bool
     */
    protected $_isAnimated;

    /**
     * @var bool
     */
    protected $_tmpName = '';

    /**
     * @var string
     */
    protected $_filename;

    /**
     * @var string
     */
    protected $_extension;

    /**
     * @var string
     */
    protected $_format;

    /**
     * Image constructor.
     *
     * @param string $resource Image resource : URL, existing file path, input stream (php://input) or image data
     * @param string $format
     */
    public function __construct($resource, $format = '')
    {
        if (!is_string($resource)) {
            throw new BadArgumentException('Passed string is not a valid image resource');
        }

        if (Utils::isURL($resource)) {
            $this->_loadFromURL($resource);
        } elseif (@file_exists($resource)) {
            $this->_loadFromFile($resource);
        } elseif ($resource == 'php://input') {
            if (empty($format)) {
                throw new BadArgumentException('File format is mandatory for input stream image resource');
            }

            $this->_loadFromInputStream($format);
        } elseif (imagecreatefromstring($resource) !== false) {
            if (empty($format)) {
                throw new BadArgumentException('File format is mandatory for image data resource');
            }

            $this->_loadFromString($resource, $format);
        } else {
            throw new BadArgumentException('Passed string is not a valid image resource');
        }

        $this->_isAnimated = $this->_isAniGif($format);
        $this->_img = WideImage::loadFromString($this);
    }

    protected function _loadFromURL($url)
    {
        $referer = substr($url, 0, strrpos($url, '/'));
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_REFERER, $referer);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        try {
            $this->_source = $output;
            $parsedURL = parse_url($url);
            $this->_setImageInfo($parsedURL['path']);
            $this->_isVirtual = true;
        } catch (\Exception $e) {
            throw new BadRequestException(sprintf('Unable to load image from %s (referer: %s)', $url, $referer));
        }
    }

    protected function _setImageInfo($path, $format = '')
    {
        $pathInfo = pathinfo($path);
        $this->_filename = $pathInfo['filename'];
        $this->_format = $this->_extension = $pathInfo['extension'];
        if (!empty($format)) {
            $this->_format = $format;
        }
        $this->_path = realpath($pathInfo['dirname']);
    }

    protected function _loadFromFile($path)
    {
        $this->_setImageInfo($path);
        $this->_isVirtual = false;
    }

    protected function _loadFromInputStream($format)
    {
        $this->_source = file_get_contents('php://input');
        $this->_format = $format;
        $this->_isVirtual = true;
    }

    protected function _loadFromString($content, $format)
    {
        $this->_source = $content;
        $this->_format = $format;
        $this->_isVirtual = true;
    }

    protected function _isAniGif($format = '')
    {
        $filePath = $this->_isVirtual ? $this->_createTmpImage($format) : $this->filePath();
        // Create temporary file to read if image doesn't actually exists
        if (!($fh = @fopen($filePath, 'rb'))) {
            return false;
        }
        $count = 0;
        //an animated gif contains multiple "frames", with each frame having a
        //header made up of:
        // * a static 4-byte sequence (\x00\x21\xF9\x04)
        // * 4 variable bytes
        // * a static 2-byte sequence (\x00\x2C) (some variants may use \x00\x21 ?)

        // We read through the file til we reach the end of the file, or we've found
        // at least 2 frame headers
        while (!feof($fh) && $count < 2) {
            $chunk = fread($fh, 1024 * 100); //read 100kb at a time
            $count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
        }

        fclose($fh);

        return $count > 1;
    }

    protected function _createTmpImage($format = '')
    {
        $filePath = tempnam(sys_get_temp_dir(), 'Sag');

        $handle = @fopen($filePath, "w");
        fwrite($handle, (string)$this);
        fclose($handle);

        $this->_setImageInfo($filePath, $format);

        return $filePath;
    }

    public function filePath()
    {
        return $this->path() . $this->filename();
    }

    public function path()
    {
        return empty($this->_path) ? $this->_path : $this->_path . DIRECTORY_SEPARATOR;
    }

    public function filename($withExtension = true)
    {
        if (is_string($withExtension)) {
            $this->_filename = $withExtension;
        } elseif (is_bool($withExtension)) {
            return $this->_filename . ($withExtension ? '.' . $this->_extension : '');
        }

        return $this->_filename;
    }

    public function __destruct()
    {
        if ($this->_isVirtual) {
            unlink($this->filePath());
        }
    }

    public function __toString()
    {
        try {
            return $this->content();
        } catch (\Exception $e) {
            return '';
        }
    }

    public function content()
    {
        return $this->_isVirtual ? $this->_source : file_get_contents($this->filePath());
    }

    public function width()
    {
        return $this->_img->getWidth();
    }

    public function height()
    {
        return $this->_img->getHeight();
    }

    /**
     * @param string $extension
     *
     * @return string|void
     */
    public function extension($extension = '')
    {
        if ($extension) {
            $this->_extension = $extension;
        } else {
            return $this->_extension;
        }
    }

    public function format()
    {
        return $this->_format;
    }

    public function isAnimated()
    {
        return $this->_isAnimated;
    }

    public function resize($targetWidth)
    {
        $this->_img->resize($targetWidth);
        $this->_source = $this->_img->asString($this->_format);

        return $this;
    }

    public function crop($top, $left, $width, $height)
    {
        $this->_img->crop($left . '%', $top . '%', $width . '%', $height . '%');
        $this->_source = $this->_img->asString($this->_format);

        return $this;
    }
}
