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
 * Language strings for tool_zoomapi
 *
 * @package tool_zoomapi
 * @copyright 2026 Jonathan Champ
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['accountid'] = 'Account ID';
$string['accountid_desc'] = 'Account ID from your Zoom Marketplace App';
$string['api'] = 'Zoom API';
$string['api_desc'] = 'Choose which Zoom API to use. The global API should work for most users. Other API endpoints are only for accounts in specific provisioning environments. If you are unsure, use the global API.';
$string['api_global'] = 'Global (default)';
$string['api_gov'] = 'Zoom for Government';
$string['apisettings'] = 'API Settings';
$string['clientid'] = 'Client ID';
$string['clientid_desc'] = 'Client ID from your Zoom Marketplace App';
$string['clientsecret'] = 'Client Secret';
$string['clientsecret_desc'] = 'Client Secret from your Zoom Marketplace App';
$string['error:api'] = 'Zoom API request failed: {$a}';
$string['error:apiurl'] = 'Zoom OAuth response did not provide the API URL';
$string['error:notconfigured'] = 'Missing required Zoom API credentials';
$string['error:requiredscopes'] = 'The Zoom OAuth app is missing one or more required OAuth scopes: {$a}';
$string['error:token'] = 'Error obtaining OAuth access token from Zoom';
$string['error:usernotfound'] = 'Could not find a Zoom account with the email address "{$a}". Please ensure your Moodle email matches your Zoom account email.';
$string['pluginname'] = 'Zoom API';
$string['pluginname_help'] = 'A reusable library for Zoom API integrations.';
$string['privacy:metadata:user_mappings'] = 'Maps Moodle user accounts to their corresponding Zoom user accounts.';
$string['privacy:metadata:user_mappings:timemodified'] = 'The timestamp of when the mapping was last updated.';
$string['privacy:metadata:user_mappings:userid'] = 'The ID of the Moodle user account.';
$string['privacy:metadata:user_mappings:zoom_email'] = 'The Zoom account email address.';
$string['privacy:metadata:user_mappings:zoom_userid'] = 'The Zoom user ID linked to the Moodle user.';
