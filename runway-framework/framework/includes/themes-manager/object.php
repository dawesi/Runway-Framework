<?php
define( 'DS', DIRECTORY_SEPARATOR ); // I always use this short form in my code.

/**
 * Themes_Manager_Settings_Object
 *
 * @category Runway themes
 * @package  Core extensions
 * @author    <>
 * @license
 * @link
 *
 * Registered actions:
 * 1. before_save_theme_settings - takes theme settings options array
 * 2. after_save_theme_settings - takes theme settings options array
 * 3. before_build_theme_css - takes theme options array
 * 4. after_build_theme_css - takes theme css string
 * 5. before_delete_child_theme - takes theme name
 * 6. after_delete_chold_theme - takes theme name
 * 7. before_build_child_package - takes theme name
 * 8. after_build_child_package - takes theme name and download path
 * 9. before_build_alone_theme - takes theme name
 * 10. after_build_alone_theme - takes theme name and download path
 */
class Themes_Manager_Settings_Object {
	public $extensions_dir;

	// construct the developer tools object
	function __construct() {
		$this->extensions_dir = TEMPLATEPATH . '/framework/extensions/';
		$this->themes_path = $this->build_themes_path();
		$this->themes_url = home_url().'/wp-content/themes';
		$this->default_theme_package_path = TEMPLATEPATH . '/framework/themes/default.zip';
	}

	// theme settings validation rules
	function validate_theme_settings( $settings = null ) {

		$errors = array();

		// if settings are empty
		if ( !$settings )
			return $errors[] = '';

		// Theme title validation
		if ( !isset( $settings['Name'] ) || empty( $settings['Name'] ) )
			$errors[] = 'Theme title is required';

		if ( !preg_match( '/([a-zA-Z])/', $settings['Name'] ) ) {
			$errors[] = 'Theme title need to have at least one character';
		}

		if ( empty( $settings['Folder'] ) && isset( $settings['Name'] ) && !empty( $settings['Name'] ) ) {
			$settings['Folder'] = $this->make_theme_folder_from_name( $settings['Name'] );
		}

		$_REQUEST['base_name'] = ( isset( $_REQUEST['base_name'] ) ) ? $_REQUEST['base_name'] : '';
		if ( $_REQUEST['base_name'] != $settings['Folder'] ) {
			if ( file_exists( $this->themes_path . '/' . $settings['Folder'] ) ) {
				$errors[] = 'Please choose another theme folder';
			}
		}

		return $errors;

	}

	// search only for Runway themes or themes based on Runway
	function search_themes() {

		$themes_dir = opendir( $this->themes_path );
		$themes_list = array();

		while ( $dir = readdir( $themes_dir ) ) {
			if ( $dir != '.' && $dir != '..' && is_dir( $this->themes_path.'/'.$dir ) ) {
				//$theme = $this->load_settings($dir);
				$theme = rw_get_theme_data( $this->themes_path.'/'.$dir );
				// add to list themes which based on runway
				if ( file_exists( $this->themes_path.'/'.$dir.'/style.css' ) )
					if ( $theme['Template'] == 'runway-framework' )
						$themes_list[$dir] = $theme;
			}
		}
		if ( file_exists( $this->themes_path.'/runway-framework' ) )
			$themes_list['runway-framework'] = rw_get_theme_data( $this->themes_path.'/runway-framework' );

		return $themes_list;

	}

	function make_theme_copy( $name = null, $new_name = null ) {

		if ( !$name || !$new_name ) return false;

		if ( file_exists( $this->themes_path . '/' . $new_name ) ) return false;

		// copy source theme
		$this->copy_r( $this->themes_path . '/' . $name, $this->themes_path . '/' . $new_name );

		$settings = json_decode( file_get_contents( $this->themes_path . '/' . $new_name . '/data/settings.json' ), true );
		$settings['Folder'] = $new_name;
		file_put_contents( $this->themes_path . '/' . $new_name . '/data/settings.json', json_encode( $settings ) );

		$theme_info = file_get_contents( $this->themes_path . '/' . $new_name . '/style.css' );
		$theme_info = str_replace( "Theme Name:     $name", "Theme Name:     $new_name", $theme_info );
		file_put_contents( $this->themes_path . '/' . $new_name . '/style.css', $theme_info );

		return $settings;

	}

