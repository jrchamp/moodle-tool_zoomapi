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
use core\clock;
use core\di;
use moodle_exception;
use stdClass;

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
            $zoomuserid = null;
        }

        return $zoomuserid;
    }

    /**
     * Get the current user's Zoom ID.
     *
     * @throws moodle_exception When no user is found.
     * @return string
     */
    public static function get_userid() {
        global $USER;

        return self::get_zoom_userid($USER);
    }

    /**
     * Get a Moodle user's Zoom user ID.
     *
     * Resolves via the Zoom API (by email) and stores the result in the
     * mapping table for future reference.
     *
     * @param stdClass $user Moodle user object.
     * @throws moodle_exception When no Zoom account is found.
     * @return string Zoom user ID.
     */
    public static function get_zoom_userid(stdClass $user): string {
        $apiidentifier = self::get_api_identifier($user);
        $zoomuser = self::get_user($apiidentifier);

        if (empty($zoomuser) || empty($zoomuser['id'])) {
            throw new moodle_exception('error:usernotfound', 'tool_zoomapi', '', $apiidentifier);
        }

        self::store_user_mapping($user->id, $zoomuser['id'], $zoomuser['email'] ?? '');

        return $zoomuser['id'];
    }

    /**
     * Get a Moodle user ID from a Zoom user ID.
     *
     * Resolves via the Zoom API (email lookup) and stores the result in the
     * mapping table for future reference.
     *
     * @param string $zoomid Zoom user ID.
     * @return int|null Moodle user ID, or null if not found.
     */
    public static function get_moodle_userid(string $zoomid): ?int {
        global $DB;

        $zoomuser = self::get_user($zoomid);
        if (empty($zoomuser) || empty($zoomuser['email'])) {
            return null;
        }

        $moodleuser = $DB->get_record('user', ['email' => $zoomuser['email'], 'deleted' => 0]);
        if (!$moodleuser) {
            return null;
        }

        self::store_user_mapping($moodleuser->id, $zoomid, $zoomuser['email']);

        return (int) $moodleuser->id;
    }

    /**
     * Get a user.
     *
     * @param string $identifier User identifier for the Zoom API.
     * @return array|false User array if found, otherwise false.
     */
    public static function get_user($identifier) {
        global $DB;

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

                if (strpos($identifier, '@') !== false) {
                    $moodleuser = $DB->get_record('user', ['email' => $identifier, 'deleted' => 0]);
                    if ($moodleuser) {
                        self::store_user_mapping($moodleuser->id, $user['id'], $user['email'] ?? '');
                    }
                }
            }
        }

        return $user;
    }

    /**
     * Persist a Moodle-to-Zoom user mapping.
     *
     * @param int $userid Moodle user ID.
     * @param string $zoomuserid Zoom user ID.
     * @param string $zoomemail Zoom account email.
     */
    private static function store_user_mapping(int $userid, string $zoomuserid, string $zoomemail): void {
        global $DB;

        $record = $DB->get_record('tool_zoomapi_user_mappings', ['userid' => $userid]);
        if ($record && $record->zoom_userid === $zoomuserid && $record->zoom_email === $zoomemail) {
            return;
        }

        $mapping = (object) [
            'userid' => $userid,
            'zoom_userid' => $zoomuserid,
            'zoom_email' => $zoomemail,
            'timemodified' => di::get(clock::class)->time(),
        ];

        if ($record) {
            $mapping->id = $record->id;
            $DB->update_record('tool_zoomapi_user_mappings', $mapping);
            return;
        }

        $DB->insert_record('tool_zoomapi_user_mappings', $mapping);
    }
}
