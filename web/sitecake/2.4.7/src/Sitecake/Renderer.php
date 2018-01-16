<?php
namespace Sitecake;

use Sitecake\Exception\UnregisteredElementTypeException;
use Sitecake\Util\HtmlUtils;
use Sitecake\Util\Utils;

class Renderer
{
    /**
     * @var array Options with paths
     */
    protected $options;

    /**
     * @var Site Reference to Site object
     */
    protected $site;

    public function __construct($_site, $options)
    {
        $this->site = $_site;
        $this->options = $options;
    }

    public function loginResponse()
    {
        return $this->injectLoginDialog($this->site->getDefaultPublicPage());
    }

    /**
     * @param Page $page
     *
     * @return mixed
     * @throws \Exception
     */
    protected function injectLoginDialog($page)
    {
        // Need to adjust links before any code is appended because of cached element positions
        $page->adjustLinks($this->options['entry_point_file_name'], function ($url) {
            $path = $this->site->urlToPath($url, $this->site->getDefaultIndex());
            if (!$this->site->isPageFile($path)) {
                return false;
            }
            return $path;
        });

        $page->appendCodeToHead($this->clientCodeLogin());

        return $this->render($page);
    }

    protected function clientCodeLogin()
    {
        $globals = 'var sitecakeGlobals = {' .
            "editMode: false, " .
            'serverVersionId: "2.4.7", ' .
            'phpVersion: "' . phpversion() . '@' . PHP_OS . '", ' .
            'serviceUrl:"' . $this->options['SERVICE_URL'] . '", ' .
            'configUrl:"' . $this->options['EDITOR_CONFIG_URL'] . '", ' .
            'forceLoginDialog: true' .
            '};';

        return HtmlUtils::wrapToScriptTag($globals) .
            HtmlUtils::scriptTag($this->options['EDITOR_LOGIN_URL'], [
                'data-cfasync' => 'false'
            ]);
    }

    public function editResponse($path)
    {
        $this->site->startEdit();

        return $this->injectEditorCode($this->site->getDraft($path), $path, $this->site->isDraftClean());;
    }

    /**
     * @param Page   $page
     * @param string $path
     * @param bool   $published
     *
     * @return mixed
     * @throws \Exception
     */
    protected function injectEditorCode($page, $path, $published)
    {
        // Need to adjust links before any code is appended because of cached element positions
        $page->adjustLinks($this->options['entry_point_file_name'], function ($url) use ($path) {
            $path = $this->site->urlToPath($url, $path);
            if (!$this->site->isPageFile($path)) {
                return false;
            }
            return $path;
        });

        $page->appendCodeToHead(HtmlUtils::css($this->options['PAGEMANAGER_CSS_URL']));
        $page->appendCodeToHead($this->clientCodeEditor($published));
        $page->appendCodeToHead(HtmlUtils::scriptTag($this->options['PAGEMANAGER_VENDORS_URL']));
        $page->appendCodeToHead(HtmlUtils::scriptTag($this->options['PAGEMANAGER_JS_URL']));

        return $this->render($page);
    }

    protected function clientCodeEditor($published)
    {
        $globals = 'var sitecakeGlobals = {' .
            'editMode: true, ' .
            'serverVersionId: "2.4.7", ' .
            'phpVersion: "' . phpversion() . '@' . PHP_OS . '", ' .
            'serviceUrl: "' . $this->options['SERVICE_URL'] . '", ' .
            'configUrl: "' . $this->options['EDITOR_CONFIG_URL'] . '", ' .
            'draftPublished: ' . ($published ? 'true' : 'false') . ', ' .
            'entryPoint: "' . $this->options['entry_point_file_name'] . '",'  .
            'indexPageName: "' . $this->site->getDefaultIndex() . '"' .
            '};';

        return HtmlUtils::wrapToScriptTag($globals) .
            HtmlUtils::scriptTag($this->options['EDITOR_EDIT_URL'], [
                'data-cfasync' => 'false'
            ]);
    }

    public function render(Page $page)
    {
        return (string)$page;
    }
}
