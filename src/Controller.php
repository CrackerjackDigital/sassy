<?php
use Leafo\ScssPhp\Compiler;
use SS_HTTPRequest as HTTPRequest;

class SassyController extends \Controller {
	const InjectorServiceName = 'CSSService';

	private static $allowed_actions = [
		'css',
		'font',
	];

	/**
	 * Try and read font from all configured font_paths, meaning font can be e.g. in 'vendor' and still be served even though blocked by .htaccess.
	 *
	 * @param HTTPRequest $request
	 *
	 * @throws \Exception
	 */
	public function font(HTTPRequest $request) {
		if ($fileName = $request->param('Name')) {
			// param will not have an extension so get from url instead
			$fileName = current(array_reverse(explode(DIRECTORY_SEPARATOR, $request->getURL())));

			foreach (static::config()->get('font_paths') ?: [] as $path) {
				$filePathName = Controller::join_links(SassyServer::check_path($path), $fileName);
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
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function css(HTTPRequest $request) {
		$response = Injector::inst()->get( static::InjectorServiceName )->generate( $request );

		if ( false === strpos( $response, "Parse error:" ) ) {
			// worked ok
			$this->getResponse()->addHeader( "Content-Type", "text/css" );
		} else {
			// fail
			$this->getResponse()->setStatusCode( 400 );
			$this->getResponse()->addHeader( "Content-Type", "text/plain" );
		}

		return $response;

	}

}