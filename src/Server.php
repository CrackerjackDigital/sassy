<?php
namespace Sassy;

use SilverStripe\Control\HTTPRequest;

/**
 * Overrides some of the scss_server functionality to play nicer with SilverStripe routing.
 * Class Server
 */
class Server extends \Leafo\ScssPhp\Server {
	protected $request;

	// dir is private in parent so save a copy here to reference in findInput
	protected $path;

	public function __construct($dir, $cacheDir = null, $scss = null) {
		parent::__construct($dir, $cacheDir, $scss);
		$this->path = $dir;
	}

	/**
	 * Sets the SS request object so we can use it later to get e.g. script name.
	 * @param HTTPRequest $request
	 */
	public function setRequest(HTTPRequest $request) {
		$this->request = $request;
	}

	/**
	 * Override to remove requirement that name ends with '.scss' as params passed from SS do not have extensions.
	 *
	 * @return bool|string
	 */
	protected function findInput() {
		if (($input = $this->inputName())
			&& strpos($input, '..') === false
		) {
			$name = $this->join($this->path, $input . ".scss");

			if (is_file($name) && is_readable($name)) {
				return $name;
			}
		}

		return false;
	}

	/**
	 * Override the parent to get the name of the scss file to include from the request object Name param.
	 *
	 * @return null|string
	 */
	protected function inputName() {
		return $this->request->param('Name');
	}

}