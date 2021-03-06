<?php

namespace Journey;

use RuntimeException;
use FastRoute;

/**
 *
 */
class Collector extends FastRoute\RouteCollector
{
	/**
	 *
	 */
	static protected $methods = [
		'GET',
		'PUT',
		'PATCH',
		'POST',
		'DELETE',
		'HEAD',
	];


	/**
	 *
	 */
	public function addPattern($type, $pattern)
	{
		if (preg_match('#' . $pattern . '#', '') === FALSE) {
			throw new RuntimeException(sprintf(
				'Invalid pattern %s supplied for type %s',
				$pattern,
				$type
			));
		}

		$this->patterns[$type] = $pattern;
	}


	/**
	 *
	 */
	public function addRoute($methods, $route, $target)
	{
		$params  = array();
		$pattern = $route;

		if (preg_match_all('/{([^:]+):([^}]+)}/', $route, $matches)) {
			$params = array_combine($matches[1], $matches[2]);

			foreach ($matches[0] as $i => $token) {
				$name = $matches[1][$i];
				$type = $matches[2][$i];

				if (!isset($this->patterns[$type])) {
					continue;
				}

				$pattern = str_replace($token, '{' . $name . ':' . $this->patterns[$type] . '}', $pattern);
			}
		}

		parent::addRoute($methods, $pattern, [
			'target'  => $target,
			'mapping' => $params
		]);
	}


	/**
	 *
	 */
	public function any($route, $target)
	{
		$this->addRoute(static::$methods, $route, $target);
	}
}
