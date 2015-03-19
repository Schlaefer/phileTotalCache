<?php

namespace Phile\Plugin\Siezi\PhileTotalCache;

use Phile\Core\ServiceLocator;

class PageCache {

	protected $cache;

	protected $cacheId;

	public function __construct($cacheId) {
		if (ServiceLocator::hasService('Phile_Cache')) {
			$this->cache = ServiceLocator::getService('Phile_Cache');
		}
		$this->cacheId = 'siezi\phileTotalCache.' . md5($cacheId);
	}

	public function get() {
		if (!$this->cache || !$this->cache->has($this->cacheId)) {
			return;
		}
		return $this->cache->get($this->cacheId);
	}

	public function set($body, array $options = []) {
		if (!$this->cache) {
			return;
		}
		$page = ['body' => $body] + $options;
		$this->cache->set($this->cacheId, $page);
	}

}
