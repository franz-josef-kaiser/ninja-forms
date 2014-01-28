<?php
/*
Plugin Name: Ninja Forms
Plugin URI: http://ninjaforms.com/
Description: Ninja Forms is a webform builder with unparalleled ease of use and features.
Version: 2.4.2
Author: The WP Ninjas
Author URI: http://ninjaforms.com
Text Domain: ninja-forms
Domain Path: /lang/

Copyright 2011 WP Ninjas/Kevin Stover.


This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

Ninja Forms also uses the following jQuery plugins. Their licenses can be found in their respective files.

	jQuery TipTip Tooltip v1.3
	code.drewwilson.com/entry/tiptip-jquery-plugin
	www.drewwilson.com
	Copyright 2010 Drew Wilson

	jQuery MaskedInput v.1.3.1
	http://digitalbush.co
	Copyright (c) 2007-2011 Josh Bush

	jQuery Tablesorter Plugin v.2.0.5
	http://tablesorter.com
	Copyright (c) Christian Bach 2012

	jQuery AutoNumeric Plugin v.1.9.15
	http://www.decorplanit.com/plugin/
	By: Bob Knothe And okolov Yura aka funny_falcon

*/
global $wpdb, $wp_version;

define("NINJA_FORMS_DIR", plugin_dir_path( __FILE__ ) );
define("NINJA_FORMS_URL", plugin_dir_url( __FILE__ ) );
define("NINJA_FORMS_VERSION", "2.4.2");
define("NINJA_FORMS_TABLE_NAME",            "{$wpdb->prefix}ninja_forms");
define("NINJA_FORMS_FIELDS_TABLE_NAME",     "{$wpdb->prefix}ninja_forms_fields");
define("NINJA_FORMS_FAV_FIELDS_TABLE_NAME", "{$wpdb->prefix}ninja_forms_fav_fields");
define("NINJA_FORMS_SUBS_TABLE_NAME",       "{$wpdb->prefix}ninja_forms_subs");

define("NINJA_FORMS_JS_DEBUG", false);


'' === session_id() AND session_start();
$_SESSION['NINJA_FORMS_DIR'] = NINJA_FORMS_DIR;
$_SESSION['NINJA_FORMS_URL'] = NINJA_FORMS_URL;

/**
 * Load all PHP-files from ~/includes and upwards
 */
add_action( 'plugins_loaded', 'ninja_forms_load_files' );
function ninja_forms_load_files()
{
	# File Loader: Build Stack of sub directories
	$base  = plugin_dir_path( __FILE__ );
	$flags = GLOB_ONLYDIR | GLOB_BRACE;
	$dirs  = glob( "{$base}{**/*}", $flags );

	$stack = array( "{$base}includes" );
	foreach( $dirs as $tree )
	{
		$stack[] = $tree;

		$subTree = glob( "{$tree}{/**}", $flags );
		if ( empty( $subTree ) )
			continue;

		foreach( $subTree as $sub )
			$stack[] = $sub;
	}

	# File Loader: Load files
	# Add any switches for special files in here
	foreach ( $stack as $dir )
	{
		// Only load PHP files
		$files = glob( "{$dir}/*.php" );

		// Custom updater for the EDD-plugin
		if (
			! class_exists( 'EDD_SL_Plugin_Updater' )
			AND FALSE !== ( $pos = array_search(
				"{$base}includes/EDD_SL_Plugin_Updater.php",
				$files
			) )
		)
			unset( $files[ $pos ] );

		foreach ( $files as $key => $file )
			include_once $file;
	}
}

// Set $_SESSION variable used for storing items in transient variables
function ninja_forms_set_transient_id(){
	if ( !isset ( $_SESSION['ninja_forms_transient_id'] ) AND !is_admin() ) {
		$t_id = ninja_forms_random_string();
		// Make sure that our transient ID isn't currently in use.
		while ( get_transient( $t_id ) !== false ) {
			$_id = ninja_forms_random_string();
		}
		$_SESSION['ninja_forms_transient_id'] = $t_id;
	}
}
add_action( 'init', 'ninja_forms_set_transient_id', 1 );

function ninja_forms_load_lang() {

	/** Set our unique textdomain string */
	$textdomain = 'ninja-forms';

	/** The 'plugin_locale' filter is also used by default in load_plugin_textdomain() */
	$locale = apply_filters( 'plugin_locale', get_locale(), $textdomain );

	/** Set filter for WordPress languages directory */
	$wp_lang_dir = apply_filters(
		'ninja_forms_wp_lang_dir',
		WP_LANG_DIR . '/ninja-forms/' . $textdomain . '-' . $locale . '.mo'
	);

	/** Translations: First, look in WordPress' "languages" folder = custom & update-secure! */
	load_textdomain( $textdomain, $wp_lang_dir );

	/** Translations: Secondly, look in plugin's "lang" folder = default */
	$plugin_dir = basename( dirname( __FILE__ ) );
	$lang_dir = apply_filters( 'ninja_forms_lang_dir', $plugin_dir . '/lang/' );
	load_plugin_textdomain( $textdomain, FALSE, $lang_dir );

}
add_action('plugins_loaded', 'ninja_forms_load_lang');

function ninja_forms_update_version_number(){
	$plugin_settings = get_option( 'ninja_forms_settings' );

	if ( !isset ( $plugin_settings['version'] ) OR ( NINJA_FORMS_VERSION != $plugin_settings['version'] ) ) {
		$plugin_settings['version'] = NINJA_FORMS_VERSION;
		update_option( 'ninja_forms_settings', $plugin_settings );
	}
}
add_action( 'admin_init', 'ninja_forms_update_version_number' );

register_activation_hook( __FILE__, 'ninja_forms_activation' );

function ninja_forms_return_echo($function_name){
	$arguments = func_get_args();
    array_shift($arguments); // We need to remove the first arg ($function_name)
    ob_start();
    call_user_func_array($function_name, $arguments);
	$return = ob_get_clean();
	return $return;
}

function ninja_forms_random_string($length = 10){
	$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $random_string = '';
    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $random_string;
}

function ninja_forms_remove_from_array($arr, $key, $val, $within = FALSE) {
    foreach ($arr as $i => $array)
            if ($within && stripos($array[$key], $val) !== FALSE && (gettype($val) === gettype($array[$key])))
                unset($arr[$i]);
            elseif ($array[$key] === $val)
                unset($arr[$i]);

    return array_values($arr);
}

function ninja_forms_letters_to_numbers( $size ) {
	$l		= substr( $size, -1 );
	$ret	= substr( $size, 0, -1 );
	switch( strtoupper( $l ) ) {
		case 'P':
			$ret *= 1024;
		case 'T':
			$ret *= 1024;
		case 'G':
			$ret *= 1024;
		case 'M':
			$ret *= 1024;
		case 'K':
			$ret *= 1024;
	}
	return $ret;
}