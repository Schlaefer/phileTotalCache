<?php

namespace Phile\Plugin\Siezi\PhileTotalCache;

use Phile\Core\Event;
use Phile\Core\Response;
use Phile\Plugin\AbstractPlugin;

class Plugin extends AbstractPlugin
{

    /**
     * @var PageCache
     */
    protected $pageCache;

    protected $enabled = false;

    protected $events = [
        'after_resolve_page' => 'onAfterResolvePage',
        'before_parse_content' => 'onBeforeParseContent',
        'before_render_template' => 'onBeforeRenderTemplate',
        'siezi\phileTotalCache.command.setPage' => 'onSetPage'
    ];

    protected function onSetPage($data)
    {
        $data += ['options' => []];
        (new PageCache($data['url']))->set($data['body'], $data['options']);
    }

    protected function onAfterResolvePage($data)
    {
        $pageId = $data['pageId'];
        if ($this->isPageExcluded($pageId)) {
            return;
        }
        $this->enabled = true;
        $cacheId = $this->getCacheId($pageId);
        $this->pageCache = new PageCache($cacheId);
        $page = $this->pageCache->get();
        if (!$page) {
            return;
        }
        $this->sendPage($page);
    }

    protected function onBeforeParseContent($data)
    {
        // don't fill cache storage with bogus requests
        if ($this->enabled && $data['page']->getUrl() === '404') {
            $this->enabled = false;
        }
    }

    protected function onBeforeRenderTemplate()
    {
        /**
         * Register this event as late as possible. So it hopefully runs after
         * all normal plugins have modified the output to its final state.
         */
        $event = 'after_render_template';
        $this->events[$event] = 'onAfterRenderTemplate';
        Event::registerEvent($event, $this);
    }

    protected function onAfterRenderTemplate($data)
    {
        if ($this->enabled) {
            $this->pageCache->set($data['output']);
        }
    }

    protected function getCacheId($pageId)
    {
        if (empty($this->settings['cacheRequestParams'])) {
            $cacheId = $pageId;
        } else {
            $cacheId = $_SERVER['REQUEST_URI'];
        }

        return $cacheId;
    }

    protected function isPageExcluded($current)
    {
        foreach ($this->settings['excludePages'] as $page) {
            if ($page === $current) {
                return true;
            }
            if (substr($page, -1) === '*') {
                $page = rtrim(rtrim($page, '*'), '/');
                if (strpos($current, $page) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function sendPage($page)
    {
        $response = new Response();
        if (isset($page['status'])) {
            $response->setStatusCode($page['status']);
        }
        if (isset($page['headers'])) {
            foreach ($page['headers'] as $key => $value) {
                $response->setHeader($key, $value);
            }
        }
        $response->setBody($page['body'])->send();
        die();
    }

}
