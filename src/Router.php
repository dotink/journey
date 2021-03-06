<?php

namespace Journey;

use RuntimeException;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use FastRoute;

/*
 *
 */
class Router
{
	/**
	 *
	 */
	protected $masks = array();


	/**
	 *
	 */
	protected $resolver = NULL;


	/**
	 *
	 */
	protected $response = NULL;


	/**
	 *
	 */
	protected $request = NULL;


	/**
	 *
	 */
	public function __construct(Resolver $resolver)
	{
		$this->resolver = $resolver;
	}


	/**
	*
	*/
	public function addMask($from, $to)
	{
		$this->masks[$from] = $to;

		return $this;
	}


	/**
	 *
	 */
	public function addTransformer($type, Transformer $transformer)
	{
		if (isset($this->transformers[$type])) {
			throw new RuntimeException(
				'Transformer %s is already registered.  Cannot register %s for type "%s"',
				get_class($this->transformers[$type]),
				get_class($transformer),
				$type
			);
		}

		$this->transformers[$type] = $transformer;

		return $this;
	}


	/**
	 *
	 */
	public function getRequest()
	{
		return $this->request;
	}


	/**
	 *
	 */
	public function getResponse()
	{
		return $this->response;
	}


	/**
	 *
	 */
	public function link($route, $params = array(), $include_domain = FALSE)
	{
		$target  = $route;
		$query   = array();
		$mapping = array();
		$domain  = NULL;

		foreach ($this->masks as $from => $to) {
			$target = str_replace($from, $to, $target);
		}

		if (preg_match_all('/{([^:}]+)(?::([^}]+))?}/', $route, $matches)) {
			$mapping = array_combine($matches[1], $matches[2]);
		}

		if ($params instanceof ParamProvider) {
			$provider = $params;
			$params   = array();

			foreach ($mapping as $name => $type) {
				$params[$name] = $provider->getRouteParameter($name);
			}
		}

		foreach ($params as $name => $value) {
			if (!isset($mapping[$name])) {
				$query[$name] = $value;
				continue;
			}

			$type = $mapping[$name];

			if (isset($this->transformers[$type])) {
				$value = $this->transformers[$type]->toUrl($name, $value, $params);
			}

			if ($type) {
				$target = str_replace('{' . $name . ':' . $type . '}', urlencode($value), $target);
			} else {
				$target = str_replace('{' . $name . '}', urlencode($value), $target);
			}
		}

		if ($include_domain) {
			$uri    = $this->request->getURI();
			$domain = sprintf('%s://%s', $uri->getScheme(), $uri->getAuthority());
		}

		return $domain . $target . (count($query) ? '?' . http_build_query($query) : NULL);
	}


	/**
	 *
	 */
	public function run(Request $request, Response $response, FastRoute\Dispatcher $dispatcher)
	{
		$this->request  = $request;
		$this->response = $response;
		$request_method = $request->getMethod();
		$request_path   = $request->getURI()->getPath();

		foreach ($this->masks as $from  => $to) {
			$request_path = str_replace($to, $from, $request_path);
		}

		//
		// If any masks are in play and the URL was translated, redirect to the canonical URL.

		if ($this->link($request_path) != $request->getURI()->getPath()) {
			return $response->withStatus(301)->withHeader('Location', $this->link($request_path));
		}

		$result = $dispatcher->dispatch($request_method, $request_path);

		switch ($result[0]) {
			case $dispatcher::NOT_FOUND:
				return $response->withStatus(404);

			case $dispatcher::METHOD_NOT_ALLOWED:
				return $response->withHeader('Allowed', join(',', $result[1]))->withStatus(405);

			case $dispatcher::FOUND:
				$handler = $result[1];
				$params  = $result[2];

				if (is_array($handler) && isset($handler['target'])) {
					$target = $handler['target'];

					if (isset($handler['mapping'])) {
						foreach ($params as $name => $value) {

							if (!isset($handler['mapping'][$name])) {
								$this->request = $this->request->withAttribute($name, $value);

							} else {
								$type = $handler['mapping'][$name];

								if (isset($this->transformers[$type])) {
									$value = $this->transformers[$type]->fromUrl($name, $value, $result[2]);
								}
							}

							$this->request = $this->request->withAttribute($name, $value);
						}
					}

				} else {
					$target = $handler;
				}

				$this->response = $this->resolver->execute($this, $target);
		}

		return $this->response;
	}
}
