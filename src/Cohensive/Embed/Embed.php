<?php
namespace Cohensive\Embed;

class Embed
{

	// could be just a string containing url
	protected $url;

	protected $attributes;

	protected $params;

	// providers array
	protected $providers;

	// provider array after parse run
	protected $provider;

	// array of matches after parse run
	protected $matches;

	/**
	 * Create Embed instance.
	 *
	 * @param  string  $url
	 * @param  mixed  $config
	 * @return void
	 */
	public function __construct($url = null, $config = null)
	{
		if ( ! is_null($url) ) {
			$this->url = $url;
		}

		if ( ! is_null($config) ) {
			$this->attributes = (isset($config['attributes']) ? $config['attributes'] : null);
			$this->params = (isset($config['params']) ? $config['params'] : null);
		}
	}

	/**
	 * Excplicitly set url.
	 *
	 * @param  string  $url
	 * @return void
	 */
	public function setUrl($url)
	{
		$this->url = $url;
	}

	/**
	 * Excplicitly set embed params.
	 *
	 * @param  string  $key
	 * @param  mixed  $val
	 * @return void
	 */
	public function setParam($key, $val = null)
	{
		if (is_array($key) ) {
			foreach ($key as $k => $val) {
				$this->params[$k] = $val;
			}
		} else {
			$this->params[$key] = $val;
		}

		$this->updateProvider();

		return $this;
	}

	/**
	 * Excplicitly set embed attributes.
	 *
	 * @param  string  $key
	 * @param  mixed  $val
	 * @return void
	 */
	public function setAttr($key, $val = null)
	{
		if (is_array($key)) {
			foreach ($key as $k => $val) {
				$this->attributes[$k] = $val;
			}
		} else {
			$this->attributes[$key] = $val;
		}

		// If provider already set, update it's data.
		$this->updateProvider();

		return $this;
	}

	/**
	 * Parse given url.
	 *
	 * @return mixed
	 */
	public function parseUrl()
	{
		if ( ! is_null($this->url) ) {

			foreach ($this->providers as $provider) {
				if ( is_array($provider['url']) ) {
					// multiple urls
					foreach ($provider['url'] as $pattern) {
						if ( preg_match('~'.$pattern.'~imu', $this->url, $matches) ) {
							$this->matches = $matches;
							$this->provider = $provider;
							$this->parseProvider($this->provider['info'], $matches);
							$this->parseProvider($this->provider['render'], $matches);
							$this->updateProvider();
							return $this;
						}
					}

				} else {
					if ( preg_match('~'.$provider['url'].'~imu', $this->url, $matches) ) {
						$this->matches = $matches;
						$this->provider = $provider;
						$this->parseProvider($this->provider['info'], $matches);
						$this->parseProvider($this->provider['render'], $matches);
						$this->updateProvider();
						return $this;
					}
				}
			}

		} else {
			return false;
		}
	}

	/**
	 * Get remote data if available.
	 *
	 * @return Cohensive\Embed\Embed
	 */
	public function parseData()
	{
		if (isset($this->provider['dataCallback'])) {
			$this->provider['data'] = $this->provider['dataCallback']($this);
		}
		return $this;
	}


	/**
	 * Parse found provider and replace {x} parts with parsed code.
	 *
	 * @param  array  $array
	 * @param  array  $matches
	 * @return array  $array
	 */
	public function parseProvider(&$array, $matches)
	{
		// Check if we have an iframe creation array.
		foreach ($array as $key => $val) {
			if (is_array($val)) {
				$array[$key] = $this->parseProvider($val, $matches);
			} else {
				for ($i=1; $i<count($matches); $i++) {
					$array[$key] = str_replace('{'.$i.'}', $matches[$i], $array[$key]);
				}
			}
		}

		return $array;
	}

