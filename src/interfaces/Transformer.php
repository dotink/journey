<?php

namespace Journey;

interface Transformer
{
	/**
	 *
	 */
	public function fromUrl($name, $value, array $context = array());


	/**
	 *
	 */
	public function toUrl($name, $value, array $context = array());
}
