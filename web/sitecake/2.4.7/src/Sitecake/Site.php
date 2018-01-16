<?php

namespace Sitecake;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Directory;
use League\Flysystem\Filesystem;
use League\Flysystem\Util;
use LogicException;
use RuntimeException;
use Silex\Application;
use Sitecake\DOM\Element\Container;
use Sitecake\DOM\Element\Link;
use Sitecake\DOM\Element\Menu;
use Sitecake\DOM\Element\Meta;
use Sitecake\DOM\Element\Title;
use Sitecake\DOM\ElementFactory;
use Sitecake\Exception\FileNotFoundException;
use Sitecake\Exception\InternalException;
use Sitecake\Util\Beautifier;
use Sitecake\Util\HtmlUtils;
use Sitecake\Util\Utils;

class Site
{
    const RESOURCE_TYPE_ALL = 'all';
    const RESOURCE_TYPE_SOURCE_FILE = 'page';
    const RESOURCE_TYPE_RESOURCE_FILE = 'resource';
    const RESOURCE_TYPE_IMAGE = 'image';
    const RESOURCE_TYPE_FILE = 'file';

    const SC_PAGES_EXCLUSION_CHARACTER = '!';

    /**
     * @var Application
     */
    protected $config;

    /**
     * @var Filesystem
     */
    protected $fs;

    protected $tmp;

    protected $draft;

    protected $backup;

    protected $ignores;

    protected $sourceFiles;

    protected $pageFiles;

    /**
     * Metadata that are stored in draft marker file.
     * Contains next information :
     *      + lastPublished : Timestamp when content was published last time
     *      + files : All site file paths with respective modification times for public [0] and draft [1] versions
     *      + pages : All page file paths with its details :
     *          * id - page id. The service should use the page id to identify and update an appropriate existing page,
     *                 even if its url/path has been changed.
     *          * url - website root/website relative URL/file path.
     *          * idx - nav bar index. -1 if not present in the nav bar or relative position within the nav bar.
     *          * title - page title. Content of the <title> tag.
     *          * navtitle - title used in the nav element.
     *          * desc - meta description. Content of the meta description tag.
     *      + menus : Contain paths of files that contains menu(s) [pages] and list of menu items [items]
     *      + containerMap: Array where keys are container names and
     *        under each key is array of paths that contains those containers
     *
     * @var array
     */
    protected $metadata;

    protected $defaultMetadataStructure
        = [
            'lastPublished' => 0,
            'files' => [],
            'pages' => [],
            'menus' => [],
            'containerMap' => [],
            'unpublished' => [], // Contains draft page paths that needs to be published
            'containerCache' => []
        ];

    /**
     * Stores base dir
     *
     * @var string
     */
    protected $base;

    /**
     * Stores default index page name
     *
     * @var array
     */
    protected $defaultIndexes = [];

    public function __construct(Filesystem $fs, $config)
    {
        $this->config = $config;
        $this->fs = $fs;

        $this->__ensureDirs();

        $this->ignores = [];
        $this->__loadIgnorePatterns();

        // Register element types
        ElementFactory::registerElementType(Container::type(), Container::class);
        ElementFactory::registerElementType(Menu::type(), Menu::class);
        ElementFactory::registerElementType(Meta::type(), Meta::class);
        ElementFactory::registerElementType(Title::type(), Title::class);
        ElementFactory::registerElementType(Link::type(), Link::class);

        // Initialize beautifier
        Beautifier::config([
            'indentation_character' => isset($app['content.indent']) ? $this->config['content.indent'] : '    '
        ]);

        $this->loadMetadata();
    }

    private function __ensureDirs()
    {
        // check/create directory images
        try {
            if (!$this->fs->ensureDir('images')) {
                throw new LogicException('Could not ensure that the directory /images is present and writable.');
            }
        } catch (RuntimeException $e) {
            throw new LogicException('Could not ensure that the directory /images is present and writable.');
        }
        // check/create files
        try {
            if (!$this->fs->ensureDir('files')) {
                throw new LogicException('Could not ensure that the directory /files is present and writable.');
            }
        } catch (RuntimeException $e) {
            throw new LogicException('Could not ensure that the directory /files is present and writable.');
        }
        // check/create sitecake-temp
        try {
            if (!$this->fs->ensureDir('sitecake-temp')) {
                throw new LogicException('Could not ensure that the directory /sitecake-temp is present and writable.');
            }
        } catch (RuntimeException $e) {
            throw new LogicException('Could not ensure that the directory /sitecake-temp is present and writable.');
        }
        // check/create sitecake-temp/<workid>
        try {
            $work = $this->fs->randomDir('sitecake-temp');
            if ($work === false) {
                throw new LogicException(
                    'Could not ensure that the work directory in /sitecake-temp is present and writable.'
                );
            }
        } catch (RuntimeException $e) {
            throw new LogicException(
                'Could not ensure that the work directory in /sitecake-temp is present and writable.'
            );
        }
        // check/create sitecake-temp/<workid>/tmp
        try {
            $this->tmp = $this->fs->ensureDir($work . '/tmp');
            if ($this->tmp === false) {
                throw new LogicException('Could not ensure that the directory '
                    . $work
                    . '/tmp is present and writable.');
            }
        } catch (RuntimeException $e) {
            throw new LogicException('Could not ensure that the directory '
                . $work
                . '/tmp is present and writable.');
        }
        // check/create sitecake-temp/<workid>/draft
        try {
            $this->draft = $this->fs->ensureDir($work . '/draft');
            if ($this->draft === false) {
                throw new LogicException('Could not ensure that the directory '
                    . $work
                    . '/draft is present and writable.');
            }
        } catch (RuntimeException $e) {
            throw new LogicException('Could not ensure that the directory '
                . $work
                . '/draft is present and writable.');
        }

        // check/create sitecake-backup
        try {
            if (!$this->fs->ensureDir('sitecake-backup')) {
                throw new LogicException(
                    'Could not ensure that the directory /sitecake-backup is present and writable.'
                );
            }
        } catch (RuntimeException $e) {
            throw new LogicException('Could not ensure that the directory /sitecake-backup is present and writable.');
        }
        // check/create sitecake-backup/<workid>
        try {
            $this->backup = $this->fs->randomDir('sitecake-backup');
            if ($work === false) {
                throw new LogicException(
                    'Could not ensure that the work directory in /sitecake-backup is present and writable.'
                );
            }
        } catch (RuntimeException $e) {
            throw new LogicException(
                'Could not ensure that the work directory in /sitecake-backup is present and writable.'
            );
        }
    }

    private function __loadIgnorePatterns()
    {
        if ($this->fs->has('.scignore')) {
            $scIgnores = $this->fs->read('.scignore');

            if (!empty($scIgnores)) {
                $this->ignores = preg_split('/\R/', $this->fs->read('.scignore'));
            }
        }
        $this->ignores = array_filter(array_merge($this->ignores, [
            '.scignore',
            '.scpages',
            'draft.drt',
            'draft.mkr',
            $this->config['entry_point_file_name'],
            'sitecake/',
            'sitecake-temp/',
            'sitecake-backup/'
        ]));
    }

