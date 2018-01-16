<?php
namespace Sitecake\Services\Pages;

use Sitecake\Exception\BadArgumentException;
use Sitecake\Site;
use Sitecake\Util\Utils;

class PageManager
{
    protected $conf;

    /**
     * @var Site
     */
    protected $site;

    /**
     * List of site pages
     * @var array
     */
    protected $pageList;

    public function __construct(Site $site, $conf)
    {
        $this->site = $site;
        $this->conf = $conf;
    }

    /*static function update($newPages) {
        $pages = $this->pages->listPages();
        $pages = pages::get(true);
        pages::sanity_check($pages, $newPages);
        $pages = $pages['pages'];
        $navPages = pages::nav_pages($newPages);
        pages::updatePages($pages, $newPages, $navPages);
        pages::savePages($newPages);
        pages::remove_deleted_pages($pages, $newPages);
        pages::sitemap($navPages);
        return array('status' => 0, 'pages' => pages::reduce_pages($newPages));
    }*/

    /*static function sanity_check($pages, $newPages) {
        $homePages = util::arrayFilterProp($newPages, 'home', true);
        if (!(is_array($homePages) && count($homePages) == 1))
            throw new \Exception(
                'One and only one page should be marked as the home page');

        $homePage = util::arrayFindProp($newPages, 'home', true);
        if (!(isset($homePage['url']) && $homePage['url'] == 'index.html'))
            throw new \Exception(
                'The URL of the home page has to be index.html');

        array_walk($newPages, function($page) {
            if (!util::strEndsWith('.html', $page['url']))
                throw new \Exception('The page URL has to end with .html');
        });
    }*/

    public function update($pageUpdates, $menuUpdates)
    {
        /**
         * Go through all existing page files (from metadata) and compare it with received $pageUpdates
         *        - Create new pages (id not set tid set to source page)
         *        - Delete pages that could not be found in received $pageUpdates and we have it in page files
         *        - Update unnamed container names
         *        - Duplicate resources in unnamed containers
         *        - Update title and description metadata for all pages
         *        - Update menus in all files that contain menu
         *        - Update site metadata
         */
        $pagesMetadata = $this->site->loadMetadata()['pages'];
        $pathsForDeletion = $paths = array_keys($pagesMetadata);
        $pageUpdateMap = [];
        $metadata = [];
        $pages = [];
        if (!empty($pageUpdates)) {
            foreach ($pageUpdates as $no => $pageDetails) {
                // Get page path
                $path = $pageDetails['path'];

                // Gather metadata for later update
                $metadata[$path] = $pageDetails;

                if (!isset($pageDetails['id'])) {
                    if (!isset($pageDetails['tid'])) {
                        throw new BadArgumentException(['name' => 'pages[' . $no . ']']);
                    }

                    // This is a new page, create it from source
                    $page = $this->site->newPage($pageDetails, $path);

                    $metadata[$path]['id'] = Utils::id();
                    unset($metadata[$path]['tid']);
                } else {
                    // Find page path by ID in case it's changed
                    $originalPath = '';
                    foreach ($pagesMetadata as $pagePath => $details) {
                        if ($details['id'] == $pageDetails['id']) {
                            $originalPath = $pagePath;
                            break;
                        }
                    }

                    // If page path is changed and there is not re-created we need to delete old page
                    if ($path != $originalPath) {
                        // Page path is updated. Save i for menu metadata update
                        $pageUpdateMap[$originalPath] = $path;
                        if ((array_search($originalPath, $pathsForDeletion) === false) &&
                            // If original path exist as map value for some other path that means it's re-created
                            !in_array($originalPath, $pageUpdateMap)) {
                            array_push($pathsForDeletion, $originalPath);
                        }
                    }

                    $page = $this->site->updateMetaTags($originalPath, $pageDetails);
                }

                $pages[$path] = [
                    'path' => $path,
                    'page' => $page,
                ];

                if (($index = array_search($path, $pathsForDeletion)) !== false) {
                    unset($pathsForDeletion[$index]);
                }
            }

            // Remove deleted pages
            if (!empty($pathsForDeletion)) {
                $pageUpdateMap = array_merge($pageUpdateMap, $pathsForDeletion);
                $this->site->deleteDraftPages($pathsForDeletion);
            }

            // Update page files
            $this->site->updateSourceFiles($pages, $metadata);
        }

        // Update menus
        $this->site->updateMenus($menuUpdates, $pageUpdateMap);

        // Publish draft
        $this->site->publishDraft();
    }

    public function listPages()
    {
        $this->pageList = $this->site->loadMetadata()['pages'];
        $pageFilePaths = $this->site->loadPageFilePaths();

        $pages = [];
        foreach ($pageFilePaths as $no => $path) {
            if (isset($this->pageList[$path])) {
                $pages[] = array_merge($this->pageList[$path], ['path' => $path]);
            }
        }

        return $pages;
    }

    public function listMenus()
    {
        $return = [];
        $menus = $this->site->loadMetadata()['menus'];

        foreach ($menus as $name => $menu) {
            $return[$name] = array_values($menu['items']);
        }

        return $return;
    }
}
