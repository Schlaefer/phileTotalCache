<?php

namespace Phile\Plugin\Siezi\TotalCache;

use Phile\Core\Event;
use Phile\Core\Response;
use Phile\Core\ServiceLocator;
use Phile\Gateway\EventObserverInterface;
use Phile\Plugin\AbstractPlugin;

class Plugin extends AbstractPlugin implements EventObserverInterface {

	protected $pageHash;

	protected $cache;

	protected $defaults = [
		'excludeUrls' => []
	];

	public function __construct() {
		Event::registerEvent('request_uri', $this);
		Event::registerEvent('after_render_template', $this);
	}

	public function on($eventKey, $data = null) {
		$method = lcfirst(str_replace(' ', '' , ucwords(str_replace('_', ' ', $eventKey))));
		$this->{$method}($data);
	}

	public function afterRenderTemplate($data) {
		if (!$this->cache) {
			return;
		}
		$this->cache->set($this->pageHash, $data['output']);
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
		(new Response())
			->setBody($this->cache->get($this->pageHash))
			->send();
		die();
	}

}
