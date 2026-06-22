<?php
/**
 * Plugin Name: Simplified Links
 * Plugin URI: https://simplifiedwp.com/plugins/simplified-links
 * Description: The most robust, flexible, and intuitive way to build your brand using link building strategy.
 * Author: SimplifiedWP
 * Author URI: https://simplifiedwp.com/
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 8.0
 * Text Domain: simplified-links
 * Domain Path: /languages
 *
 * Simplified Links is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Simplified Links is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Simplified Links. If not, see <https://www.gnu.org/licenses/>.
 *
 * A Tribute to Open Source:
 *
 * "Open source software is software that can be freely used, changed, and shared (in modified or unmodified form) by
 * anyone. Open source software is made by many people, and distributed under licenses that comply with the Open Source
 * Definition."
 *
 * -- The Open Source Initiative
 *
 * Simplified Links is a tribute to the spirit and philosophy of Open Source. We at SimplifiedWP gladly embrace the Open Source
 * philosophy both in how Give itself was developed, and how we hope to see others build more from our code base.
 *
 * Simplified Links would not have been possible without the tireless efforts of WordPress and the surrounding Open Source projects
 * and their talented developers. Thank you all for your contribution to WordPress.
 *
 * - The SimplifiedWP Team
 */

namespace SimplifiedWP\Links;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load Constants.
require_once __DIR__ . '/config/constants.php';

// Automatically loads files used throughout the plugin.
require_once SIMPLIFIED_LINKS_PLUGIN_DIR . 'vendor/autoload.php';

// Initialize the plugin.
$simplified_links_plugin = new Plugin();
$simplified_links_plugin->register();
