<?php
use Leafo\ScssPhp\Compiler;
use Modular\Exceptions\Module as Exception;
use Modular\Traits\config;
use Modular\Traits\environment;

/**
 * Overrides some of the scss_server functionality to play nicer with SilverStripe routing.
 * Class Server
 */
class SassyServer extends \Leafo\ScssPhp\Server {
	use config;
	use environment;

	const CurrentEnvironment = SS_ENVIRONMENT_TYPE;
	const DefaultFormatter   = 'Leafo\ScssPhp\Formatter\Nested';

	/** @var  \SS_HTTPRequest */
	protected $request;

	// add paths to here if you want to serve files from outside of web root
	private static $safe_paths = [
		BASE_PATH,
	];
	// in live mode css will be compiled to this file
	private static $live_path = 'assets/sassy/styles.css';

	// if paths are prefixed by DIRECTORY_SEPARATOR they are treated as relative to site root,
	// otherwise the current theme dir (e.g. themes/name/scss for scss_path)
	private static $scss_path = 'scss';

	// dir is private in parent so save a copy here to reference in overloaded findInput
	protected $scssPath;

	private static $font_paths = [
		'fonts',
	];

	// set to folder where css should be compiled to, e.g. '/assets/css'. If not set the combined files folder will be used.
	private static $css_path = '';

	// paths to check for scss files being imported if not fully specified in scss
	// these are relative to site root (starting with '/') if they are loading scss from other
	// modules, otherwise relative to themes
	private static $extra_import_paths = array();

	// map of SS environment to Formatter class
	private static $formatters = [
		'dev'  => 'Leafo\ScssPhp\Formatter\Nested',
		'test' => 'Leafo\ScssPhp\Formatter\Compact',
		'live' => 'Leafo\ScssPhp\Formatter\Compressed',
	];

	/**
	 * Paths to write compiled css to depending on current environment.
	 * Cache file paths ending in '/' will mean a temporary name is generated each time (e.g. for dev),
	 * otherwise a specific file path and name is used, e.g. 'sassy/main.css' will always write to that file.
	 * If path begins with '/' it is relative to site root otherwise relative to assets.
	 *
	 * @var array
	 */
	private static $output_paths = [
		'dev'  => 'sassy/',
		'test' => 'sassy/',
		'live' => 'sassy/live.css',
	];

	public function __invoke() {
		return $this;
	}

	/**
	 * Constructor
	 *
	 * @param string                       $dir      Root directory to .scss files
	 * @param string                       $cacheDir Cache directory
	 * @param \Leafo\ScssPhp\Compiler|null $compiler SCSS compiler instance
	 *
	 * @throws \Exception
	 */
	public function __construct( $dir = '', $cacheDir = null, $compiler = null ) {
		$this->scssPath = $dir ?: static::scss_path();

		$extraScssPaths = static::config()->get( 'extra_import_paths' ) ?: [];

		$importPaths = array_merge(
			[
				$this->scssPath,
			],
			array_map(
				function ( $path ) {
					return static::checked_path( $path, SSViewer::get_theme_folder() );
				},
				$extraScssPaths
			)
		);

		if ( ! isset( $compiler ) ) {
			$compiler = new Compiler();
			$compiler->setImportPaths( $importPaths );
			$compiler->setFormatter( $this->formatter() );
		}

		$cacheDir = $cacheDir ?: static::cache_path();

		parent::__construct( $this->scssPath, $cacheDir, $compiler );
	}

	public function generate( SS_HTTPRequest $request ) {
		$this->request = $request;

		ob_start();
		$this->serve();
		return ob_get_clean();
	}

	/**
	 * Override to remove requirement that name ends with '.scss' as params passed from SS do not have extensions.
	 *
	 * @return bool|string
	 */
	protected function findInput() {
		if ( ( $input = $this->inputName() )
		     && strpos( $input, '..' ) === false
		) {
			$name = $this->join( $this->scssPath, $input . ".scss" );

			if ( is_file( $name ) && is_readable( $name ) ) {
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
		return $this->request->param( 'Name' );
	}

	/**
	 * Return the Scss compiler formatter to use for the current silverstripe environment type (dev, test, live).
	 *
	 * @return string
	 */
	private function formatter() {
		$formatters = static::config()->get( 'formatters' );

		return isset( $formatters[ static::CurrentEnvironment ] )
			? $formatters[ static::CurrentEnvironment ]
			: static::DefaultFormatter;
	}

	/**
	 * Return the generated output css file name
	 *
	 * @param string $fileName absolute path to output file.
	 *
	 * @return string
	 * @throws \Exception
	 * @throws \Modular\Exceptions\Config
	 */
	protected function cacheName( $fileName ) {
		return Controller::join_links(
			static::css_path(),
			basename($fileName, '.scss')
		) . '.css';
	}

	/**
	 * Return absolute directory path to 'main' scss files, e.g. 'themes/themedir/scss'
	 *
	 * @return string
	 * @throws \Exception
	 */
	private static function scss_path() {
		$path = static::config()->get( 'scss_path' );
		return static::checked_path( $path, SSViewer::get_theme_folder() );
	}

	/**
	 * Return filesystem absolute path to where to generate intermediate cache files.
	 *
	 * @return string
	 * @throws \Exception
	 */
	private static function cache_path() {
		$path = static::config()->get( 'cache_path' )
			?: Controller::join_links('/', Requirements::backend()->getCombinedFilesFolder() . "/.sassy");

		return static::checked_path( $path, ASSETS_DIR, true );
	}

	/**
	 * Return absolute directory path to where final generated css file(s) should be saved to.
	 *
	 * @return string
	 * @throws \Exception
	 */
	private static function css_path() {
		$path = static::config()->get( 'output_path' )
			?: Controller::join_links('/', Requirements::backend()->getCombinedFilesFolder());

		return static::checked_path( $path, ASSETS_DIR, true );
	}

	/**
	 * Build a path to an existing (real) directory on the server relative to BASE_PATH or the current theme path. The path must exist as it needs
	 * to be checked to make sure it is inside the web root.
	 *
	 * @param string $path         relative to site root if starts with DIRECTORY_SEPARATOR otherwise relative to current them folder
	 * @param string $relativePath if path doesn't start with '/' prepend this
	 * @param bool   $create       the path if it doesn't exist (e.g. for compiled css output)
	 *
	 * @return string
	 * @throws \Modular\Exceptions\Module
	 */
	public static function checked_path( $path, $relativePath, $create = false ) {
		if ( $path ) {
			if ( in_array( substr( $path, 0, 1 ), [ '\\', '/' ] ) ) {
				$path = Controller::join_links( BASE_PATH, $path );
			} else {
				$path = Controller::join_links( BASE_PATH, $relativePath, $path );
			}
			if ( ! realpath( $path ) && ! $create ) {
				throw new Exception( "'$path' doesn't exist" );
			}
			$safe = false;
			foreach ( static::config()->get( 'safe_paths' ) as $safePath ) {
				if ( $safe = ( substr( $path, 0, strlen( $safePath ) ) == $safePath ) ) {
					break;
				}
			}
			if ( ! $safe ) {
				throw new Exception( "'$path' is not inside list of safe paths so won't continue" );
			}
			if ( ! is_dir( $path ) && $create && ( substr( $path, 0, strlen( ASSETS_PATH )) == ASSETS_PATH ) ) {
				// if under assets path then create the output path if it doesn't exist.
				Filesystem::makeFolder( $path );
			}
			$path = realpath( $path );
		}
		if ( ! $path ) {
			throw new Exception( "Path '$path' doesn't exist" );
		}

		return $path;
	}

}