	// extract wordpress themes path
	function build_themes_path() {

		$path = explode( '/', TEMPLATEPATH );
		unset( $path[count( $path ) - 1] );

		return implode( '/', $path );

	}

	// return extended theme information array
	function theme_information( $folder ) {

		if ( !file_exists( $this->themes_path . '/' . $folder . '/style.css' ) ) return null;

		$theme = rw_get_theme_data( $this->themes_path . '/' . $folder );

		if ( file_exists( $this->themes_path . '/' . $folder . '/screenshot.png' ) ) {
			$theme['screenshot'] = get_bloginfo( 'url' ) . '/wp-content/themes/' . $folder . '/screenshot.png';
		} else {
			$theme['screenshot'] = get_bloginfo( 'url' ) . '/wp-content/themes/runway-framework/screenshot.png';
		}

		$theme['Folder_location'] = '/wp-content/themes/' . $folder;
		$theme['Folder'] = $folder;

		return $theme;
	}

	// save settings array to JSON file
	function save_settings( $theme_folder, $settings ) {

		do_action( 'before_save_theme_settings', $settings );
		$json = json_encode( $settings );
		file_put_contents( $this->themes_path . '/' . $theme_folder . '/data/settings.json', $json );
		do_action( 'after_save_theme_settings', $settings );

	}

	// load settings array from JSON file
	function load_settings( $theme_folder ) {

		$settings = array();
		$settings_file = $this->themes_path . '/' . $theme_folder . '/data/settings.json';
		if ( file_exists( $this->themes_path . '/' . $theme_folder . '/data/settings.json' ) ) {
			$json = file_get_contents( $this->themes_path . '/' . $theme_folder . '/data/settings.json' );
			$settings = json_decode( $json, true );
		}
		else {
			if ( !file_exists( $this->themes_path . '/' . $theme_folder . '/data' ) ) {
				if ( is_writable( $this->themes_path . '/' . $theme_folder . '/data' ) ) {
					if ( mkdir( $this->themes_path . '/' . $theme_folder . '/data', 0777, true ) ) {
						fopen( $settings_file, 'a' );
					}
				}
			}
			elseif ( !file_exists( $settings_file ) ) {
				fopen( $settings_file, 'a' );
			}
		}

		return $settings;
	}

	function make_theme_folder_from_name( $name = null ) {

		$folder = strtolower( $name );
		$folder = str_replace( ' ', '-', $folder );
		$folder = str_replace( "'", '-', $folder );

		return $folder;

	}

