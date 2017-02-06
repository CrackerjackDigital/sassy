<?php
namespace Sassy;

use Exception;
use SilverStripe\Assets\Filesystem;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\View\Requirements;
use SilverStripe\View\SSViewer;

class FrontController extends Controller {
	private static $allowed_actions = [
		'css',
		'fonts',
	];

	// if paths are prefixed by '/' they are treated as relative to site root, otherwise the current theme dir (e.g. themes/name/scss for scss_path)
	private static $scss_path = 'scss';
	private static $font_path = 'fonts';

	// set to folder where css should be compiled to, e.g. '/assets/css'. If not set the combined files folder will be used.
	private static $css_path = '';

	// paths to check for scss files being imported if not fully specified in scss
	private static $import_paths = array();

	// output format dev, test or live, leave blank to use current environment instead
	private static $formatter_config = '';

	// output format for SS_ENVIRONMENT_TYPE see leafo docs for more info.
	private static $formatter_names = array(
		'dev'  => 'scss_formatter_nested',
		'test' => 'scss_formatter_compressed',
		'live' => 'scss_formatter_compressed',
	);

	// safe paths to pass files through from, e.g. fonts
	private static $passthru_file_paths = [
		'fonts',
	];

	/**
	 * Read requests to fonts path directly (this is also handled in css handler for passthru_file_paths). See font_path() below for where it looks.
	 *
	 * @param HTTPRequest $request
	 */
	public function fonts(HTTPRequest $request) {
		// handle paths registered in passthru_file_paths as straight through files not scss.
		if (in_array($request->param('Name'), static::config()->get('passthru_file_paths'))) {
			$url = explode('/', $request->getVar('url'));

			list($font,) = array_reverse($url);

			readfile(Controller::join_links(
				Director::baseFolder(),
				static::font_path(),
				'fonts',
				$font
			));
			return;
		}
	}

	/**
	 * matches sassy/css/<name> and returns compiled css for that scss file if found. See scss_path() below for where it looks.
	 *
	 * @param \SilverStripe\Control\HTTPRequest $request
	 * @return string
	 */
	public function css(HTTPRequest $request) {
		$scssPath = static::scss_path();

		// handle paths registered in passthru_file_paths as straight through files not scss.
		if (in_array($request->param('Name'), static::config()->get('passthru_file_paths'))) {
			$url = explode('/', $request->getVar('url'));

			list($fontFileName, $path) = array_reverse($url);

			readfile(Controller::join_links(
				static::font_path(),
				$fontFileName
			));
			return '';
		}

		$compiler = new \scssc();

		$paths = array_map(
			function ($path) {
				return BASE_PATH . "/" . $path;
			},
			array_merge(array($scssPath), static::config()->get('import_paths'))
		);

		$compiler->setImportPaths($paths);

		$formatters = static::config()->get('formatter_names');

		$compiler->setFormatter($formatters[ $this->getFormatterName() ]);

		$outputPath = static::css_path();

		$server = new Server($scssPath, $outputPath, $compiler);
		$server->setRequest($request);

		ob_start();
		$server->serve();
		$response = ob_get_clean();

		if (false === strpos($response, "Parse error:")) {
			$this->getResponse()->addHeader("Content-Type", "text/css");
		} else {
			$this->getResponse()->setStatusCode(400);
			$this->getResponse()->addHeader("Content-Type", "text/plain");
		}
		return $response;
	}

	/**
	 * Return the Scss compiler formatter to use for the current silverstripe environment type (dev, test, live).
	 *
	 * @return string
	 */
	private function getFormatterName() {
		return FrontController::config()->get('formatter_config') ?: getenv('SS_ENVIRONMENT_TYPE');
	}

	/**
	 * Return path (in filesystem) to scss files.
	 *
	 * @return string
	 */
	private static function scss_path() {
		return static::build_path(static::config()->get('scss_path'));
	}

	/**
	 * Return path (in filesystem) to css files (where they should be compiled to).
	 *
	 * @return string
	 */
	private static function css_path() {
		$path = static::config()->get('css_path') ?: Controller::join_links('/', ASSETS_DIR, Requirements::backend()->getCombinedFilesFolder() . "/sassy");
		return static::build_path($path, true);
	}

	/**
	 * Return path (in filesystem) to font files.
	 *
	 * @return string
	 */
	private static function font_path() {
		return static::build_path(static::config()->get('font_path'));
	}

	/**
	 * Build a path to an existing (real) directory on the server relative to BASE_PATH or the current theme path. The path must exist as it needs
	 * to be checked to make sure it is inside the web root.
	 *
	 * @param string $path   relative to site root if starts with '/' otherwise relative to current them folder
	 * @param bool   $create the path if it doesn't exist (e.g. for compiled css output)
	 * @return string
	 * @throws \Exception
	 */
	protected static function build_path($path, $create = false) {
		if ($path) {
			if (substr($path, 0, 1) == '/') {
				// relative to base
				$path = Controller::join_links(BASE_PATH, $path);
			} else {
				// relative to theme
				$path = Controller::join_links(THEMES_PATH, SSViewer::config()->get('theme'), $path);
			}
			if (!realpath($path) && !$create) {
				throw new Exception("'$path' doesn't exist");
			}
			if (substr($path, 0, strlen(BASE_PATH)) != BASE_PATH) {
				throw new Exception("'$path' is not inside the web root so won't continue");
			}
			if ($create && !is_dir($path)) {
				// create the css file to write compiled scss to for direct reference e.g. on live servers
				Filesystem::makeFolder($path);
			}
			$path = realpath($path);
		}
		return $path;
	}
}