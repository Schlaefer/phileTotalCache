<?php

namespace Phile\Plugin\Siezi\PhileTotalCache;

use Phile\Core\ServiceLocator;

class PageCache {

	protected $cache;

	protected $url;

	public function __construct($url) {
		$this->url = $url;
		if (ServiceLocator::hasService('Phile_Cache')) {
			$this->cache = ServiceLocator::getService('Phile_Cache');
		}
	}

	public function get() {
		if (!$this->cache) {
			return;
		}
		$pageHash = $this->getPageHash($this->url);
		if (!$this->cache->has($pageHash)) {
			return;
		}
		return $this->cache->get($pageHash);
	}

	public function set($body, array $options = []) {
		if (!$this->cache) {
			return;
		}
		$hash = $this->getPageHash($this->url);
		$page = ['body' => $body] + $options;
		$this->cache->set($hash, $page);
	}

	protected function getPageHash($url) {
		return 'siezi\phileTotalCache.' . md5($url);
	}

}
