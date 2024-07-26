<?php
/**
 * Plugin Name: Property Listing Manager
 * Plugin URI: https://RealEstate-Huntsville.com
 * Description: Manages property listings, including retrieval of listing counts and generation of location-based buttons.
 * Version: 1.0
 * Author: David E. England, PhD
 * Author URI: mailto:DavidEEngland@Outlook.com
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include the PropertyListingManager class
require_once plugin_dir_path(__FILE__) . 'PropertyListingManager.php';

// Activation hook
function plm_activate() {
    // Activation code here
}
register_activation_hook(__FILE__, 'plm_activate');

// Deactivation hook
function plm_deactivate() {
    // Deactivation code here
}
register_deactivation_hook(__FILE__, 'plm_deactivate');

// Implement shortcode or other hooks to use PropertyListingManager