    //<editor-fold desc="Metadata methods">
    /**
     * Retrieves site metadata from file. Also stores internal _metadata property.
     *
     * @return array If metadata written to file can't be un-serialized
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function loadMetadata()
    {
        if (!$this->metadata) {
            if ($this->draftExists()) {
                try {
                    if (($content = $this->fs->read($this->draftMarkerPath())) != '') {
                        // TODO: Should be remove at one point. Kept for compatibility
                        if ($metadata = @unserialize($content)) {
                            $this->metadata = $metadata;
                        } elseif ($metadata = json_decode($content, true)) {
                            $this->metadata = $metadata;
                        }

                        if (!isset($this->metadata)) {
                            throw new InternalException('Metadata could\'t be loaded');
                        }
                    } else {
                        $this->metadata = $this->defaultMetadataStructure;
                    }
                } catch (\Exception $e) {
                    throw new InternalException($e->getMessage());
                }
            } else {
                $this->metadata = $this->defaultMetadataStructure;
            }
        }

        return $this->metadata;
    }

    /**
     * Writes site metadata to file.
     *
     * @return bool Operation success
     * @throws \League\Flysystem\FileExistsException
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function writeMetadata()
    {
        if ($this->draftExists()) {
            return $this->fs->update($this->draftMarkerPath(), json_encode($this->metadata));
        }

        return $this->fs->write($this->draftMarkerPath(), json_encode($this->metadata));
    }

    /**
     * Saves lastPublished metadata value. Called after publish event finishes
     *
     * @throws \League\Flysystem\FileExistsException
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function saveLastPublished()
    {
        $this->loadMetadata();

        $this->metadata['lastPublished'] = time();

        $this->writeMetadata();
    }

    /**
     * Saves last modification time for passed path
     *
     * @param string $path
     *
     * @throws \League\Flysystem\FileExistsException
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function saveLastModified($path)
    {
        $this->loadMetadata();

        $index = 0;

        $filePath = $path;

        if (strpos($path, $this->draftPath()) === 0) {
            $filePath = $this->stripDraftPath($path);
            $index = 1;
        }

        if (!isset($this->metadata['files'][$filePath])) {
            $this->metadata['files'][$filePath] = [];
        }

        $meta = $this->fs->getMetadata($path);

        $this->metadata['files'][$filePath][$index] = $meta['timestamp'];

        $this->writeMetadata();
    }

    /**
     * Initializes (clears) containerMap metadata.
     * This method will be called on each page load
     */
    protected function initContainerMap() {
        $this->metadata['containerMap'] = [];
    }

    /**
     * Updates metadata with container mapping information
     *
     * @param array|string $containers One or list of containers to be stored to metadata
     * @param string       $path       Source file path that contains passed containers
     */
    protected function updateContainerMap($containers, $path)
    {
        if (is_string($containers)) {
            $containers = [$containers];
        }
        foreach ($containers as $container) {
            if (!isset($this->metadata['containerMap'][$container])) {
                $this->metadata['containerMap'][$container] = [$path];
            } else {
                $this->metadata['containerMap'][$container][] = $path;
            }
        }
    }

    /**
     * Returns whole container map or map for specific container name if passed
     *
     * @param null|string|array $containers Container name
     *
     * @return array|null
     */
    public function getContainerMap($containers = null)
    {
        if (!isset($this->metadata['containerMap'])) {
            return null;
        }

        if ($containers === null) {
            return $this->metadata['containerMap'];
        }

        $containers = is_array($containers) ? $containers : [$containers];

        $return = [];

        foreach ($containers as $container) {
            if (isset($this->metadata['containerMap'][$container])) {
                $return = array_merge($return, $this->metadata['containerMap'][$container]);
            }
        }

        return array_unique($return);
    }

    /**
     * Return container names contained in page on passed path
     *
     * @param string $path
     *
     * @return array
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function getPageContainers($path)
    {
        $this->loadMetadata();

        $filePath = $path;
        if (strpos($path, $this->draftPath()) === 0) {
            $filePath = $this->stripDraftPath($path);
        }
        return isset($this->metadata['files'][$filePath][2]) ? $this->metadata['files'][$filePath][2] : [];
    }

    /**
     * Gets page ID for specific page from metadata if it exist, if not returns false
     *
     * @param string $path
     *
     * @return bool|int
     * @throws \League\Flysystem\FileNotFoundException
     */
    protected function getPageID($path)
    {
        $this->loadMetadata();

        if (!isset($this->metadata['pages'][$this->stripDraftPath($path)])) {
            return false;
        }

        return $this->metadata['pages'][$this->stripDraftPath($path)]['id'];
    }

    /**
     * Removes references of passed path from metadata.
     * If second parameter is false, removes only reference for page metadata. All references are removed otherwise
     *
     * @param string $path
     * @param bool   $pageOnly
     */
    public function removePathFromMetadata($path, $pageOnly = false)
    {
        if (!$pageOnly) {
            if (isset($this->metadata['files'][$path])) {
                if (!empty($this->metadata['files'][$path][2])) {
                    $containers = $this->metadata['files'][$path][2];
                    foreach ($containers as $container) {
                        if (($index = array_search($path, $this->metadata['containerMap'][$container]))) {
                            array_splice($this->metadata['containerMap'][$container], $index, 1);
                        }
                    }
                }
                unset($this->metadata['files'][$path]);
            }
            if (!empty($this->metadata['menus'])) {
                foreach ($this->metadata['menus'] as &$menu) {
                    if (($index = array_search($path, $menu['pages'])) !== false) {
                        unset($menu['pages'][$index]);
                    }
                }
            }
        }
        if (isset($this->metadata['pages'][$path])) {
            unset($this->metadata['pages'][$path]);
        }
    }

    /**
     * Marks specific source file path as dirty
     *
     * @param string $path
     *
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \League\Flysystem\FileExistsException
     */
    public function markPathDirty($path)
    {
        $this->loadMetadata();

        if (array_search($path, $this->metadata['unpublished']) === false) {
            $this->metadata['unpublished'][] = $path;
            $this->writeMetadata();
        }
    }

    /**
     * Returns list of paths that needs to be published
     *
     * @return array|bool
     * @return array|bool|mixed
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function getUnpublishedPaths()
    {
        $this->loadMetadata();

        return empty($this->metadata['unpublished']) ? [] : $this->metadata['unpublished'];
    }

    /**
     * Updates pages metadata
     *
     * @param array $pages
     *
     * @throws \League\Flysystem\FileExistsException
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function savePagesMetadata($pages)
    {
        $this->loadMetadata();
        $this->metadata['pages'] = $pages;
        $this->writeMetadata();
    }

    /**
     * Caches container content for passed page.
     * Method is called on draft page rendering and stores container content caches to be compared on content saving
     *
     * @param \Sitecake\Page $page
     *
     * @throws \League\Flysystem\FileExistsException
     * @throws \League\Flysystem\FileNotFoundException
     */
    protected function cacheContainersContent(Page $page) {
        $this->loadMetadata();
        // Ensure containerCache key in metadata
        if (!isset($this->metadata['containerCache'])) {
            $this->metadata['containerCache'] = [];
        }
        // Get all page containers
        $containers = $page->containers();
        foreach($containers as $container) {
            // Cache container
            $this->metadata['containerCache'][$container->getIdentifier()] = trim(preg_replace_callback(
                '/>\s+</',
                function () {
                    return '><';
                },
                $container->getInnerHtml()
            ));
        }
        $this->writeMetadata();
    }

