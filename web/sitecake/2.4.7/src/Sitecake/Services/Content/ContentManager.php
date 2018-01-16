<?php
namespace Sitecake\Services\Content;

use Sitecake\Site;
use Sitecake\Util\Beautifier;

class ContentManager
{
    /**
     * Storage manager
     *
     * @var Site
     */
    protected $site;

    /**
     * Array containing all pages with path
     *
     * @var \ArrayObject<string, \ArrayObject<string, string|Sitecake\Page>>
     */
    protected $modifiedPages = [];

    /**
     * Content constructor.
     *
     * @param Site $site
     */
    public function __construct($site)
    {
        $this->site = $site;
    }

    public function save($data)
    {
        $containers = $this->filterChangedContainers($data);
        $this->setContainerContents($containers);
        $this->site->clearCachedContainersContent();
        $this->saveModifiedSourceFiles();

        return 0;
    }

    protected function filterChangedContainers($containers) {
        $changed = [];
        foreach ($containers as $containerName => $content) {
            $normalized = $this->normalizeContent($content);
            if ($this->site->checkCachedContainerContentChange($containerName, $normalized)) {
                $changed[$containerName] = $normalized;
            }
        }

        return $changed;
    }

    protected function normalizeContent($content) {
        // remove slashes
        if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
            $content = stripcslashes($content);
        }
        // Decode content
        $content = base64_decode($content);
        // Strip draft path from resources
        $content = $this->site->normalizeResourceUrls($content);
        return Beautifier::fixTags($content);
    }

    protected function setContainerContents($containerContents)
    {
        // Get pages that contains changed containers
        $pages = $this->site->getPagesWithContainers(array_keys($containerContents));
        if (!empty($pages)) {
            foreach ($pages as $pageData) {
                $this->setPageDirty($pageData);
                /* @var \Sitecake\SourceFile $page */
                $page = $pageData['page'];
                // Get container names for certain page
                $pageContainers = $this->site->getPageContainers($pageData['path']);
                // Filter only container contents that are actually contained inside certain page
                $containers = [];
                foreach ($containerContents as $containerName => $content) {
                    if (in_array($containerName, $pageContainers)) {
                        $containers[$containerName] = $content;
                    }
                }
                $page->setContainerContent($containers);
            }
        }
    }

    protected function setPageDirty($page)
    {
        if (!isset($this->modifiedPages[$page['path']])) {
            $this->modifiedPages[$page['path']] = $page['page'];
        }
    }

    protected function saveModifiedSourceFiles()
    {
        foreach ($this->modifiedPages as $path => $page) {
            $this->site->saveSourceFileContent($path, $page);
        }
    }
}
