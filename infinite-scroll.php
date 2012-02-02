<?php
/*
Plugin Name: Infinite Scroll
Description:
Version: 1.0
Author: Beaver6813, dirkhaim, Paul Irish, benbalter
Author URI: 
License: GPL3
*/

/*  Copyright 2012  Beaver6813, dirkhaim, Paul Irish, Benjamin J. Balter
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  @copyright 2012
 *  @license GPL v3
 *  @version 2.5
 *  @package Infinite Scroll
 *  @author Beaver6813, dirkhaim, Paul Irish, Benjamin J. Balter
 */

require_once dirname( __FILE__ ) . '/includes/options.php';
require_once dirname( __FILE__ ) . '/includes/admin.php';
require_once dirname( __FILE__ ) . '/includes/presets.php';

class Infinite_Scroll  {

	static $instance;
	public $options;
	public $admin;
	public $name      = 'Infinite Scroll'; //Human-readable name of plugin
	public $slug      = 'infinite-scroll'; //plugin slug, generally base filename and in url on wordpress.org
	public $slug_     = 'infinite_scroll'; //slug with underscores (PHP/JS safe)
	public $prefix    = 'infinite_scroll_'; //prefix to append to all options, API calls, etc. w/ trailing underscore
	public $file      = null;
	public $directory = null;
	public $version   = '2.5';

	/**
	 * Construct the boilerplate and autoload all child classes
	 */
	function __construct() {

		self::$instance = &$this;
		$this->file = __FILE__;
		$this->admin = new Infinite_Scroll_Admin( &$this );
		$this->options = new Infinite_Scroll_Options( &$this );
		$this->presets = new Infinite_Scroll_Presets( &$this );
						
		//upgrade db
		add_action( 'admin_init', array( &$this, 'upgrade_check' ) );
		
		//i18n
		add_action( 'init', array( &$this, 'i18n' ) );
	
		//default options
		add_action( 'init', array( &$this, 'init_defaults' ) );
		
		//js
		add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_js' ) );
		add_action( 'wp_footer', array( &$this, 'footer' ), 100 ); //low priority will load after i18n and script loads
		
		register_activation_hook( __FILE__, $this->presets->schedule );
		register_deactivation_hook( __FILE__, $this->presets->unschedule );

	}

	/**
	 * Init default options
	 */
	function init_defaults() {
		
		//option keys map to javascript options where applicable
		$this->options->defaults = array( 
			'finishedMsg' => __( '<em>Congratulations, you\'ve reached the end of the internet.</em>', 'infinite-scroll' ),
			'img' => plugins_url( 'img/ajax-loader.gif', __FILE__ ), 
			'msgText' => __( '<em>Loading the next set of posts...</em>', 'infinite-scroll' ),
			'nextSelector' => '#nav-below a:first',
			'navSelector' => '#nav-below',
			'itemSelector' => '.post',
			'contentSelector' => '#content',
			'debug' => ( WP_DEBUG || SCRIPT_DEBUG ),
		);

	}
	
	/**
	 * Enqueue front-end JS and pass options to json_encoded array
	 */
	function enqueue_js() {
		
		//no need to show on singular pages
		if ( is_singlular() )
			return;
		
		$suffix = ( WP_DEBUG || WP_SCRIPT_DEBUG ) ? '.dev' : '';
		
		$file = "/js/front-end/jquery.infinitescroll{$suffix}.js";

		wp_enqueue_script( $this->slug, plugins_url( $file, __FILE__ ), array( 'jquery' ), $this->version, true ); 
	
		wp_localize_script( $this->slug, $this->slug_, $this->options->get_options() );	
		
	}
	
	/**
	 * Load footer template to pass options array to JS
	 */
	function footer() {
		require dirname( __FILE__ ) . '/templates/footer.php';
	}
	
	/**
	 * Init i18n files
	 */
	function i18n() {
		load_plugin_textdomain( $this->slug, false, plugin_basename( dirname( __FILE__ ) ) . '/languages/' );
	}


	/**
	 * Upgrades DB
	 * Fires on admin init to support SVN
	 */
	function upgrade_check() {

		if ( $this->options->db_version == $this->version )
			return;

		$this->upgrade( $this->options->db_version, $this->version );

		do_action( $this->prefix . 'upgrade', $this->version, $this->options->db_version );

		$this->options->db_version = $this->version;

	}


	/**
	 * Upgrade DB to latest version
	 * @param int $from version comming from
	 * @param int $to version going to
	 * @TODO MIGRATE OLD UPGRADE PROCEDURE
	 */
	function upgrade( $from , $ti ) {
		
		//array of option conversions in the form of from => to
		$map = array( 
			'js_calls' => 'callback',
			'image' => 'img', 
			'text' => 'msgText',
			'dontetext' => 'finishedMsg',
			'content_selector' => 'contentSelector',
			'post_selector' => 'itemSelector',
			'nav_selector' => 'navSelector',
			'next_selector' => 'nextSelector',
		);
		
		$old = get_options( 'infscr_options' );
		$new = array();
		
		foreach ( $map as $from => $to ) {
			
			if ( !isset( $old[ 'infscr_' . $from ] ) )
				continue;
			
			$new[ $to ] = $old[ 'infscr_' . $from ];
			
		}
		
		$this->options->set_options( $new );
		delete_option( 'infscr_options' );
	
	}


}


$infinite_scroll = new Infinite_Scroll();