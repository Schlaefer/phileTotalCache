<?php

namespace Phile\Plugin\Siezi\PhileTotalCache;

use Phile\Core\Event;
use Phile\Core\Response;
use Phile\Core\ServiceLocator;
use Phile\Gateway\EventObserverInterface;
use Phile\Plugin\AbstractPlugin;

class Plugin extends AbstractPlugin implements EventObserverInterface {

	protected $cache;

	protected $enabled = true;

	protected $pageOptions = [];

	protected $url;

	protected $defaults = [
		'excludeUrls' => []
	];

	public function __construct() {
		Event::registerEvent('request_uri', $this);
		Event::registerEvent('before_parse_content', $this);
		Event::registerEvent('after_render_template', $this);
		Event::registerEvent('siezi.phileTotalCache.set', $this);
	}

	public function on($eventKey, $data = null) {
		if ($eventKey === 'siezi.phileTotalCache.set') {
			$this->onSet($data);
			return;
		}
		$method = lcfirst(str_replace(' ', '' , ucwords(str_replace('_', ' ', $eventKey))));
		$this->{$method}($data);
	}

	protected function onSet($data) {
		$url = $data['url'];
		$body = $data['body'];
		unset($data['url'], $data['body']);
		$this->setPage($url, $body, $data);
	}

	protected function requestUri($data) {
		if (!ServiceLocator::hasService('Phile_Cache')) {
			$this->enabled = false;
			return;
		}

		$this->url = $current = $data['uri'];
		$this->settings += $this->defaults;


		foreach ($this->settings['excludeUrls'] as $url) {
			if ($url === $current) {
				$this->enabled = false;
			}
			if (substr($url, -1) === '*') {
				$url = rtrim(rtrim($url, '*'), '/');
				if (strpos($current, $url) === 0) {
					$this->enabled = false;
				}
			}
		}

		if (!$this->enabled) {
			return;
		}

		$this->cache = ServiceLocator::getService('Phile_Cache');

		$pageHash = $this->getPageHash($data['uri']);
		if (!$this->cache->has($pageHash)) {
			return;
		}
		$page = $this->cache->get($pageHash);

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

	protected function beforeParseContent($data) {
		if (!$this->enabled) {
			return;
		}
		if ($data['page']->getUrl() === '404') {
			$this->pageOptions['status'] = 404;
		}
	}

	protected function afterRenderTemplate($data) {
		$this->setPage($this->url, $data['output'], $this->pageOptions);
	}

	protected function getPageHash($url) {
		return 'siezi.totalCache.' . md5($url);
	}

	protected function setPage($url, $body, array $options = []) {
		if (!$this->enabled) {
			return;
		}
		$hash = $this->getPageHash($url);
		$page = ['body' => $body] + $options;
		$this->cache->set($hash, $page);
	}

}