    /**
     * Returns cached containers content
     *
     * @return array|mixed|null
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function getCachedContainerContent()
    {
        $this->loadMetadata();
        return isset($this->metadata['containerCache']) ? $this->metadata['containerCache'] : null;
    }

    /**
     * Checks if passed content in changed from previously cached content for passed container name.
     * Returns true if content is changed or don't exist. False otherwise.
     *
     * @param string $containerName
     * @param string $content
     *
     * @return bool
     */
    public function checkCachedContainerContentChange($containerName, $content) {
        $containerContentCache = $this->getCachedContainerContent();
        return !isset($containerContentCache[$containerName]) ||
            $containerContentCache[$containerName] != trim(preg_replace_callback(
                '/>\s+</',
                function () {
                    return '><';
                },
                $content
            ));
    }

    /**
     * Clears cached container content
     *
     * @throws \League\Flysystem\FileExistsException
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function clearCachedContainersContent() {
        $this->loadMetadata();
        $this->metadata['containerCache'] = [];
        $this->writeMetadata();
    }
    //</editor-fold>

    /**
     * Returns the path of the draft directory.
     *
     * @return string the draft dir path
     */
    public function draftPath()
    {
        return $this->draft;
    }

    /**
     * Returns the path of the temporary directory.
     *
     * @return string the tmp dir path
     */
    public function tmpPath()
    {
        return $this->tmp;
    }

    /**
     * Finds all files from site root recursively that needs to be handled by sitecake
     */
    protected function findScPaths()
    {
        // Get valid source file extensions
        $extensions = $this->getValidSourceFileExtensions();

        // List first level files for passed directory
        $firstLevel = $this->fs->listWith(['path', 'type', 'basename', 'timestamp']);

        $files = [];

        $ignorePattern = '/^(?!' . implode('|', array_map(function ($path) {
                return preg_quote($path, '/');
            }, $this->ignores)) . ').*/';

        foreach ($firstLevel as $file) {
            if (($file['type'] == 'dir' && !in_array($file['path'] . '/', $this->ignores))
                || ($file['type'] == 'file' && !in_array($file['basename'], $this->ignores))
            ) {
                if ($file['type'] == 'dir') {
                    $subDirPaths = $this->fs->listWith(
                        ['path', 'type', 'timestamp'],
                        $file['path'],
                        true
                    );

                    foreach ($subDirPaths as $path) {
                        if ($path['type'] == 'dir') {
                            continue;
                        }

                        if (preg_match($ignorePattern, $path['path'])
                            && (((strpos($path['path'], 'images/') == 0 || strpos($path['path'], 'files/') == 0)
                                    && preg_match('/^.*\-sc[0-9a-f]{13}[^\.]*\..+$/', $path['path']))
                                || preg_match('/^.*\.(' . implode('|', $extensions) . ')?$/', $path['basename']))) {
                            if (isset($this->metadata['files'][$path['path']][0])) {
                                $files[$path['path']] = $this->metadata['files'][$path['path']];
                                $files[$path['path']][0] = $path['timestamp'];
                            } else {
                                $files[$path['path']] = [$path['timestamp']];
                            }
                        }
                    }
                } else {
                    if (((strpos($file['path'], 'images/') == 0 || strpos($file['path'], 'files/') == 0)
                            && preg_match('/^.*\-sc[0-9a-f]{13}[^\.]*\..+$/', $file['path']))
                        || preg_match('/^.*\.(' . implode('|', $extensions) . ')?$/', $file['basename'])) {
                        if (isset($this->metadata['files'][$file['path']][0])) {
                            $files[$file['path']] = $this->metadata['files'][$file['path']];
                            $files[$file['path']][0] = $file['timestamp'];
                        } else {
                            $files[$file['path']] = [$file['timestamp']];
                        }
                    }
                }
            }
        }

        $this->metadata['files'] = $files;

        $this->writeMetadata();
    }

    /**
     * Returns a list of paths of CMS related files from the given
     * directory. It looks for HTML files, images and uploaded files.
     * It ignores entries from .scignore filter the output list.
     *
     * @param  string $directory the root directory to start search into
     * @param  string $type      Indicates what type of resources should be listed (pages, resources or all)
     *
     * @return array            the output paths list
     */
    public function listScPaths(
        $directory = '',
        $type = self::RESOURCE_TYPE_ALL
    ) {
        if (empty($this->metadata['files'])) {
            $this->findScPaths();
        }

        $files = array_keys($this->metadata['files']);

        if (empty($directory) && $type == self::RESOURCE_TYPE_ALL) {
            return $files;
        }

        $pattern = '^';
        if ($type == self::RESOURCE_TYPE_SOURCE_FILE) {
            $extensions = $this->getValidSourceFileExtensions();
            $pattern .= '.*\.(' . implode('|', $extensions) . ')?$';
        } elseif ($type == self::RESOURCE_TYPE_RESOURCE_FILE) {
            $pattern .= '(files|images)\/.*\-sc[0-9a-f]{13}[^\.]*\..+$';
        } elseif ($type == self::RESOURCE_TYPE_IMAGE) {
            $pattern .= 'images\/.*\-sc[0-9a-f]{13}[^\.]*\..+$';
        } elseif ($type == self::RESOURCE_TYPE_FILE) {
            $pattern .= 'files\/.*\-sc[0-9a-f]{13}[^\.]*\..+$';
        }

        $paths = array_values(preg_grep('/' . $pattern . '/', $files));

        if ($directory) {
            foreach ($paths as &$path) {
                $path = $directory . '/' . $path;
            }
        }

        return $paths;
    }

    /**
     * Returns array of source file extensions that should be considered when handling source files.
     * Extensions are collected based on config 'site.default_pages' var
     *
     * @return array
     */
    protected function getValidSourceFileExtensions()
    {
        $defaultPages = is_array($this->config['site.default_pages'])
            ? $this->config['site.default_pages']
            :
            [$this->config['site.default_pages']];

        return Utils::map(function ($pageName) {
            $nameParts = explode('.', $pageName);

            return array_pop($nameParts);
        }, $defaultPages);
    }

    /**
     * Starts the site draft out of the public content.
     * It copies public pages and resources into the draft folder.
     */
    public function startEdit()
    {
        $draftExists = $this->draftExists();
        $this->findScPaths();
        $this->loadPageFilePaths();

        // containerMap needs to be refreshed on each page load so this is perfect place to do this
        $this->initContainerMap();

        if (!$draftExists) {
            $this->startDraft();
        } else {
            $this->updateFromSource();
        }
    }

    /**
     * Starts site draft. Copies all pages and resources to draft directory.
     * Also prepares container names, prefixes all urls in draft pages
     * and collects all navigation sections that appears inside pages.
     */
    protected function startDraft()
    {
        // Copy and prepare all resources and pages (normalize containers and prefix resource urls) and load navigation
        $paths = $this->listScPaths();
        foreach ($paths as $path) {
            $this->createDraftResource($path);

        }

        // Set lastPublished metadata value to current timestamp
        $this->saveLastPublished();

        // Set metadata
        $this->writeMetadata();
    }