	// build and save child theme
	function build_and_save_theme( $options , $new_theme = true ) {
		global $extm;
		// extract tags from string
		$options['Tags'] = explode( ' ', $options['Tags'] );

		// set template to runway-framework
		$options['Template'] = 'runway-framework';

		// if theme folder unknown name folder like theme name
		if ( !isset( $options['Folder'] ) || empty( $options['Folder'] ) )
			$options['Folder'] = $this->make_theme_folder_from_name( $options['Name'] );
		else
			$options['Folder'] = $this->make_theme_folder_from_name( $options['Folder'] );

		// check form mode new or edit(duplicate)
		$this->mode = ( isset( $this->mode ) ) ? $this->mode : '';
		if ( $this->mode == 'new' ) {
			if ( file_exists( $this->themes_path . '/' . $options['Folder'] ) ) return false;
			mkdir( $this->themes_path . '/' . $options['Folder'] );
		} else {
			// change theme folder
			if ( !file_exists( $this->themes_path . '/' . $options['Folder'] ) ) {
				rename( $this->themes_path . '/' . $_REQUEST['name'], $this->themes_path . '/' . $options['Folder'] );
				// change file names into changed theme folder
				$this->rename_history_packages( $options['Folder'] );
			}
		}

		if ( !file_exists( $this->themes_path . '/' . $options['Folder'] . '/data' ) ) {
			mkdir( $this->themes_path . '/' . $options['Folder'] . '/data' );
		}

		// check if have new screenshot and if true move file to theme folder
		if ( $_FILES['theme_options']['name']['Screenshot'] != '' ) {
			imagepng(
				imagecreatefromstring(
					file_get_contents( $_FILES['theme_options']['tmp_name']['Screenshot'] )
				),
				$this->themes_path . '/' . $options['Folder'] . '/screenshot.png'
			);
			$options['Screenshot'] = true;
		}

		// check if have new custom icon and if true move file to theme folder
		if ( $_FILES['theme_options']['type']['CustomIcon'] == 'image/png' ) {
			imagepng(
				imagecreatefromstring(
					file_get_contents( $_FILES['theme_options']['tmp_name']['CustomIcon'] )
				),
				$this->themes_path . '/' . $options['Folder'] . '/tmp.png'
			);

			$image = $this->themes_path . '/' . $options['Folder'] . '/tmp.png';
			$new_image = $this->themes_path . '/' . $options['Folder'] . '/custom-icon.png';

			$size = getimagesize( $image );
			$width = 24; //*** Fix Width & Heigh (Autu caculate) ***//
			$height = round( $width*$size[1]/$size[0] );

			$images_orig = imagecreatefrompng( $image );
			$photoX = imagesx( $images_orig );
			$photoY = imagesy( $images_orig );

			$images_fin = imagecreatetruecolor( $width, $height );
			imagecopyresampled( $images_fin, $images_orig, 0, 0, 0, 0, $width+1, $height+1, $photoX, $photoY );

			imagepng( $images_fin, $new_image );
			imagedestroy( $images_orig );
			imagedestroy( $images_fin );
			unlink( $image );

			$options['CustomIcon'] = true;
		}

		if ( file_exists( $this->themes_path . '/' . $options['Folder'] . '/custom-icon.png' ) ) {
			$options['CustomIcon'] = true;
		}

		// If no custom screenshot copy default
		if ( file_exists( $this->themes_path . '/' . $options['Folder'] . '/screenshot.png' ) ) {
			$options['Screenshot'] = true;
		}
		else {
			copy( $this->themes_path.'/'.$options['Template'].'/screenshot.png', $this->themes_path.'/'.$options['Folder'].'/screenshot.png' );
			$options['Screenshot'] = true;
		}

		// save settings to JSON
		$this->save_settings( $options['Folder'], $options );

		if ( $new_theme ) {
			// Add functions.php
			$functions = '';
			if ( $this->themes_path . '/' . $options['Template'] . '/functions.php' ) {
				$functions = '<?php /* child theme functions */ ?>';
			}
			file_put_contents( $this->themes_path . '/' . $options['Folder'] . '/functions.php', $functions );
			// save settings into wordpress style.css
			file_put_contents( $this->themes_path . '/' . $options['Folder'] . '/style.css', $this->build_theme_css( $options ) );
		}
		else {
			$matches = array();
			$css = file_get_contents( $this->themes_path . '/' . $options['Folder'] . '/style.css' );
			$pattern = '/\/\*([^\*]*)/i';
			$result = preg_match( $pattern, $css, $matches );
			$css = str_replace( $matches['0']."*/", '', $css );
			$new_css = $this->build_theme_css( $options ).$css;
			// save settings into wordpress style.css
			file_put_contents( $this->themes_path . '/' . $options['Folder'] . '/style.css', $new_css );
		}

		// return settings to enable activate theme popup
		return $options;
	}

	// if disabled history each time before create new
	// packages will be deleted previous created
	function clear_old_packages( $dir = null ) {

		if ( !$dir ) return false;

		// load theme settings
		$settings = $this->load_settings( $dir );

		// check if history enabled
		if ( !$settings['History'] ) {
			// remove download folder (if already exists)
			if ( file_exists( "{$this->themes_path}/$dir/download" ) )
				$this->rrmdir( "{$this->themes_path}/$dir/download" );
			// male new download dir
			mkdir( "{$this->themes_path}/$dir/download" );
		}
	}



