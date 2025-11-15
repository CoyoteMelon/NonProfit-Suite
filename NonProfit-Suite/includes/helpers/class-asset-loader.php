<?php
/**
 * Asset Loader for CSS/JS optimization
 *
 * Handles minification and concatenation of assets
 *
 * @package    NonprofitSuite
 * @subpackage NonprofitSuite/includes/helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asset Loader class for optimizing CSS and JavaScript
 */
class NonprofitSuite_Asset_Loader {

	/**
	 * Whether to use minified assets
	 *
	 * @var bool
	 */
	private static $use_minified = true;

	/**
	 * Cache directory for combined assets
	 *
	 * @var string
	 */
	private static $cache_dir = '';

	/**
	 * Initialize asset loader
	 */
	public static function init() {
		// Use minified assets in production
		self::$use_minified = ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );

		// Set cache directory
		$upload_dir = wp_upload_dir();
		self::$cache_dir = trailingslashit( $upload_dir['basedir'] ) . 'nonprofitsuite-cache/';

		// Create cache directory if it doesn't exist
		if ( ! file_exists( self::$cache_dir ) ) {
			wp_mkdir_p( self::$cache_dir );
		}

		// Add version query string for cache busting
		add_filter( 'nonprofitsuite_asset_version', array( __CLASS__, 'get_asset_version' ) );
	}

	/**
	 * Enqueue optimized stylesheet
	 *
	 * @param string $handle Style handle
	 * @param string $src Source URL or path
	 * @param array  $deps Dependencies
	 * @param string $version Version
	 * @param string $media Media type
	 */
	public static function enqueue_style( $handle, $src, $deps = array(), $version = null, $media = 'all' ) {
		$src = self::get_optimized_asset( $src, 'css' );
		$version = $version ?: self::get_asset_version();

		wp_enqueue_style( $handle, $src, $deps, $version, $media );
	}

	/**
	 * Enqueue optimized script
	 *
	 * @param string $handle Script handle
	 * @param string $src Source URL or path
	 * @param array  $deps Dependencies
	 * @param string $version Version
	 * @param bool   $in_footer In footer
	 */
	public static function enqueue_script( $handle, $src, $deps = array(), $version = null, $in_footer = true ) {
		$src = self::get_optimized_asset( $src, 'js' );
		$version = $version ?: self::get_asset_version();

		wp_enqueue_script( $handle, $src, $deps, $version, $in_footer );
	}

	/**
	 * Get optimized asset URL
	 *
	 * Returns minified version if available and enabled
	 *
	 * @param string $src Source path or URL
	 * @param string $type Asset type (css or js)
	 * @return string Optimized asset URL
	 */
	private static function get_optimized_asset( $src, $type ) {
		if ( ! self::$use_minified ) {
			return $src;
		}

		// Check if minified version exists
		$path_parts = pathinfo( $src );
		$minified_src = $path_parts['dirname'] . '/' . $path_parts['filename'] . '.min.' . $path_parts['extension'];

		// Convert URL to file path for checking
		$plugin_url = plugin_dir_url( dirname( dirname( __FILE__ ) ) );
		$plugin_path = plugin_dir_path( dirname( dirname( __FILE__ ) ) );

		if ( strpos( $src, $plugin_url ) === 0 ) {
			$file_path = str_replace( $plugin_url, $plugin_path, $minified_src );

			if ( file_exists( $file_path ) ) {
				return $minified_src;
			}
		}

		return $src;
	}

	/**
	 * Combine multiple CSS files into one
	 *
	 * @param array  $files Array of CSS file paths
	 * @param string $cache_key Unique cache key
	 * @return string|false URL to combined file or false on failure
	 */
	public static function combine_css( $files, $cache_key ) {
		$cache_file = self::$cache_dir . $cache_key . '.css';
		$cache_url = wp_upload_dir()['baseurl'] . '/nonprofitsuite-cache/' . $cache_key . '.css';

		// Check if cache file exists and is fresh
		if ( file_exists( $cache_file ) && ! self::should_regenerate_cache( $files, $cache_file ) ) {
			return $cache_url;
		}

		// Combine files
		$combined = '';
		foreach ( $files as $file ) {
			if ( file_exists( $file ) ) {
				$content = file_get_contents( $file );
				// Fix relative URLs in CSS
				$content = self::fix_css_urls( $content, dirname( $file ) );
				$combined .= $content . "\n";
			}
		}

		// Minify if possible
		if ( self::$use_minified ) {
			$combined = self::minify_css( $combined );
		}

		// Write to cache
		if ( file_put_contents( $cache_file, $combined ) !== false ) {
			return $cache_url;
		}

		return false;
	}

	/**
	 * Combine multiple JS files into one
	 *
	 * @param array  $files Array of JS file paths
	 * @param string $cache_key Unique cache key
	 * @return string|false URL to combined file or false on failure
	 */
	public static function combine_js( $files, $cache_key ) {
		$cache_file = self::$cache_dir . $cache_key . '.js';
		$cache_url = wp_upload_dir()['baseurl'] . '/nonprofitsuite-cache/' . $cache_key . '.js';

		// Check if cache file exists and is fresh
		if ( file_exists( $cache_file ) && ! self::should_regenerate_cache( $files, $cache_file ) ) {
			return $cache_url;
		}

		// Combine files
		$combined = '';
		foreach ( $files as $file ) {
			if ( file_exists( $file ) ) {
				$content = file_get_contents( $file );
				$combined .= $content . ";\n";
			}
		}

		// Minify if possible
		if ( self::$use_minified ) {
			$combined = self::minify_js( $combined );
		}

		// Write to cache
		if ( file_put_contents( $cache_file, $combined ) !== false ) {
			return $cache_url;
		}

		return false;
	}

	/**
	 * Simple CSS minification
	 *
	 * @param string $css CSS content
	 * @return string Minified CSS
	 */
	private static function minify_css( $css ) {
		// Remove comments
		$css = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css );

		// Remove whitespace
		$css = str_replace( array( "\r\n", "\r", "\n", "\t", '  ', '    ', '    ' ), '', $css );
		$css = preg_replace( '/\s+/', ' ', $css );

		// Remove unnecessary spaces
		$css = preg_replace( '/\s*([:;{}])\s*/', '$1', $css );

		return trim( $css );
	}

	/**
	 * Simple JavaScript minification
	 *
	 * @param string $js JavaScript content
	 * @return string Minified JavaScript
	 */
	private static function minify_js( $js ) {
		// Remove single-line comments (but not URLs)
		$js = preg_replace( '#^\s*//.*$#m', '', $js );

		// Remove multi-line comments
		$js = preg_replace( '#/\*.*?\*/#s', '', $js );

		// Remove whitespace
		$js = preg_replace( '/\s+/', ' ', $js );

		// Remove spaces around operators
		$js = preg_replace( '/\s*([\{\};\(\),\=\+\-\*\/])\s*/', '$1', $js );

		return trim( $js );
	}

	/**
	 * Fix relative URLs in CSS
	 *
	 * @param string $css CSS content
	 * @param string $base_path Base path of CSS file
	 * @return string CSS with fixed URLs
	 */
	private static function fix_css_urls( $css, $base_path ) {
		return preg_replace_callback(
			'/url\([\'"]?([^\'")]+)[\'"]?\)/i',
			function( $matches ) use ( $base_path ) {
				$url = $matches[1];

				// Skip absolute URLs and data URIs
				if ( preg_match( '/^(https?:\/\/|\/\/|data:)/i', $url ) ) {
					return $matches[0];
				}

				// Convert relative path to absolute
				$absolute_path = realpath( $base_path . '/' . $url );
				if ( $absolute_path ) {
					$plugin_path = plugin_dir_path( dirname( dirname( __FILE__ ) ) );
					$plugin_url = plugin_dir_url( dirname( dirname( __FILE__ ) ) );
					$absolute_url = str_replace( $plugin_path, $plugin_url, $absolute_path );
					return 'url(' . $absolute_url . ')';
				}

				return $matches[0];
			},
			$css
		);
	}

	/**
	 * Check if cache should be regenerated
	 *
	 * @param array  $source_files Source files
	 * @param string $cache_file Cache file
	 * @return bool True if cache should be regenerated
	 */
	private static function should_regenerate_cache( $source_files, $cache_file ) {
		$cache_time = filemtime( $cache_file );

		foreach ( $source_files as $file ) {
			if ( file_exists( $file ) && filemtime( $file ) > $cache_time ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get asset version for cache busting
	 *
	 * @return string Version string
	 */
	public static function get_asset_version() {
		// Use plugin version and last modification time
		$version = NONPROFITSUITE_VERSION;

		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$version .= '.' . time();
		}

		return $version;
	}

	/**
	 * Clear asset cache
	 *
	 * @return bool True on success
	 */
	public static function clear_cache() {
		if ( ! file_exists( self::$cache_dir ) ) {
			return true;
		}

		$files = glob( self::$cache_dir . '*' );
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}

		return true;
	}

	/**
	 * Get cache statistics
	 *
	 * @return array Cache statistics
	 */
	public static function get_cache_stats() {
		if ( ! file_exists( self::$cache_dir ) ) {
			return array(
				'files' => 0,
				'size'  => 0,
			);
		}

		$files = glob( self::$cache_dir . '*' );
		$total_size = 0;

		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				$total_size += filesize( $file );
			}
		}

		return array(
			'files' => count( $files ),
			'size'  => size_format( $total_size ),
		);
	}
}

// Initialize asset loader
NonprofitSuite_Asset_Loader::init();