	/**
	 * Update provider if set.
	 *
	 * @return void
	 */
	public function updateProvider()
	{
		// If provider already set, update its data.
		if (! is_null($this->provider)) {
			if (isset($this->attributes['width']) && ! isset($this->attributes['height'])) {
				$this->attributes['height'] = $this->attributes['width']/$this->provider['render']['sizeRatio'];
			}

			if (! is_null($this->attributes)) {
				if (isset($this->provider['render']['iframe'])) {
					$this->provider['render']['iframe'] = array_replace($this->provider['render']['iframe'], $this->attributes);
				}
				if (isset($this->provider['render']['object']) && isset($this->provider['render']['object']['attributes'])) {
					$this->provider['render']['object']['attributes'] = array_replace($this->provider['render']['object']['attributes'], $this->attributes);
				}
				if (isset($this->provider['render']['object']) && isset($this->provider['render']['object']['embed'])) {
					$this->provider['render']['object']['embed'] = array_replace($this->provider['render']['object']['embed'], $this->attributes);
				}
			}

			if (! is_null($this->params)) {
				if (isset($this->provider['render']['object']) && isset($this->provider['render']['object']['params'])) {
					$this->provider['render']['object']['params'] = array_replace($this->provider['render']['object']['params'], $this->params);
				}
			}
		}
	}

	/**
	 * Generate script for embed if required and available.
	 *
	 * @return string
	 */
	public function forgeScript()
	{
		// check if we have an iframe creation array
		if (! isset($this->provider) || ! isset($this->provider['render']['script'])) {
			return null;
		}

		// Start script tag.
		$script = '<script';

		foreach ($this->provider['render']['script'] as $attribute => $val) {
			$script .= sprintf(' %s="%s"', $attribute, $val);
		}

		// Close script tag.
		$script .='></script>';

		return $script;
	}

	/**
	 * Generate iframe for embed if required and available.
	 *
	 * @return string
	 */
	public function forgeIframe()
	{
		// Check if we have an iframe creation array.
		if (! isset($this->provider) || ! isset($this->provider['render']['iframe'])) {
			return false;
		}

		// Start iframe tag.
		$iframe = '<iframe';

		foreach ($this->provider['render']['iframe'] as $attribute => $val) {
			$iframe .= sprintf(' %s="%s"', $attribute, $val);
		}

		// Close iframe tag.
		$iframe .='></iframe>';

		$iframe .= $this->forgeScript();

		return $iframe;
	}

	/**
	 * Generate object for embed if required and available.
	 *
	 * @return string
	 */
	public function forgeObject()
	{
		// Check if we have an object creation array.
		if (! isset($this->provider) || ! isset($this->provider['render']['object'])) {
			return false;
		}

		// Start object tag.
		$object = '<object';

		foreach ($this->provider['render']['object']['attributes'] as $attribute => $val) {
			$object .= sprintf(' %s="%s"', $attribute, $val);
		}
		$object .= '>';

		// Create params.
		if ( isset($this->provider['render']['object']['params']) ) {
			foreach ($this->provider['render']['object']['params'] as $param => $val) {
				$object .= sprintf('<param name="%s" value="%s"></param>', $param, $val);
			}
		}

		// Create embed.
		if ( isset($this->provider['render']['object']['embed']) ) {
			$object .= '<embed';
			// embed can have same attributes as object itself (height, width etc)
			foreach ($this->provider['render']['object']['embed'] as $ettribute => $val) {
				$val = ( is_bool($val) && $val ? 'true' : 'false' );
				$object .= sprintf(' %s="%s"', $attribute, $val);
			}
			$object .= '></embed>';
		}

		// Close object tag.
		$object .= '</object>';

		$object .= $this->forgeScript();

		return $object;
	}

	/**
	 * Generate html code for embed.
	 *
	 * @return string
	 */
	public function getHtmlCode()
	{
		if ($html = $this->forgeIframe()) return $html;
		if ($html = $this->forgeObject()) return $html;
	}

	/**
	 * Alias for iframe forge method.
	 *
	 * @return string
	 */
	public function getIframeCode()
	{
		return $this->forgeIframe();
	}

	/**
	 * Alias for object forge method.
	 *
	 * @return string
	 */
	public function getObjectCode()
	{
		return $this->forgeObject();
	}

	/**
	 * Set up new list of providers.
	 *
	 * @param  array  $aproviders
	 * @return void
	 */
	public function setProviders(array $providers)
	{
		$this->providers = $providers;
	}

	/**
	 * Get up list of providers.
	 *
	 * @return array
	 */
	public function getProvider()
	{
		return json_decode(json_encode($this->provider));
	}

}