	// function-template for chuild theme css
	function build_theme_css( $options = null, $alone = false ) {
		do_action( 'before_build_theme_css', $options );
		if ( !$options ) return false;

		$lines = array();
		extract( $options );

		$lines[] = "/*\n";

		if ( !empty( $Tags ) && is_array( $Tags ) ) {
			$Tags = implode( ',', $Tags );
			if ( $Tags == ',' ) $Tags = '';
		}

		if ( isset( $Name ) )
			$lines[] = "Theme Name: {$Name}\n";
		if ( isset( $Icon ) )
			$lines[] = "Icon: {$Icon}\n";
		if ( isset( $URI ) )
			$lines[] = "Theme URI: {$URI}\n";
		if ( isset( $Description ) )
			$lines[] = "Description: {$Description}\n";
		if ( isset( $AuthorName ) )
			$lines[] = "Author: {$AuthorName}\n";
		if ( isset( $AuthorURI ) )
			$lines[] = "Author URI: {$AuthorURI}\n";

		if ( !$alone ) {
			if ( !isset( $Template ) || $Template != false )
				$lines[] = "Template: runway-framework\n";
		}

		if ( isset( $Version ) )
			$lines[] = "Version: {$Version}\n";
		if ( isset( $Tags ) )
			$lines[] = "Tags: {$Tags}\n";
		if ( isset( $License ) )
			$lines[] = "License: {$License}\n";
		if ( isset( $LicenseURI ) )
			$lines[] = "License URI: {$LicenseURI}\n";
		if ( isset( $Comments ) )
			$lines[] = "{$Comments}\n";

		$lines[] = '*/';
		$string = '';

		foreach ( $lines as $line ) {
			$string .= $line;
		}
		do_action( 'after_build_theme_css', $string );
		return $string;
	}

	// recursive copy
	function copy_r( $path, $dest, $exlude = array() ) {
		if ( is_dir( $path ) ) {
			@mkdir( $dest );
			$objects = scandir( $path );
			if ( sizeof( $objects ) > 0 ) {
				foreach ( $objects as $file ) {
					if ( $file == '.' || $file == '..' ) continue;
					// go on
					if ( is_dir( $path.DS.$file ) ) {
						if ( !in_array( $file, $exlude ) )
							$this->copy_r( $path.DS.$file, $dest.DS.$file );
					} else {
						copy( $path.DS.$file, $dest.DS.$file );
					}
				}
			}
			return true;
		} elseif ( is_file( $path ) ) {
			return copy( $path, $dest );
		} else {
			return false;
		}
	}

	// recursive delete
	function rrmdir( $dir ) {
		foreach ( glob( $dir . '/*' ) as $file ) {
			if ( is_dir( $file ) ) $this->rrmdir( $file );
			else unlink( $file );
		}

		@rmdir( $dir );
	}

	// delete child theme
	function delete_child_theme( $theme_name = null ) {

		do_action( 'before_delete_child_theme', $theme_name );

		if ( !$theme_name ) return false;

		$theme = $this->load_settings( $theme_name );

		$theme['Template'] = ( isset( $theme['Template'] ) ) ? $theme['Template'] : 'runway-framework';
		if ( $theme['Template'] != 'runway-framework' ) return false;

		$dir = $this->themes_path . '/' . $theme_name;

		if ( is_dir( $dir ) ) {
			$objects = scandir( $dir );
			foreach ( $objects as $object ) {
				if ( $object != '.' && $object != '..' ) {
					if ( filetype( $dir.'/'.$object ) == 'dir' ) $this->rrmdir( $dir.'/'.$object ); else unlink( $dir.'/'.$object );
				}
			}
			reset( $objects );
			rmdir( $dir );
		}
		do_action( 'after_delete_child_theme', $theme_name );
	}

	// blank
	function load_objects() {

	}

