<?php
// This file is part of the tool_zoomapi plugin for Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Settings for tool_zoomapi
 *
 * @package tool_zoomapi
 * @copyright 2026 Jonathan Champ
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_zoomapi\helper;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('tool_zoomapi', new lang_string('pluginname', 'tool_zoomapi'));
    $ADMIN->add('tools', $settings);
}

if ($hassiteconfig && $ADMIN->fulltree) {
    // API settings section.
    $settings->add(new admin_setting_heading('tool_zoomapi_api', new lang_string('apisettings', 'tool_zoomapi'), ''));

    $settings->add(new admin_setting_configtext(
        'tool_zoomapi/accountid',
        new lang_string('accountid', 'tool_zoomapi'),
        new lang_string('accountid_desc', 'tool_zoomapi'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'tool_zoomapi/clientid',
        new lang_string('clientid', 'tool_zoomapi'),
        new lang_string('clientid_desc', 'tool_zoomapi'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_encryptedpassword(
        'tool_zoomapi/clientsecret',
        new lang_string('clientsecret', 'tool_zoomapi'),
        new lang_string('clientsecret_desc', 'tool_zoomapi'),
    ));

    $apioptions = [];
    foreach (helper::OAUTH_HOSTS as $api => $url) {
        $apioptions[$api] = new lang_string('api_' . $api, 'tool_zoomapi');
    }

    $settings->add(new admin_setting_configselect(
        'tool_zoomapi/api',
        new lang_string('api', 'tool_zoomapi'),
        new lang_string('api_desc', 'tool_zoomapi'),
        helper::API_GLOBAL,
        $apioptions
    ));
}
