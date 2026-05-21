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
 * Convenient functions and constants for tool_zoomapi
 *
 * @package tool_zoomapi
 * @copyright 2026 Jonathan Champ
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_zoomapi;

use cache;
use moodle_exception;

/**
 * Helper class for tool_zoomapi.
 */
final class helper {
    /**
     * @var string
     */
    public const API_GLOBAL = 'global';

    /**
     * @var string
     */
    public const API_ZFG = 'gov';

    /**
     * @var array
     */
    public const OAUTH_HOSTS = [
        self::API_GLOBAL => 'zoom.us',
        self::API_ZFG => 'zoomgov.com',
    ];

    /**
     * @var int
     */
    public const MAX_PAGE_SIZE = 300;

    /**
     * Prevent instanciation, all methods are static.
     */
    private function __construct() {
    }

    /**
     * Get the configured Zoom API URL.
     *
     * @return string The API URL.
     */
    public static function get_token_url() {
        // Get the API endpoint setting.
        $apiendpoint = get_config('tool_zoomapi', 'apiendpoint');

        // If not found, default to the global endpoint.
        if (!isset(self::OAUTH_HOSTS[$apiendpoint])) {
            $apiendpoint = self::API_GLOBAL;
        }

        // Return API URL.
        return 'https://' . self::OAUTH_HOSTS[$apiendpoint] . '/oauth/token';
    }

    /**
     * Get the Zoom API identifier.
     *
     * @param object $user The user object
     * @return string API identifier
     */
    public static function get_api_identifier($user) {
        return strtolower($user->email);
    }

    /**
     * Get the current user's Zoom ID (but ignore exceptions).
     *
     * @return ?string
     */
    public static function get_userid_optional() {
        try {
            $zoomuserid = self::get_userid();
        } catch (moodle_exception $e) {
            // Ignore exceptions.
            $zoomuserid = null;
        }

        return $zoomuserid;
    }

    /**
     * Get the current user's Zoom ID.
     *
     * @throws moodle_exception When not user is found.
     * @return string
     */
    public static function get_userid() {
        global $USER;

        $apiidentifier = self::get_api_identifier($USER);
        $zoomuser = self::get_user($apiidentifier);

        if ($zoomuser !== false && !empty($zoomuser['id'])) {
            $zoomuserid = $zoomuser['id'];
        }

        // No Zoom account was found, so throw an exception.
        if (empty($zoomuser)) {
            throw new moodle_exception('error:usernotfound', 'tool_zoomapi', '', $apiidentifier);
        }

        return $zoomuserid;
    }

    /**
     * Get a user.
     *
     * @param string $identifier User identifier for the Zoom API.
     * @return array|false User array if found, otherwise false.
     */
    public static function get_user($identifier) {
        $cache = cache::make('tool_zoomapi', 'users');

        if (empty($identifier)) {
            return false;
        }

        $user = $cache->get($identifier);

        if (empty($user)) {
            $response = api::instance()->get_user($identifier);

            if (!empty($response)) {
                $user = $response;

                $cache->set_many(
                    [
                        $user['id'] => $user,
                        strtolower($user['email']) => $user,
                    ]
                );
            }
        }

        return $user;
    }
}