	/**
	 * Recursive adding files in zip archive
	 *
	 * @param unknown $path
	 * @param unknown $path_in_zip
	 * @param ZipArchive $zip
	 */
	function add_to_zip_r( $path, $path_in_zip, $zip, $exclude = array() ) {
		if ( !file_exists( $path ) ) return;

		$files = scandir( $path );
		foreach ( $files as $file ) {
			if ( $file != '.' && $file != '..' ) {
				if ( !in_array( $file, $exclude ) ) {
					if ( is_dir( $path.'/'.$file ) ) {
						$zip->addEmptyDir( $path_in_zip.$file );
						$this->add_to_zip_r( $path.'/'.$file, $path_in_zip.$file.'/', $zip );
					}
					elseif ( is_file( $path.'/'.$file ) ) {
						$zip->addFromString( $path_in_zip.$file, file_get_contents( $path.'/'.$file ) );
					}
				}
			}
		}
	}

	/**
	 * build_child_package - make child themes packages
	 *
	 * @param mixed   $theme_name Theme name.
	 * @param mixed   $ts         Time stamp to make unique download archive name.
	 *
	 * @access public
	 *
	 * @return mixed Value.
	 */
	function build_child_package( $theme_name = null, $ts = null ) {

		if ( class_exists( 'ZipArchive' ) ) {
			do_action( 'before_build_child_package' );
			if ( !$theme_name || !$ts ) return false;

			if ( !is_writable( $this->themes_path.'/'.$theme_name ) ) {
				wp_die( 'Please set write permissions for ' . $this->themes_path.'/'.$theme_name . '  and then refresh page' );
			}

			$zip = new ZipArchive();

			$packages_storage_path = "$this->themes_path/{$theme_name}/download";

			if ( !file_exists( $packages_storage_path ) ) {
				mkdir( $packages_storage_path );
			}

			$zip_file_name = "{$theme_name}-({$ts}).c.zip";
			$zip->open( $packages_storage_path . '/' . $zip_file_name, ZIPARCHIVE::CREATE );
			$source = "$this->themes_path/{$theme_name}";

			$source = str_replace( '\\', '/', realpath( $source ) );
			if ( is_dir( $source ) === true ) {
				$files = scandir( $source );
				foreach ( $files as $file ) {
					if ( $file != '.' && $file != '..' ) {
						$file = $source.'/'.$file;

						if ( is_dir( $file ) === true ) {
							$zip->addEmptyDir( str_replace( $source . '/', "{$theme_name}/", $file . '/' ) );
							$arr = explode( '/', $file );
							if ( array_pop( $arr ) == 'assets' ) {
								$this->add_to_zip_r( $file, $theme_name.'/assets/', $zip );
							}
							if ( array_pop( $arr ) == 'data' ) {
								$this->add_to_zip_r( $file, $theme_name.'/data/', $zip );
							}
						}
						else if ( is_file( $file ) === true ) {
								$zip->addFromString( str_replace( $source . '/', "{$theme_name}/", $file ), file_get_contents( $file ) );
							}
					}
				}
			}
			else if ( is_file( $source ) === true ) {
					$zip->addFromString( basename( $source ), file_get_contents( $source ) );
				}

			$zip->close();

			do_action( 'after_build_child_package', $theme_name, get_bloginfo( 'url' ) . "/wp-content/themes/{$theme_name}/download/child/{$zip_file_name}" );

			return get_bloginfo( 'url' ) . "/wp-content/themes/{$theme_name}/download/child/{$zip_file_name}";
		}
		else {
			wp_die( 'You must have ZipArchive class' );
		}
	}


