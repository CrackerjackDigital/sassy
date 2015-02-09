<?php
/**
 * Overrides some of the scss_server functionality to play nicer with SilverStripe routing.
 * Class ScussiSCSSServer
 */
class ScussiSCSSServer extends scss_server {
	protected $request;

	/**
	 * Sets the SS request object so we can use it later to get e.g. script name.
	 * @param SS_HTTPRequest $request
	 */
	public function setRequest(SS_HTTPRequest $request) {
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
			$name = $this->join($this->dir, $input . ".scss");

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