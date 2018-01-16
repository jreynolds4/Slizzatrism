<?php

namespace Sitecake\Services\Content;

use Sitecake\Exception\Http\BadRequestException;
use Sitecake\Services\Service;
use Sitecake\Site;

class ContentService extends Service
{
    /**
     * @var Site
     */
    protected $site;

    /**
     * @var ContentManager
     */
    protected $content;

    public function __construct($ctx)
    {
        $this->site = $ctx['site'];
        $this->content = new ContentManager($this->site);
    }

    public function save($request)
    {
        $id = $request->request->get('scpageid');
        if (is_null($id)) {
            throw new BadRequestException('Page ID is missing');
        }

        $request->request->remove('scpageid');

        $this->content->save($request->request->all());

        return $this->json($request, ['status' => 0]);
    }

    public function publish($request)
    {
        $this->site->publishDraft();

        return $this->json($request, ['status' => 0]);
    }
}
