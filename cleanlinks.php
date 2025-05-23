<?php
/**
 * Plugin Name: CleanLinks
 * Plugin URI: https://github.com/mehul0810/cleanlinks
 * Description: The most robust, flexible, and intuitive way to build your brand using link building strategy.
 * Author: Mehul Gohil
 * Author URI: https://mehulgohil.com/
 * Version: 1.0.0
 * Requires at least: 5.5
 * Requires PHP: 7.4
 * Text Domain: cleanlinks
 * Domain Path: /languages
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * CleanLinks is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * CleanLinks is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with CleanLinks. If not, see <https://www.gnu.org/licenses/>.
 *
 * A Tribute to Open Source:
 *
 * "Open source software is software that can be freely used, changed, and shared (in modified or unmodified form) by
 * anyone. Open source software is made by many people, and distributed under licenses that comply with the Open Source
 * Definition."
 *
 * -- The Open Source Initiative
 *
 * CleanLinks is a tribute to the spirit and philosophy of Open Source. We gladly embrace the Open Source
 * philosophy both in how Give itself was developed, and how we hope to see others build more from our code base.
 *
 * CleanLinks would not have been possible without the tireless efforts of WordPress and the surrounding Open Source projects
 * and their talented developers. Thank you all for your contribution to WordPress.
 *
 */

namespace MG\CleanLinks;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load Constants.
require_once __DIR__ . '/config/constants.php';

// Automatically loads files used throughout the plugin.
require_once CLEANLINKS_PLUGIN_DIR . 'vendor/autoload.php';

// Initialize the plugin.
$cleanlinks_plugin = new Plugin();
$cleanlinks_plugin->register();
