<?php

namespace Phile\Plugin\Siezi\PhileTotalCache;

use Phile\Core\Event;
use Phile\Core\Response;
use Phile\Gateway\EventObserverInterface;
use Phile\Plugin\AbstractPlugin;

class Plugin extends AbstractPlugin implements EventObserverInterface {

	/**
	 * @var PageCache
	 */
	protected $pageCache;

	protected $enabled = false;

	protected $defaults = ['excludeUrls' => []];

	protected $registeredEvents = [
		'config_loaded' => 'onConfigLoaded',
		'request_uri' => 'onRequestUri',
		'before_parse_content' => 'onBeforeParseContent',
		'after_render_template' => 'onAfterRenderTemplate',
		'siezi\phileTotalCache.command.setPage' => 'onSetPage'
	];

	public function __construct() {
		foreach ($this->registeredEvents as $event => $method) {
			Event::registerEvent($event, $this);
		}
	}

	public function on($eventKey, $data = null) {
		$method = $this->registeredEvents[$eventKey];
		$this->{$method}($data);
	}

	protected function onConfigLoaded() {
		$this->settings += $this->defaults;
	}

	protected function onSetPage($data) {
		$data += ['options' => []];
		(new PageCache($data['url']))->set($data['body'], $data['options']);
	}

	protected function onRequestUri($data) {
		$url = $data['uri'];
		if ($this->isUrlExcluded($url)) {
			return;
		}
		$this->enabled = true;
		$this->pageCache = new PageCache($url);
		$page = $this->pageCache->get();
		if (!$page) {
			return;
		}
		$this->sendPage($page);
	}

	protected function onBeforeParseContent($data) {
		// don't fill cache storage with bogus requests
		if ($this->enabled && $data['page']->getUrl() === '404') {
			$this->enabled = false;
		}
	}

	protected function onAfterRenderTemplate($data) {
		$this->pageCache->set($data['output']);
	}

	protected function isUrlExcluded($current) {
		foreach ($this->settings['excludeUrls'] as $url) {
			if ($url === $current) {
				return true;
			}
			if (substr($url, -1) === '*') {
				$url = rtrim(rtrim($url, '*'), '/');
				if (strpos($current, $url) === 0) {
					return true;
				}
			}
		}
		return false;
	}

	protected function sendPage($page) {
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