    /**
     * Creates draft resource for passed path.
     *
     * Copies original file to draft dir normalizes container names and process menus if any present.
     * Also stores info regarding draft last modification time, pages and containers to metadata
     *
     * @param string $path Path to create resource file from
     *
     * @throws \Exception
     */
    protected function createDraftResource($path)
    {
        // Copy page/resource to draft dir
        $draftPath = $this->draftBaseUrl() . $path;
        $this->fs->copy($path, $draftPath);

        $containers = [];

        // This is a Page. Create draft and process it
        if (!$this->isResourcePath($path)) {
            // Initialize Page
            $sourceFile = new SourceFile($this->fs->read($draftPath));

            $sourceFile->normalizeContainerNames();

            if ($containers = $sourceFile->containerNames()) {
                $this->updateContainerMap($containers, $path);
            }

            // Update draft file content
            $this->fs->update($draftPath, (string)$sourceFile);

            if ($this->isPageFile($path)) {
                $id = Utils::id();
                $this->metadata['pages'][$path] = [
                    // Set page id
                    'id' => $id,
                    // Set page title
                    'title' => (string)$sourceFile->getPageTitle(),
                    // Set page description
                    'desc' => (string)$sourceFile->getPageDescription()
                ];
            }

            $this->processMenu($sourceFile, $path);
        }

        $draftMetadata = $this->fs->getMetadata($draftPath);

        // Write last modification time of draft file to metadata
        $this->metadata['files'][$path][1] = $draftMetadata['timestamp'];

        // Write container names for this page to metadata
        if (!empty($containers)) {
            $this->metadata['files'][$path][2] = $containers;
        }
    }

    //<editor-fold desc="Resources related methods">
    /**
     * Returns default page path
     *
     * @param string $directory
     *
     * @return array|mixed|string
     */
    public function getDefaultIndex($directory = '')
    {
        if (isset($this->defaultIndexes[$directory])) {
            return $this->defaultIndexes[$directory];
        }

        $paths = $this->listScPagesPaths($directory);

        $defaultPages = is_array($this->config['site.default_pages']) ?
            $this->config['site.default_pages'] :
            [$this->config['site.default_pages']];
        foreach ($defaultPages as $defaultPage) {
            $dir = $directory ? rtrim($directory, '/') . '/' : '';
            if (in_array($dir . $defaultPage, $paths)) {
                return $this->defaultIndexes[$directory] = $dir . $defaultPage;
            }
        }

        throw new FileNotFoundException([
            'type' => 'Default page',
            'files' => '[' . implode(', ', $defaultPages) . ']'
        ], 401);
    }

    /**
     * Returns draft for default (index) public page
     *
     * @return \Sitecake\Page
     * @throws \Exception
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function getDefaultPublicPage()
    {
        $pagePath = $this->getDefaultIndex();
        $page = new Page($this->fs->read($pagePath));

        // Set Page ID stored in metadata if exists
        if (!($pageID = $this->getPageID($pagePath))) {
            $pageID = Utils::id();
        }
        $page->setPageId($pageID);
        $page->addMetaRobots();

        return $page;
    }

    /**
     * Check if passed path is resource path
     *
     * @param string $path
     *
     * @return int
     */
    public function isResourcePath($path)
    {
        return (bool)preg_match('/^(files|images)\/.*\-sc[0-9a-f]{13}[^\.]*\..+$/', $path);
    }

    /**
     * Applies passed callable on resource URLs found in passed page
     *
     * @param SourceFile $page
     * @param callable $callback
     */
    public function processResourceUrls(SourceFile $page, $callback)
    {
        $page->update(function ($source) use ($callback) {
            $resourcePattern = '/[^\s"\',]*(?:files|images)\/[^\s]*\-sc[0-9a-f]{13}[^\.]*\.[0-9a-zA-Z]+/i';
            return preg_replace_callback($resourcePattern, function ($matches) use ($callback) {
                if ($this->isResourcePath($matches[0])) {
                    return $callback($matches[0]);
                }
                return $matches[0];
            }, $source);
        });
    }

    /**
     * Strips draft path from resource URLs in passed content
     *
     * @param string $content
     *
     * @return mixed
     */
    public function normalizeResourceUrls($content)
    {
        return preg_replace_callback(
            '/'. preg_quote($this->base() . $this->draftBaseUrl(), '/') .
            '((?:files|images)\/[^\s]*\-sc[0-9a-f]{13}[^\.]*\.[0-9a-zA-Z]+)/i',
            function ($matches) {
                return $this->getBase() . $matches[1];
            },
            $content
        );
    }

    /**
     * Returns list of resource URLs in passed Container's source
     *
     * @param Container $container
     *
     * @return array
     */
    public function getResourceUrls(Container $container) {
        $urls = [];
        preg_match_all(
            '/[^\s"\',]*(?:files|images)\/[^\s]*\-sc[0-9a-f]{13}[^\.]*\\.[0-9a-zA-Z]+/',
            $container->getInnerHtml(),
            $matches
        );
        foreach ($matches[0] as $match) {
            if (Utils::isScResourceUrl($match)) {
                array_push($urls, urldecode($match));
            }
        }

        return $urls;
    }

    /**
     * Returns a list of paths for source files
     *
     * @return array
     */
    protected function findSourceFiles()
    {
        if (isset($this->sourceFiles)) {
            return $this->sourceFiles;
        }

        return $this->sourceFiles = $this->listScPaths('', self::RESOURCE_TYPE_SOURCE_FILE);
    }

