<?php
class SassyController extends Controller {

	private static $allowed_actions = [
		'css'   => true,
		'fonts' => true,
	];
	private static $url_handlers = [
		'css//$Name!'   => 'css',
		'fonts//$Name!' => 'fonts',
	];

	private static $scss_path = '';
	private static $cache_path = '';
	private static $import_paths = array();
	private static $formatter_config = '';      // dev, test or live, leave blank to use current environment instead
	private static $formatter_names = array(
		'dev'  => 'scss_formatter_nested',
		'test' => 'scss_formatter_compressed',
		'live' => 'scss_formatter_compressed',
	);

	private static $enabled = true;

	private static $read_file_paths = [
		'fonts',
	];

	/**
	 * Read requests to fonts path directly (this is also handled in css handler for read_file_paths).
	 *
	 * @param \SS_HTTPRequest $request
	 */
	public function fonts(SS_HTTPRequest $request) {
		$modulePath = Injector::inst()->get('Application')->config()->get('module_path');

		// handle paths registered in read_file_paths as straight through files not scss.
		if (in_array($request->param('Name'), static::config()->get('read_file_paths'))) {
			$url = explode('/', $request->getVar('url'));

			list($font, $path) = array_reverse($url);

			readfile(Controller::join_links(
				Director::baseFolder(),
				$modulePath,
				'fonts',
				$font
			));
			return;
		}
	}

	public function css(SS_HTTPRequest $request) {
		$inputPath = SassyController::config()->get('scss_path') ?: (SSViewer::get_theme_folder() . "/scss");

		// handle paths registered in read_file_paths as straight through files not scss.
		if (in_array($request->param('Name'), static::config()->get('read_file_paths'))) {
			$url = explode('/', $request->getVar('url'));

			list($font, $path) = array_reverse($url);

			readfile(Controller::join_links(
				Director::baseFolder(),
				Application::module_path(),
				'fonts',
				$font
			));
			return;
		}

		$scss = new scssc();

		$paths = array_map(function ($path) {
			return BASE_PATH . "/" . $path;
		},
			array_merge(array($inputPath), SassyController::config()->get('import_paths'))
		);

		$scss->setImportPaths($paths);

		$formatters = SassyController::config()->get('formatter_names');

		$scss->setFormatter($formatters[ $this->getFormatterName() ]);

		if (!$cachePath = SassyController::config()->get('cache_path')) {
			$cachePath = BASE_PATH . Requirements::backend()->getCombinedFilesFolder() . "/sassy/" . \Config::inst()->get('SSViewer', 'theme');
		} else {
			$cachePath = BASE_PATH . DIRECTORY_SEPARATOR . $cachePath;
		}
		// if path doesn't exist and we're in the assets folder then make the path
		if (!strncmp($cachePath, ASSETS_PATH, strlen(ASSETS_PATH))) {
			if (!is_dir($cachePath)) {
				Filesystem::makeFolder($cachePath);
			}
		}

		$server = new SassySCSSServer(BASE_PATH . "/" . $inputPath, $cachePath, $scss);
		$server->setRequest($request);

		ob_start();
		$server->serve();
		$css = ob_get_clean();

		if (false === strpos($css, "Parse error:")) {
			$this->getResponse()->addHeader("Content-Type", "text/css");
		} else {
			$this->getResponse()->setStatusCode(400);
			$this->getResponse()->addHeader("Content-Type", "text/plain");
		}
		return $css;
	}

	/**
	 * Return the Scss compiler formatter to use for the current silverstripe environment type (dev, test, live).
	 *
	 * @return string
	 */
	private function getFormatterName() {
		return SassyController::config()->get('formatter_config') ?: SS_ENVIRONMENT_TYPE;
	}
}