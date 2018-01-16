<?php

namespace Sitecake\Services\Pages;

use Silex\Application;
use Sitecake\Services\Service;

class PagesService extends Service
{
    /**
     * @var Application
     */
    protected $ctx;

    protected $pages;

    public function __construct($ctx)
    {
        $this->ctx = $ctx;
        $this->pages = new PageManager($this->ctx['site'], $this->ctx);
    }

    public function pages($request)
    {
        $pageUpdates = $request->request->get('pages');
        $menuUpdates = $request->request->get('menus');
        if (!is_null($pageUpdates) || !is_null($menuUpdates)) {
            $this->pages->update(
                json_decode($pageUpdates, true),
                json_decode($menuUpdates, true)
            );
        }

        return $this->json($request, [
            'status' => 0,
            'pages' => $this->pages->listPages(),
            'menus' => $this->pages->listMenus()
        ], 200);
    }
}