	/**
	 * build_alone_theme - make alone theme package
	 *
	 * @param mixed   $theme_name Theme name.
	 * @param mixed   $ts         Time stamp to make unique download archive name.
	 *
	 * @access public
	 *
	 * @return mixed Value.
	 */
	function build_alone_theme( $theme_name = null, $ts = null ) {

		if ( class_exists( 'ZipArchive' ) ) {
			do_action( 'before_build_alone_theme', $theme_name );
			global $extm;
			if ( !$theme_name || !$ts ) return false;

			if ( !is_writable( $this->themes_path.'/'.$theme_name ) ) {
				wp_die( 'Please set write permissions for ' . $this->themes_path.'/'.$theme_name . '  and then refresh page' );
			}

			$zip = new ZipArchive();

			$packages_storage_path = "$this->themes_path/{$theme_name}/download";

			if ( !file_exists( $packages_storage_path ) ) {
				mkdir( $packages_storage_path );
			}

			$zip_file_name = "{$theme_name}-({$ts}).a.zip";
			$zip->open( $packages_storage_path . '/' . $zip_file_name, ZIPARCHIVE::CREATE );

			$source = "$this->themes_path/runway-framework";
			$source = str_replace( '\\', '/', realpath( $source ) );

			// Copy framework and data types folder
			$zip->addEmptyDir( $theme_name.'/framework/' );
			$framework_dir = FRAMEWORK_DIR.'framework/';
			$this->add_to_zip_r( $framework_dir, $theme_name.'/framework/', $zip );

			$zip->addEmptyDir( $theme_name.'/data-types/' );
			$framework_dir = FRAMEWORK_DIR.'data-types/';
			$this->add_to_zip_r( $framework_dir, $theme_name.'/data-types/', $zip );

			// Add active extensions in package
			$zip->addEmptyDir( $theme_name.'/extensions/' );
			foreach ( $extm->get_active_extensions_list( $theme_name ) as $ext ) {
				if ( is_string( $ext ) ) {
					$ext_dir = explode( '/', $ext );
					$file = $source.'/extensions/'.$ext_dir[0].'/';
					$this->add_to_zip_r( $file, $theme_name.'/extensions/'.$ext_dir[0].'/', $zip );
				}
			}
			// merge functions.php
			$functions = ( file_exists( $source.'/functions.php' ) ) ? file_get_contents( $source.'/functions.php' ) : '';
			if ( file_exists( "{$this->themes_path}/{$theme_name}/functions.php" ) ) {
				$functions .= file_get_contents( "{$this->themes_path}/{$theme_name}/functions.php" );
			}
			$zip->addFromString( $theme_name.'/functions.php', $functions );

			// build plugin header
			$theme_data = rw_get_theme_data( get_theme_root().'/'.$theme_name );
			$theme_data['Tags'] = implode( ' ', $theme_data['Tags'] );
			$css = $this->build_theme_css( $theme_data, true );

			// merge style.css
			$css_ext = ( file_exists( "{$this->themes_path}/{$theme_name}/style.css" ) ) ? file_get_contents( "{$this->themes_path}/{$theme_name}/style.css" ) : '';
			$css_ext = $this->remove_plugin_header( $css_ext, $theme_data['Name'] );
			$css_ext = $css . $css_ext;
			$zip->addFromString( $theme_name.'/style.css', $css_ext );

			// copy child theme files
			$this->add_to_zip_r( get_stylesheet_directory(), $theme_name.'/', $zip, array( 'download', 'functions.php', 'style.css' ) );

			$zip->close();

			do_action( 'after_build_alone_theme', $theme_name, get_bloginfo( 'url' ) . "/wp-content/themes/{$theme_name}/download/child/{$zip_file_name}" );
			return get_bloginfo( 'url' ) . "/wp-content/themes/{$theme_name}/download/child/{$zip_file_name}";
		}
		else {
			wp_die( 'You must have ZipArchive class' );
		}
	}

	// remove plugin header in merged css file
	function remove_plugin_header( $css_ext = null, $theme_name = null ) {

		$start = 0;
		do {
			$pos = strpos( $css_ext, 'Theme Name: '.$theme_name, $start );
			$pos_begin = strpos( $css_ext, '/*', $start );
			$pos_end = strpos( $css_ext, '*/', $start );
			if ( $pos > $pos_begin && $pos < $pos_end ) {
				$css_ext = substr_replace( $css_ext, '', $pos_begin, $pos_end - $pos_begin + 2 );
			}
			$start = $pos_begin;
		} while ( $pos !== false );

		return $css_ext;
	}

