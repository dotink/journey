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
	protected $resolver = NULL;
	protected $response = NULL;
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
		$link    = $route;
		$query   = array();
		$mapping = array();
		$domain  = NULL;

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
				$link = str_replace('{' . $name . ':' . $type . '}', urlencode($value), $link);
			} else {
				$link = str_replace('{' . $name . '}', urlencode($value), $link);
			}
		}

		if ($include_domain) {
			$uri    = $this->request->getURI();
			$domain = sprintf('%s://%s', $uri->getScheme(), $uri->getAuthority());
		}

		return $domain . $link . (count($query) ? '?' . http_build_query($query) : NULL);
	}


	/**
	 *
	 */
	public function run(Request $request, Response $response, FastRoute\Dispatcher $dispatcher)
	{
		$this->request  = $request;
		$this->response = $response;
		$result         = $dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());

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