    /**
     * Returns list of paths of Page files.
     *
     * Files that are considered as Page files are by default all files with valid extensions from root directory
     * and all files stated in .scpages file if it's present.
     * Files from root directory that shouldn't be considered as Page files can be filtered out
     * by stating them inside .scpages prefixed with exclamation mark (!)
     * If directory is stated in .scpages file all files from that directory are considered as Page files
     *
     * @return array
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function loadPageFilePaths()
    {
        if ($this->pageFiles) {
            return $this->pageFiles;
        }

        // List all pages in document root
        $sourceFiles = $this->findSourceFiles();

        // If .scpages file present we need to add page files stated inside and filter out ones that starts with !
        if ($this->fs->has('.scpages')) {
            $scPages = $this->fs->read('.scpages');

            if (!empty($scPages)) {
                // Load page life paths from .scpages
                $scPagePaths = array_filter(preg_split('/\R/', $this->fs->read('.scpages')));

                $ignores = array_filter($scPagePaths, function ($path) {
                    return substr($path, 0, 1) === self::SC_PAGES_EXCLUSION_CHARACTER;
                });

                $includePatterns = array_map(function ($path) {
                    return preg_replace(['/\*/', '/\/$/'], ['.*', '/.*'], preg_quote($path, '/'));
                }, array_diff($scPagePaths, $ignores));

                // Find paths that should be ignored
                $ignorePatterns = array_map(function ($path) {
                    return preg_replace(['/\*/', '/\/$/'], ['.*', '/.*'], preg_quote($path, '/'));
                }, preg_replace(
                        '/' . preg_quote(self::SC_PAGES_EXCLUSION_CHARACTER) . '/',
                        '',
                        $ignores
                    )
                );

                $pattern = '[^\/]+';
                if (!empty($includePatterns)) {
                    $pattern .= '|' . implode('|', $includePatterns);
                }
                $sourceFiles = preg_grep('/^(' . $pattern . ')$/', $sourceFiles);

                // Do exclusion
                if (!empty($ignorePatterns)) {
                    $sourceFiles = preg_grep('/^((?!' . implode('|', $ignorePatterns) . ').*)$/', $sourceFiles);
                }
            }
        }

        return $this->pageFiles = array_values($sourceFiles);
    }

    /**
     * Returns whether passed path is page file or not
     *
     * @param string $path
     *
     * @return bool
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function isPageFile($path)
    {
        if (!isset($this->pageFiles)) {
            $this->loadPageFilePaths();
        }

        return in_array($path, $this->pageFiles);
    }

    /**
     * Returns URL to page file based on passed URL and 'pages.use_default_page_name_in_url' config var.
     * If var set to true, method will strip default index page name from URL.
     *
     * @param string $url
     *
     * @return string
     */
    public function pageFileUrl($url)
    {
        if ($this->config['pages.use_default_page_name_in_url']) {
            return $url;
        }
        $urlParts = explode('/', $url);
        $filename = array_pop($urlParts);
        $pathnameDir = implode('/', $urlParts);
        $defaultPages = is_array($this->config['site.default_pages']) ?
            $this->config['site.default_pages'] :
            [$this->config['site.default_pages']];
        if (in_array($filename, $defaultPages)) {
            if ($pathnameDir) {
                return $pathnameDir . '/';
            }

            return $this->getBase() ?: './';
        }

        return $url;
    }

    /**
     * Strips / and . in front of url
     *
     * @param $url
     *
     * @return mixed
     */
    public function pageFilePath($url)
    {
        return ltrim($url, './');
    }
    //</editor-fold>

    //<editor-fold desc="Menu processing">
    /**
     * Loads navigation sections found within passed page
     *
     * @param SourceFile $page
     * @param string     $path
     *
     * @throws \Exception
     */
    protected function processMenu(SourceFile $page, $path)
    {
        $menus = $page->menus();
        foreach ($menus as $menu) {

            $name = $menu->getIdentifier();

            if (!isset($this->metadata['menus'][$name])) {
                $this->metadata['menus'][$name] = [
                    'pages' => [$path],
                    'items' => []
                ];

                $menuItems = $menu->items();
                $this->metadata['menus'][$name]['items'] = [];

                if ($menuItems) {
                    foreach ($menuItems as $no => &$menuItem) {
                        if (Utils::isExternalLink($menuItem['url'])
                            || HtmlUtils::isAnchorLink($menuItem['url'])
                        ) {
                            $menuItem['type'] = Menu::ITEM_TYPE_CUSTOM;
                        } else {
                            $referencedPagePath =
                                $this->urlToPath($menuItem['url'], $this->isPageFile($path) ? $path : '');
                            if ($referencedPagePath !== false
                                && $this->isPageFile($referencedPagePath)
                            ) {
                                $menuItem['type'] = Menu::ITEM_TYPE_PAGE;
                                $menuItem['reference'] = $referencedPagePath;
                            }
                        }
                        $this->metadata['menus'][$name]['items'][] = $menuItem;
                    }
                }
            } elseif (array_search($path, $this->metadata['menus'][$name]['pages']) === false) {
                array_push($this->metadata['menus'][$name]['pages'], $path);
            }

            if (!empty($this->metadata['menus'][$name]['items'])) {
                return;
            }
        }
    }

    /**
     * Updates all menus in all pages with new menu content
     *
     * @param array $menuData      Menus metadata
     * @param array $pageUpdateMap Map of updated page paths where for updated paths keys are old paths and values are
     *                             new paths, and for deleted paths, keys are numeric
     *
     * @throws \Exception
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function updateMenus($menuData, $pageUpdateMap)
    {
        $this->loadMetadata();

        // We need only to update existing menus. If there is menu that doesn't exist, we do no action
        $sentMenus = [];
        foreach ($menuData as $no => $menu) {
            array_push($sentMenus, $menu['name']);
        }

        foreach ($this->metadata['menus'] as $name => &$menuMetadata) {
            if (!in_array($name, $sentMenus)) {
                continue;
            }
            $pages = [];
            foreach ($menuMetadata['pages'] as $no => $path) {
                // Check if path is changed or deleted
                if (array_key_exists($path, $pageUpdateMap)) {
                    $path = $pageUpdateMap[$path];
                } elseif (is_numeric(array_search($path, $pageUpdateMap))) {
                    continue;
                }

                array_push($pages, $path);

                $draftPath = $this->draftBaseUrl() . $path;

                $page = new SourceFile($this->fs->read($draftPath));

                $menuMetadata['items'] = $page->saveMenus(
                    $name,
                    $this->config['pages.nav.item_template'],
                    array_values($menuData[array_search($name, $sentMenus)]['items']),
                    function ($item) use ($path) {
                        if ($item['type'] == Menu::ITEM_TYPE_PAGE) {
                            $item['url'] = $this->pathToUrl($item['reference'], $path);

                            return $item;
                        }

                        return $item;
                    },
                    $this->config['pages.nav.active_class'],
                    function ($url) use ($path) {
                        if (Utils::isExternalLink($url)
                            || HtmlUtils::isAnchorLink($url)
                        ) {
                            return false;
                        }

                        return $path == $this->urlToPath($url, $path);
                    }
                );

                $this->fs->update($draftPath, (string)$page);
                $this->markPathDirty($draftPath);

                // Update last modified time in metadata
                $this->saveLastModified($draftPath);
            }
            $menuMetadata['pages'] = array_unique($pages);
        }
        $this->writeMetadata();
    }
    //</editor-fold>

    /**
     * Maps passed URL to file path based on from where is that URL referenced
     *
     * @param string $url
     * @param string $refererPath
     *
     * @return string
     */
    public function urlToPath($url, $refererPath = '')
    {
        $defaultIndex = $this->getDefaultIndex();

        // By default we return path relative to site root dir (root default page)
        if (empty($refererPath)) {
            $refererPath = $defaultIndex;
        }

        $path = $url;

        // Strip anchor from URL if present
        $path = explode(
            '#',
            // Strip '.' in front of url if it starts with './'
            ltrim($path, '.')
        );
        $path = array_shift($path);

        // Strip query string from URL if present
        $path = explode('?', $path);
        $path = array_shift($path);

        // Strip base dir url (just '/' if no base dir) if present
        $path = ltrim($this->stripBase($path), '/');

        if (empty($path)) {
            $path = $defaultIndex;
        }

        try {
            $referenceDir = rtrim(
                implode('/', array_slice(explode('/', $refererPath), 0, -1)),
                '/'
            );
            $path = Util::normalizePath((strpos($path, '../') !== false
                    ? $referenceDir . '/' : '') . $path);

            if (empty($path)) {
                $path = $defaultIndex;
            } else {
                if ($this->fs->has($path)) {
                    if ($this->fs->get($path) instanceof Directory) {
                        $path = $this->getDefaultIndex($path);
                    }
                } elseif ($this->fs->has($this->draftBaseUrl() . $path)) {
                    if ($this->fs->get($this->draftBaseUrl() . $path) instanceof Directory) {
                        $path = $this->getDefaultIndex($path);
                    }
                } else {
                    return $path;
                }
            }
        } catch (LogicException $e) {
            return $path;
        }

        return $path;
    }

    /**
     * Maps passed file path to URL based on from where that file should be referenced
     *
     * @param string $path
     * @param string $refererPath
     *
     * @return string
     */
    public function pathToUrl($path, $refererPath = '')
    {
        if (!empty($this->config['pages.use_document_relative_paths'])) {
            return $this->isResourcePath($path) ? $this->base() . $path : $this->pageFileUrl($this->base() . $path);
        }

        $pathParts = explode('/', $path);
        $basename = array_pop($pathParts);

        $refererPathParts = array_slice(explode('/', $refererPath), 0, -1);

        $url = '';
        $no = 0;
        foreach ($pathParts as $no => $part) {
            if (isset($refererPathParts[$no])) {
                if ($part == $refererPathParts[$no]) {
                    continue;
                }

                $url = str_repeat('../', count(array_slice($refererPathParts, $no))) .
                    implode('/', array_slice($pathParts, $no));
                break;
            } else {
                $url = implode('/', array_slice($pathParts, $no));
            }
        }

        if (empty($url)) {
            if (empty($pathParts)) {
                $url = str_repeat('../', count($refererPathParts));
            } elseif (isset($refererPathParts[$no + 1])) {
                $url = str_repeat('../', count(array_slice($refererPathParts, $no + 1)));
            }
        }

        return $this->isResourcePath($path) ?
            rtrim($url, '/') . '/' . $basename :
            $this->pageFileUrl(($url ? rtrim($url, '/') . '/' : '') . $basename);
    }

    /**
     * Returns base dir for website
     * e.g. if site is under http://www.sitecake.com/demo method will return /demo/
     *
     * @return string
     */
    public function base()
    {
        if (isset($this->base)) {
            return $this->base;
        }
        $self = (string)$_SERVER['PHP_SELF'];

        $serviceURLPosition = strlen($self) - strlen($this->config['SERVICE_URL']) - 1;
        if (strpos($self, '/' . $this->config['SERVICE_URL']) === $serviceURLPosition) {
            $base = str_replace('/' . $this->config['SERVICE_URL'], '', $self);
        } else {
            $base = dirname($self);
        }

        $base = preg_replace('#/+#', '/', $base);

        if ($base === DIRECTORY_SEPARATOR || $base === '.') {
            $base = '';
        }
        $base = implode('/', array_map('rawurlencode', explode('/', $base)));

        return $this->base = $base . '/';
    }

    /**
     * Returns base path based on 'pages.use_document_relative_paths' config var
     *
     * @return string
     */
    public function getBase()
    {
        if (!empty($this->config['pages.use_document_relative_paths'])) {
            return $this->base();
        }

        return '';
    }

    /**
     * Returns passed page url modified by stripping base dir if it exists
     *
     * @param string $url
     *
     * @return string Passed url stripped by base dir if found
     */
    public function stripBase($url)
    {
        $check = $url;
        $base = $this->base();
        if (strpos($check, $base) === 0) {
            return (string)substr($check, strlen($base));
        }

        return $url;
    }

    /**
     * Returns a list of CMS related page file paths from the
     * given directory.
     *
     * @param  string $directory a directory to read from
     *
     * @return array            a list of page file paths
     */
    public function listScPagesPaths($directory = '')
    {
        return $this->listScPaths($directory, self::RESOURCE_TYPE_SOURCE_FILE);
    }

    /**
     * Returns a list of draft page file paths.
     *
     * @return array a list of draft page file paths
     */
    public function listDraftPagePaths()
    {
        return $this->listScPaths($this->draftPath(), self::RESOURCE_TYPE_SOURCE_FILE);
    }

    /**
     * Updates draft resources from original and updates metadata
     */
    public function updateFromSource()
    {
        // Check if draft is clean (all changes are published)
        $isDraftClean = $this->isDraftClean();

        // Get all resources to check if some are outdated and needs to be overwritten
        $paths = $this->listScPaths();

        // Get all draft page files to be able to compare and delete files that don't exist any more
        $draftPaths = $this->listScPaths($this->draftPath());

        foreach ($paths as $path) {
            $draftPath = $this->draftBaseUrl() . $path;

            // Filter out draft path from all draft paths
            if (($index = array_search($draftPath, $draftPaths)) !== false) {
                unset($draftPaths[$index]);
            }

            $pathAdded = false;
            if (!$this->fs->has($draftPath)) {
                // This is a new resource/page and should be copied to draft
                $this->createDraftResource($path);
                $pathAdded = true;
            } else {
                // Check if draft file last modification time exists in metadata
                if (isset($this->metadata['files'][$path][1])) {
                    // Check last modification time for resource and overwrite draft file if it is needed and possible
                    if ($this->metadata['files'][$path][0] > $this->metadata['files'][$path][1]) {
                        if (!$this->isResourcePath($path)) {
                            // Initialize Page to check if it's editable or has menus
                            $page = new SourceFile($this->fs->read($path));

                            if (!empty($this->metadata['files'][$path][2]) || $page->hasMenu()) {
                                if ($isDraftClean || $this->config['pages.prioritize_manual_changes']) {
                                    $this->fs->delete($draftPath);
                                    $this->createDraftResource($path);
                                    $pathAdded = true;
                                }
                            } else {
                                $this->fs->delete($draftPath);
                                $this->createDraftResource($path);
                                $pathAdded = true;
                            }
                        } else {
                            // Overwrite resource file
                            $this->fs->delete($draftPath);
                            $this->fs->copy($path, $draftPath);
                        }
                    }
                } else {
                    $draftMetadata = $this->fs->getMetadata($draftPath);
                    if ($isDraftClean || ($this->metadata['lastPublished'] > $draftMetadata['timestamp'])) {
                        $this->fs->delete($draftPath);
                        $this->createDraftResource($path);
                        $pathAdded = true;
                    }

                    // Remember last modification times
                    $this->metadata['files'][$path][1] = $draftMetadata['timestamp'];
                    ksort($this->metadata['files'][$path]);
                }

                $page = new SourceFile($this->fs->read($draftPath));
                // If .scpages file is changed pages won't match so we need to check and add if needed
                if ($this->isPageFile($path)) {
                    if (!isset($this->metadata['pages'][$path])) {
                        $id = Utils::id();
                        $this->metadata['pages'][$path] = [
                            // Set page id
                            'id' => $id,
                            // Set page title
                            'title' => (string)$page->getPageTitle(),
                            // Set page description
                            'desc' => (string)$page->getPageDescription()
                        ];
                    }
                } elseif (isset($this->metadata['pages'][$path])) {
                    $this->removePathFromMetadata($path, true);
                }
            }

            /*
             * TODO: Should be removed in version 3.0.
             * TODO: When this is removed, versions before 2.4.6 will need to delete sitecake-temp dir after upgrade
             */
            // If containers not set in metadata we need to add them
            if (!$pathAdded) {
                if (!isset($page)) {
                    $page = new SourceFile($this->fs->read($draftPath));
                }
                if ($containers = $page->containerNames()) {
                    if (!isset($this->metadata['files'][$path][2])) {
                        $this->metadata['files'][$path][2] = $containers;
                    }
                    $this->updateContainerMap($containers, $path);
                }
            }
        }

        if (!empty($draftPaths) && ($isDraftClean || $this->config['pages.prioritize_manual_changes'])) {
            foreach ($draftPaths as $draftPath) {
                //$draftMetadata = $this->fs->getMetadata($draftPath);
                /**
                 * TODO: For now if page is deleted manually it's draft should also be deleted. This should be changed when unpublished changes are introduced
                 */
                //if ($this->metadata['lastPublished'] > $draftMetadata['timestamp']) {
                $this->fs->delete($draftPath);
                $path = $this->stripDraftPath($draftPath);
                $this->removePathFromMetadata($path);
                //}
            }
        }

        // Set metadata
        $this->writeMetadata();
    }

    //<editor-fold desc="Draft markers methods">
    /**
     * Checks if draft is created
     *
     * @return bool
     */
    protected function draftExists()
    {
        return $this->fs->has($this->draftMarkerPath());
    }

    /**
     * Returns path for draft.mkr file
     *
     * @return string
     */
    protected function draftMarkerPath()
    {
        return $this->draftPath() . '/draft.mkr';
    }

    /**
     * Returns path for 'draft.drt' marker file
     *
     * @return string
     */
    protected function draftDirtyMarkerPath()
    {
        return $this->draftPath() . '/draft.drt';
    }

    /**
     * Returns whether all changes are published
     *
     * @return bool
     */
    public function isDraftClean()
    {
        return !$this->fs->has($this->draftDirtyMarkerPath());
    }

    /**
     * Marks that all changes are published
     */
    public function markDraftClean()
    {
        $this->loadMetadata();
        if ($this->fs->has($this->draftDirtyMarkerPath())) {
            $this->fs->delete($this->draftDirtyMarkerPath());
        }

        if (!empty($this->metadata['unpublished'])) {
            $this->metadata['unpublished'] = [];
        }

        $this->writeMetadata();
    }

    /**
     * Marks that there are unsaved changes by create draft dirty file marker
     */
    public function markDraftDirty()
    {
        if (!$this->fs->has($this->draftDirtyMarkerPath())) {
            $this->fs->write($this->draftDirtyMarkerPath(), '');
        }
    }

    //</editor-fold>

    public function restore($version = 0)
    {
    }

    /**
     * Strips draft portion of a passed path
     *
     * @param $path
     *
     * @return bool|string
     */
    public function stripDraftPath($path)
    {
        if (strpos($path, $this->draftBaseUrl()) === 0) {
            return substr($path, strlen($this->draftBaseUrl()));
        }

        return $path;
    }

    /**
     * Returns base path for draft resources
     *
     * @return string
     */
    protected function draftBaseUrl()
    {
        return $this->draftPath() . '/';
    }

    /**
     * Returns draft page for passed path
     *
     * @param string $uri
     *
     * @return \Sitecake\Page
     * @throws \Exception
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function getDraft($uri)
    {
        $draftPagePaths = $this->listDraftPagePaths();
        $currentWorkingDir = getcwd();
        $executionDirectory = $this->draftBaseUrl();

        if (!empty($uri)) {
            $pagePath = $this->draftBaseUrl() . $uri;

            if ($this->fs->has($pagePath)
                && $this->fs->get($pagePath) instanceof Directory
            ) {
                $pagePath = $this->getDefaultIndex($pagePath);
            }
        } else {
            $pagePath = $this->getDefaultIndex($this->draftPath());
        }

        // Check if we need to change execution directory
        if ($dir = implode('/', array_slice(explode('/', $pagePath), 0, -1))) {
            $executionDirectory = $dir;
        }

        if (in_array($pagePath, $draftPagePaths)) {
            // Move execution to directory where requested page is because of php includes
            /* @var AbstractAdapter $adapter */
            $adapter = $this->fs->getAdapter();
            chdir($adapter->applyPathPrefix($executionDirectory));

            $page = new Page($this->fs->read($pagePath));

            // Normalize resource URLs
            $this->processResourceUrls($page, function ($resourceURL) {
                $url = ltrim($resourceURL, './');
                if (strpos('/' . $url, $this->base()) === 0) {
                    $url = $this->stripBase('/' . $url);
                }
                $url = $this->stripDraftPath($url);
                return $this->base() . $this->draftBaseUrl() . $url;
            });

            // Set Page ID stored in metadata
            $page->setPageId($this->getPageID($pagePath));

            // Add robots meta tag
            $page->addMetaRobots();

            // Cache all containers to compare it on update and skip containers that are not changed
            $this->cacheContainersContent($page);

            // Turn execution back to root dir
            chdir($currentWorkingDir);

            return $page;
        } else {
            throw new FileNotFoundException([
                'type' => 'Draft Page',
                'files' => $pagePath
            ], 401);
        }
    }

    /**
     * Returns array of page details that contains passed container.
     * Each page detail is consisted of Page object and path to that specific page.
     *
     * @param string|array $containers Container name
     *
     * @return array
     * @throws \Exception
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function getPagesWithContainers($containers)
    {
        $pages = [];
        // If 'containerMap' metadata isn't initialized, we need to initialize it
        if (($paths = $this->getContainerMap($containers)) === null) {
            $this->loadMetadata();
            $draftPagePaths = $this->listDraftPagePaths();
            foreach ($draftPagePaths as $pagePath) {
                $page = new SourceFile($this->fs->read($pagePath));
                $containers = is_array($containers) ? $containers : [$containers];
                foreach ($containers as $container) {
                    if ($page->hasContainer($container)) {
                        array_push($pages, [
                            'path' => $pagePath,
                            'page' => $page
                        ]);
                        break;
                    }
                }
            }
        } else {
            foreach ($paths as $pagePath) {
                $path = $this->draftBaseUrl() . $pagePath;
                array_push($pages, [
                    'path' => $path,
                    'page' => new SourceFile($this->fs->read($path))
                ]);
            }
        }

        return $pages;
    }

    /**
     * Saves source file content
     *
     * @param string     $path
     * @param SourceFile $page
     *
     * @throws \League\Flysystem\FileExistsException
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function saveSourceFileContent($path, SourceFile $page)
    {
        $this->loadMetadata();
        $this->markDraftDirty();
        $this->fs->update($path, (string)$page);
        $this->markPathDirty($path);
        $this->saveLastModified($path);
    }

    /**
     * Updates title and description for page stored under passed path
     *
     * @param string $path        Path of page
     * @param array  $pageDetails Array containing 'title' and 'description' values
     *
     * @return SourceFile
     *
     * @return \Sitecake\SourceFile
     * @throws \Exception
     */
    public function updateMetaTags($path, $pageDetails)
    {
        $path = $this->draftBaseUrl() . $path;
        if (!$this->fs->has($path)) {
            throw new FileNotFoundException([
                'type' => 'Source Page',
                'file' => $path
            ], 401);
        }

        $page = new SourceFile($this->fs->read($path));

        $page->setPageTitle($pageDetails['title']);
        $page->setPageDescription($pageDetails['desc']);

        return $page;
    }

    /**
     * Updates content for passed source files and adds new subdirectory pages to .scpages file if any
     *
     * @param $pages
     * @param $pagesMetadata
     *
     * @throws \League\Flysystem\FileExistsException
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function updateSourceFiles($pages, $pagesMetadata)
    {
        $scPagesPaths = [];
        if ($this->fs->has('.scpages')) {
            $scPages = $this->fs->read('.scpages');

            if (!empty($scPages)) {
                $scPagesPaths = array_values(array_filter(preg_split('/\R/', $scPages)));
            }
        }
        $initialScPagesCount = count($scPagesPaths);
        // Update page files
        foreach ($pages as $page) {
            $path = $this->draftBaseUrl() . $page['path'];

            if ($this->fs->has($path)) {
                $this->fs->update($path, (string)$page['page']);
            } else {
                $this->fs->write($path, (string)$page['page']);

                // If new page is in subdirectory we need to add it to .scpages file for it to be visible
                if (strpos($page['path'], '/') !== false
                    && array_search($page['path'], $scPagesPaths) === false
                ) {
                    array_push($scPagesPaths, $page['path']);
                }
            }
            // Update last modified time in metadata
            $this->saveLastModified($path);
        }

        // If there are new paths for .scpages file, we need to write it
        if ($initialScPagesCount < count($scPagesPaths)) {
            $this->fs->put('.scpages', implode("\n", $scPagesPaths));
        }

        // Save metadata
        $this->savePagesMetadata($pagesMetadata);
    }

    /**
     * Publishes changed draft files
     */
    public function publishDraft()
    {
        if ($this->draftExists()) {
            // Backup public files
            $this->backup();

            // Get all draft pages with all draft files referenced in those pages
            $unpublishedResources = $this->getUnpublishedPaths();

            foreach ($unpublishedResources as $no => $file) {
                $publicPath = $this->stripDraftPath($file);
                // Overwrite live file with draft only if draft actually exists
                if ($this->fs->has($file)) {
                    if (!$this->isResourcePath($publicPath)) {
                        $page = new SourceFile($this->fs->read($file));
                        $page->cleanupContainerNames();
                        $this->processResourceUrls($page, function ($resourceURL) use ($publicPath) {
                            if (strpos($resourceURL, $this->base() . $this->draftPath()) === 0) {
                                $resourceURL = $this->pathToUrl(
                                    $this->stripDraftPath(ltrim($resourceURL, './')),
                                    $publicPath
                                );
                            }

                            return $resourceURL;
                        });
                        $this->fs->put($publicPath, (string)$page);
                    } else {
                        if ($this->fs->has($publicPath)) {
                            $this->fs->delete($publicPath);
                        }
                        $this->fs->copy($file, $publicPath);
                    }
                    // Update last modified time in metadata
                    $this->saveLastModified($publicPath);
                } else {
                    // If draft file is missing we need to delete original
                    if ($this->fs->has($publicPath)) {
                        $this->fs->delete($publicPath);
                    }
                }
            }

            // Save last publishing time
            $this->saveLastPublished();
            // Mark draft clean
            $this->markDraftClean();
        }
    }

    //<editor-fold desc="Backup methods">
    /**
     * Creates backup of current public files handled by sitecake
     */
    public function backup()
    {
        if ($this->config['site.number_of_backups'] < 1) {
            return;
        }
        // Create backup dir
        $backupPath = $this->newBackupContainerPath();
        $this->fs->createDir($backupPath);
        // Create resource dirs
        $this->fs->createDir($backupPath . '/images');
        $this->fs->createDir($backupPath . '/files');
        // Copy files
        $paths = $this->listScPaths();
        foreach ($paths as $path) {
            // New files will only exists in draft dir, but would be added to metadata
            if ($this->fs->has($path)) {
                $newPath = $backupPath . '/' . $path;
                if (!$this->fs->has($newPath)) {
                    $this->fs->copy($path, $newPath);
                } else {
                    $this->fs->update($newPath, $this->fs->read($path));
                }
            }
        }
        // Remove expired backup files
        $this->cleanupBackup();
    }

    /**
     * Generates backup path based on current datetime
     *
     * @return string
     */
    protected function newBackupContainerPath()
    {
        $path = $this->backupPath() . '/' . date('Y-m-d-H.i.s') . '-'
            . substr(uniqid(), -2);

        return $path;
    }

    /**
     * Returns the path of the backup directory.
     *
     * @return string the backup dir path
     */
    public function backupPath()
    {
        return $this->backup;
    }

    /**
     * Remove all backups except for the last recent five.
     */
    protected function cleanupBackup()
    {
        $backups = $this->fs->listContents($this->backupPath());
        usort($backups, function ($a, $b) {
            if ($a['timestamp'] < $b['timestamp']) {
                return -1;
            } elseif ($a['timestamp'] == $b['timestamp']) {
                return 0;
            } else {
                return 1;
            }
        });
        $backups = array_reverse($backups);
        foreach ($backups as $idx => $backup) {
            if ($idx >= $this->config['site.number_of_backups']) {
                $this->fs->deleteDir($backup['path']);
            }
        }
    }
    //</editor-fold>

    public function newPage($sourcePage, $path)
    {
        $metadata = $this->loadMetadata();

        $sourcePath = '';
        foreach ($metadata['pages'] as $page => $details) {
            if ($details['id'] == $sourcePage['tid']) {
                $sourcePath = $page;
                break;
            }
        }

        $draftPath = $this->draftBaseUrl() . $sourcePath;

        if (empty($sourcePath) || !$this->fs->has($draftPath)) {
            throw new FileNotFoundException([
                'type' => 'Source Page',
                'files' => $sourcePath
            ], 401);
        }

        $page = new SourceFile($this->fs->read($draftPath));

        // Clear old container names
        $page->cleanupContainerNames();
        // Name unnamed containers
        $page->normalizeContainerNames();
        // Check for existing navigation in current page and store it if found
        $this->processMenu($page, $path);

        // Duplicate resources from unnamed containers
        $containers = $page->getUnNamedContainers();
        $resources = [];
        foreach ($containers as $container) {
            $resources = array_merge($resources, $this->getResourceUrls($container));
        }
        $sets = [];
        foreach ($resources as $resource) {
            /** @var array $resourceDetails */
            $resourceDetails = Utils::resourceUrlInfo($resource);
            if (array_key_exists($resourceDetails['id'], $sets)) {
                $id = $sets[$resourceDetails['id']];
            } else {
                $id = uniqid();
                $sets[$resourceDetails['id']] = $id;
            }
            $newPath = Utils::resourceUrl(
                $resourceDetails['path'],
                $resourceDetails['name'],
                $id,
                $resourceDetails['subid'],
                $resourceDetails['ext']
            );
            $this->fs->put($newPath, $this->fs->read($resource));
            $this->markPathDirty($newPath);
            // Update resource paths
            $page->update(function ($source) use ($resource, $newPath) {
                return preg_replace(
                    '/' . preg_quote($resource, '/') . '/',
                    $newPath,
                    $source
                );
            });
        }

        $page->setPageTitle($sourcePage['title']);
        $page->setPageDescription($sourcePage['desc']);
        $this->markPathDirty($this->draftBaseUrl() . $path);

        return $page;
    }

    /**
     * Deletes list of passed draft pages
     *
     * @param $paths
     */
    public function deleteDraftPages($paths)
    {
        foreach ($paths as $path) {
            $path = $this->draftBaseUrl() . $path;
            $this->markPathDirty($path);
            $this->fs->delete($path);
        }
    }

    public function editSessionStart()
    {
    }

    /**
     * Removes all draft files
     */
    protected function removeDraft()
    {
        $this->fs->deletePaths($this->listScPaths($this->draftPath()));
        $this->fs->delete($this->draftMarkerPath());
    }
}
