<?php
/*
Plugin Name: BBU's EzineArticles Search API Widget
Version: 1.0
Plugin URI: http://blogbuildingu.com/software/ezinearticles-search-api-widget
Author: Hendry Lee
Author URI: http://blogbuildingu.com
Description: BBU's EzineArticles Search API Widget allows you to search EzineArticles.com using its API calls and displays results in a sidebar widget. This plugin automatically updates search results and uses your EzineArticles API quota very efficiently.
*/

/*  
Copyright 2009 Hendry Lee <hendry.lee@gmail.com> and Blog Building University.
Licensed under GPLv2, see file LICENSE in the package for details.
*/

require_once( 'bbu_ezasearchapi.php' );

if ( class_exists( 'BBU_EzaSearchApi' ) )
	$bbuEza = new BBU_EzaSearchApi();

// Actions and filters
if ( isset( $bbuEza ) ) {
	// Upon activation and installation
	register_activation_hook( __FILE__, array( &$bbuEza, 'bbuEzaInit' ) );
	// Schedule data update per hour
	register_activation_hook( __FILE__, array( &$bbuEza, 'bbuEzaSchedule' ) );
	add_action( 'bbuEzaUpdateData', array( &$bbuEza, 'bbuEzaFetchData' ) );
	
	// Initialize widgets
	add_action( 'widgets_init', array( &$bbuEza, 'bbuEzaWidgetInit' ) );
	
	// Add options menu in the Dashboard
	add_action( 'admin_menu', array( &$bbuEza, 'bbuEzaAdminMenu' ) );
	
	// Upon deactivation
	register_deactivation_hook( __FILE__, array( &$bbuEza, 'bbuEzaDeactivate' ) );
	// Upon removal (uninstall) - disabled
	// register_uninstall_hook( __FILE__, array( &$bbuEza, 'bbuEzaUninstall' );
}

?>