<?php
use Leafo\ScssPhp\Compiler;
use Sassy\Server;

class FrontController extends Controller {
	private static $allowed_actions = [
		'css',
		'font',
	];

	// add paths to here if you want to serve files from outside of web root
	private static $safe_paths = [
		BASE_PATH
	];

	// if paths are prefixed by '/' they are treated as relative to site root, otherwise the current theme dir (e.g. themes/name/scss for scss_path)
	private static $scss_path = 'scss';

	private static $font_paths = [
		'fonts'
	];

	// set to folder where css should be compiled to, e.g. '/assets/css'. If not set the combined files folder will be used.
	private static $css_path = '';

	// paths to check for scss files being imported if not fully specified in scss
	private static $import_paths = array();

	// force output format dev, test or live, leave blank to use current environment
	private static $formatter = '';

	// map of SS environment to Formatter class
	private static $formatters = [
		# 'dev' => 'Leafo\ScssPhp\Formatter\Nested'
		# 'test' => 'Leafo\ScssPhp\Formatter\Compact',
		# 'live' => 'Leafo\ScssPhp\Formatter\Compressed'
	];

	/**
	 * Try and read font from all configured font_paths, meaning font can be e.g. in 'vendor' and still be served even though blocked by .htaccess.
	 *
	 * @param HTTPRequest $request
	 */
	public function font(HTTPRequest $request) {
		if ($fileName = $request->param('Name')) {
			// param will not have an extension so get from url instead
			$fileName = current(array_reverse(explode('/', $request->getURL())));

			foreach (static::config()->get('font_paths') ?: [] as $path) {
				$filePathName = Controller::join_links(static::build_path($path), $fileName);
				if (file_exists($filePathName)) {
					readfile($filePathName);
					break;
				}
			}
		}
	}

	/**
	 * matches sassy/css/<name> and returns compiled css for that scss file if found. See scss_path() below for where it looks.
	 *
	 * @param SS_HTTPRequest $request
	 * @return string
	 */
	public function css(HTTPRequest $request) {
		$compiler = new Compiler();

		$scssPath = static::scss_path();
		$importPaths = static::config()->get('import_paths') ?: [];

		$paths = array_merge(
			array_map(
				function ($path) {
					return static::build_path($path);
				},
				$importPaths
			),
			[ $scssPath ]
		);

		$compiler->setImportPaths($paths);

		$compiler->setFormatter($this->getFormatter());

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
	private function getFormatter() {
		$formatters = static::config()->get('formatters');
		return $formatters[ static::config()->get('formatter') ?: getenv('SS_ENVIRONMENT_TYPE') ];
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
			$safe = false;
			foreach (static::config()->get('safe_paths') as $safePath) {
				if ($safe = (substr($path, 0, strlen($safePath)) == $safePath)) {
					break;
				}
			}
			if (!$safe) {
				throw new Exception("'$path' is not inside list of safe paths so won't continue");
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