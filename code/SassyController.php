<?php
class SassyController extends Controller {
	private static $allowed_actions = array('css');
	private static $input_path = '';
	private static $cache_path = '';
	private static $import_paths = array();
	private static $formatter_config = '';      // dev, test or live, leave blank to use current environment instead
	private static $formatter_names = array(
		'dev' => 'scss_formatter_nested',
		'test' => 'scss_formatter_compressed',
		'live' => 'scss_formatter_compressed'
	);

	public function css(SS_HTTPRequest $request) {
		$inputPath = $request->getVar('input_path') ?: (SassyController::config()->get('input_path') ?: (SSViewer::get_theme_folder() . "/scss"));

        if ($request->param('Name') === 'fonts') {
            $url = explode('/', $request->getVar('url'));
            list($font, $path) = array_reverse($url);
            readfile(Controller::join_links(
                Director::baseFolder(),
                MosaicModule::get_module_path(),
                'fonts',
                $font
            ));
            return;
        }

		$scss = new scssc();

		$paths = array_map(function($path) {
				return BASE_PATH . "/" . $path;
			},
			array_merge(array($inputPath), SassyController::config()->get('import_paths'))
		);

		$scss->setImportPaths($paths);

		$formatters = SassyController::config()->get('formatter_names');

		$scss->setFormatter($formatters[$this->getFormatterName()]);

		$cachePath = SassyController::config()->get('cache_path') ?: BASE_PATH . "/" . Requirements::backend()->getCombinedFilesFolder() . "/scss_cache";

		$server = new SassySCSSServer(BASE_PATH . "/" . $inputPath, $cachePath, $scss);
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
	private function getFormatterName() {
		return SassyController::config()->get('formatter_config') ?: SS_ENVIRONMENT_TYPE;
	}
}