	// build package info from TS (timestamp)
	function make_package_info_from_ts( $theme_name = null, $ts = null ) {
		if ( !$theme_name || !$ts ) return false;

		return array(
			'exp' => $ts,
			'date' => date( 'F j, Y', $ts ),
			'time' => date( 'g:i a', $ts ),
			'c_file' => file_exists( "{$this->themes_path}/{$theme_name}/download/{$theme_name}-({$ts}).c.zip" ) ? "{$theme_name}-({$ts}).c.zip" : '',
			'a_file' => file_exists( "{$this->themes_path}/{$theme_name}/download/{$theme_name}-({$ts}).a.zip" ) ? "{$theme_name}-({$ts}).a.zip" : '',
			'c_hash' => file_exists( "{$this->themes_path}/{$theme_name}/download/{$theme_name}-({$ts}).c.zip" ) ? md5_file( "{$this->themes_path}/{$theme_name}/download/{$theme_name}-({$ts}).c.zip" ) : '',
			'a_hash' => file_exists( "{$this->themes_path}/{$theme_name}/download/{$theme_name}-({$ts}).a.zip" ) ? md5_file( "{$this->themes_path}/{$theme_name}/download/{$theme_name}-({$ts}).a.zip" ) : '',
		);

	}

	// search previous created packages
	function get_history( $theme_name = null ) {
		if ( !$theme_name ) return false;

		$history = array();

		if ( file_exists( $this->themes_path . "/{$theme_name}/download" ) )
			$packages_dir = opendir( $this->themes_path . "/{$theme_name}/download" );

		if ( isset( $packages_dir ) && $packages_dir ) {
			while ( $file = readdir( $packages_dir ) ) {
				if ( $file != '.' && $file != '..' ) {
					if ( preg_match( '/.zip/', $file ) ) {
						preg_match( '/\((\d+)\)/', $file, $matches );
						if ( count( $matches > 0 ) ) {
							$ts = $matches[0];
							$ts = str_replace( '(', '', $ts );
							$ts = str_replace( ')', '', $ts );
						}
						else {
							continue;
						}
						$history[$ts] = $this->make_package_info_from_ts( $theme_name, $ts );
					}
				}
			}

			// Sort array (newest to oldest)
			krsort( $history );

			// remove packages if their count exceeds 10
			$to_del = array_slice( $history, 10 );
			foreach ( $to_del as $ts => $info ) {
				unset( $history[$ts] );
				if ( file_exists( $this->themes_path . "/{$theme_name}/download/".$info['c_file'] ) )
					unlink( $this->themes_path . "/{$theme_name}/download/".$info['c_file'] );
				if ( file_exists( $this->themes_path . "/{$theme_name}/download/".$info['a_file'] ) )
					unlink( $this->themes_path . "/{$theme_name}/download/".$info['a_file'] );
			}
		}

		return $history;
	}

	// URL for theme screenshot
	function screenshot_url( $theme_folder = null ) {
		if ( !$theme_folder ) return false;

		$path = "{$this->themes_path}/{$theme_folder}/screenshot.png";

		if ( !file_exists( $path ) ) {
			copy( "{$this->themes_path}/runway-framework/screenshot.png", $path );
		}

		return bloginfo( 'url' ) . "/wp-content/themes/{$theme_folder}/screenshot.png";

	}

	function rename_history_packages( $theme_folder = null ) {

		$packages_storage_path = "$this->themes_path/{$theme_folder}/download";
		if ( file_exists( $packages_storage_path ) ) {
			$download_dir = opendir( $packages_storage_path );
			while ( $file = readdir( $download_dir ) ) {
				if ( $file != '.' && $file != '..' ) {
					$pos = strpos( $file, '-(' );
					if ( $pos > 0 ) {
						$old_theme = substr( $file, 0, $pos );
						$new_file = str_replace( $old_theme, $theme_folder, $file );
						rename( $packages_storage_path.'/'.$file, $packages_storage_path.'/'.$new_file );
					}
				}
			}
		}


	}
} ?>
