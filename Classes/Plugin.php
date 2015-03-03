<?php

namespace Phile\Plugin\Siezi\PhileTotalCache;

use Phile\Core\Event;
use Phile\Core\Response;
use Phile\Core\ServiceLocator;
use Phile\Gateway\EventObserverInterface;
use Phile\Plugin\AbstractPlugin;

class Plugin extends AbstractPlugin implements EventObserverInterface {

	protected $cache;

	protected $pageHash;

	protected $page = ['body' => null, 'status' => 200];

	protected $defaults = [
		'excludeUrls' => []
	];

	public function __construct() {
		Event::registerEvent('request_uri', $this);
		Event::registerEvent('before_parse_content', $this);
		Event::registerEvent('after_render_template', $this);
	}

	public function on($eventKey, $data = null) {
		$method = lcfirst(str_replace(' ', '' , ucwords(str_replace('_', ' ', $eventKey))));
		$this->{$method}($data);
	}

	public function requestUri($data) {
		if (!ServiceLocator::hasService('Phile_Cache')) {
			return;
		}

		$current = $data['uri'];
		$this->settings += $this->defaults;

		foreach ($this->settings['excludeUrls'] as $url) {
			if ($url === $current) {
				return;
			}
			if (substr($url, -1) === '*') {
				$url = rtrim(rtrim($url, '*'), '/');
				if (strpos($current, $url) === 0) {
					return;
				}
			}
		}

		$this->cache = ServiceLocator::getService('Phile_Cache');
		$this->pageHash = md5('Siezi\TotalCache' . $data['uri']);
		if (!$this->cache->has($this->pageHash)) {
			return;
		}
		$page = $this->cache->get($this->pageHash);

		(new Response())
			->setStatusCode($page['status'])
			->setBody($page['body'])
			->send();
		die();
	}

	public function beforeParseContent($data) {
		if (!$this->cache) {
			return;
		}
		if ($data['page']->getUrl() === '404') {
			$this->page['status'] = 404;
		}
	}

	public function afterRenderTemplate($data) {
		if (!$this->cache) {
			return;
		}
		$this->page['body'] = $data['output'];
		$this->cache->set($this->pageHash, $this->page);
	}

}
