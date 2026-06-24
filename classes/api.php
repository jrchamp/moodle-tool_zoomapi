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
 * Handles API calls to Zoom REST API.
 *
 * @package tool_zoomapi
 * @copyright 2026 Jonathan Champ
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_zoomapi;

use cache;
use core\clock;
use core\di;
use core\encryption;
use core\http_client;
use moodle_exception;

/**
 * API class.
 */
class api {
    /**
     * API calls: maximum number of retries.
     * @var int
     */
    public const MAX_RETRIES = 5;

    /** @var int Zoom API error code: user not found. */
    public const ERROR_USER_NOT_FOUND = 1001;

    /** @var int Zoom API error code: invalid user. */
    public const ERROR_INVALID_USER = 1120;

    /**
     * Account ID
     * @var string
     */
    private $accountid;

    /**
     * Client ID
     * @var string
     */
    private $clientid;

    /**
     * Client secret
     * @var string
     */
    private $clientsecret;

    /**
     * API base URL.
     * @var string
     */
    private $apiurl;

    /**
     * API token URL.
     * @var string
     */
    private $tokenurl;

    /**
     * Number of retries already made by make_call.
     * @var int
     */
    private $makecallretries = 0;

    /**
     * Granted scopes.
     * @var array
     */
    private $scopes;

    /**
     * Constructor.
     *
     * @param http_client $client HTTP Client.
     */
    public function __construct(
        /**
         * @var http_client
         */
        protected readonly http_client $client
    ) {
        $config = get_config('tool_zoomapi');

        $this->accountid = $config->accountid ?? '';
        $this->clientid = $config->clientid ?? '';
        $this->clientsecret = '';
        $this->tokenurl = helper::get_token_url();

        if (!empty($config->clientsecret)) {
            $this->clientsecret = encryption::decrypt($config->clientsecret);
        }

        if (empty($this->accountid) || empty($this->clientid) || empty($this->clientsecret)) {
            throw new moodle_exception('error:notconfigured', 'tool_zoomapi');
        }
    }

    /**
     * Get the singular, reusable API class instance.
     *
     * @return api
     */
    public static function instance() {
        return \core\di::get(static::class);
    }

    /**
     * Create token.
     */
    private function create_token() {
        $data = [
            'grant_type' => 'account_credentials',
            'account_id' => $this->accountid,
        ];
        return $this->make_call('post', $this->tokenurl, $data);
    }

    /**
     * Return a working token.
     *
     * @throws moodle_exception
     * @return string Access token
     */
    protected function get_access_token() {
        $cache = cache::make('tool_zoomapi', 'token');

        $token = $cache->get('accesstoken');
        $expires = $cache->get('expires');
        $now = di::get(clock::class)->time();

        if (empty($token) || empty($expires) || $now >= $expires) {
            $response = $this->create_token();

            if (empty($response['access_token'])) {
                throw new moodle_exception('error:token', 'tool_zoomapi');
            }

            $apiurl = $response['api_url'];
            $scopes = explode(' ', $response['scope']);

            // Keep essential information.
            $this->apiurl = $apiurl;
            $this->scopes = $scopes;

            if (empty($apiurl)) {
                throw new moodle_exception('error:apiurl', 'tool_zoomapi');
            }

            $scopetype = $this->get_scope_type($scopes);
            $missingscopes = array_diff(static::required_scopes($scopetype), $scopes);

            if (!empty($missingscopes)) {
                $missingscopes = implode(', ', $missingscopes);
                throw new moodle_exception('error:requiredscopes', 'tool_zoomapi', $missingscopes);
            }

            $token = $response['access_token'];
            $expires = $now + ($response['expires_in'] ?? 3600) - 300;

            $cache->set_many([
                'accesstoken' => $token,
                'apiurl' => $apiurl,
                'expires' => $expires,
                'scopes' => $scopes,
            ]);
        } else {
            // Preload essential information from cache.
            $this->apiurl = $cache->get('apiurl');
            $this->scopes = $cache->get('scopes');
        }

        // Use API v2.
        $this->apiurl .= '/v2/';

        return $token;
    }

    /**
     * Return Behat API URL.
     *
     * @return string Access token
     */
    protected function get_behat_url() {
        global $CFG;

        return $CFG->wwwroot . '/admin/tool/zoomapi/tests/behat/fixtures/mock_api.php';
    }

    /**
     * Checks for the type of scope (classic or granular) of the user.
     *
     * @param array $scopes
     * @return string scope type
     */
    private function get_scope_type($scopes) {
        return in_array('user:read:admin', $scopes, true) ? 'classic' : 'granular';
    }

    /**
     * Gets a user.
     *
     * @param string|int $identifier The user's email or the user's ID per Zoom API.
     * @return array|false If user is found, returns the User object. Otherwise, returns false.
     */
    public function get_user($identifier) {
        $founduser = false;

        // Classic: user:read:admin.
        // Granular: user:read:user:admin.
        $url = 'users/' . $identifier;

        try {
            $founduser = $this->make_call('get', $url);
        } catch (api_exception $error) {
            if ($error->zoomerrorcode === self::ERROR_USER_NOT_FOUND || $error->zoomerrorcode === self::ERROR_INVALID_USER) {
                return false;
            }

            throw $error;
        }

        return $founduser;
    }

    /**
     * Has one of the required scopes been granted?
     *
     * @param array $scopes Scopes.
     * @throws moodle_exception
     * @return bool
     */
    public function has_scope($scopes) {
        if (!isset($this->scopes)) {
            $this->get_access_token();
        }

        if (CLI_SCRIPT) {
            mtrace('checking has_scope(' . implode(' || ', $scopes) . ')');
        }

        $matchingscopes = \array_intersect($scopes, $this->scopes);
        return !empty($matchingscopes);
    }

    /**
     * Make an API call.
     *
     * @param string $method The HTTP method to use.
     * @param string $path The path to append to the API URL
     * @param array $data The data to attach to the call.
     * @return array The call's result in JSON format.
     * @throws moodle_exception Moodle exception is thrown for API errors.
     */
    protected function make_call($method, $path, $data = []) {
        $headers = [
            'Accept' => 'application/json',
        ];

        $options = [
            'http_errors' => false,
            // Force HTTP/1.1 to avoid HTTP/2 "stream not closed" issue.
            'version' => 1.1,
            // We should never need to wait longer than 5 seconds to connect.
            'connect_timeout' => 5,
        ];

        if ($path === $this->tokenurl) {
            $headers['Authorization'] = 'Basic ' . base64_encode($this->clientid . ':' . $this->clientsecret);

            $url = $this->tokenurl;

            if (defined('BEHAT_SITE_RUNNING')) {
                $data['_path'] = 'oauth/token';
            }

            $options['form_params'] = $data;
        } else {
            $headers['Authorization'] = 'Bearer ' . $this->get_access_token();

            $url = $this->apiurl;

            if (defined('BEHAT_SITE_RUNNING')) {
                $data['_path'] = $path;
            } else {
                $url .= $path;
            }

            if (!empty($data)) {
                if ($method === 'get' || $method === 'delete') {
                    // Use URL format.
                    $options['query'] = $data;
                } else {
                    // Use JSON format.
                    $options['json'] = $data;
                }
            }
        }

        $options['headers'] = $headers;

        if (defined('BEHAT_SITE_RUNNING')) {
            $url = $this->get_behat_url();
            $options['verify'] = false;
        }

        try {
            $result = $this->client->$method($url, $options);
        } catch (Exception $e) {
            throw new moodle_exception('error:api', 'tool_zoomapi', '', $e->getMessage());
        }

        $rawresponse = $result->getBody()->getContents();
        $response = json_decode($rawresponse, true);

        $httpstatus = (int) $result->getStatusCode();

        if ($httpstatus >= 400) {
            $message = $response['message'] ?? $response['reason'];
            $code = $response['code'] ?? $httpstatus;

            if (!empty($response['errors'])) {
                foreach ($response['errors'] as $error) {
                    $message .= ' ' . $error['message'];
                }
            }

            if ($httpstatus === 429) {
                if ($this->makecallretries < self::MAX_RETRIES) {
                    $retry = true;

                    $header = $result->getHeaders();
                    // Header can have mixed case, normalize it.
                    $header = array_change_key_case($header, CASE_LOWER);

                    // Default to 1 second for max requests per second limit.
                    $timediff = 1;

                    // Check if we hit the max requests per minute.
                    if (
                        isset($header['x-ratelimit-type']) &&
                        $header['x-ratelimit-type'] == 'QPS' &&
                        strpos($path, 'metrics') !== false
                    ) {
                        $timediff = 60; // Try the next minute.
                    } else if (isset($header['retry-after'])) {
                        $retryafter = strtotime($header['retry-after']);
                        if ($retryafter > 1) {
                            $message .= '; retry after ' . $retryafter;
                            $timediff = $retryafter - di::get(clock::class)->time();
                        }

                        if (!empty($header['x-ratelimit-remaining'])) {
                            // When running CLI we might want to know how many calls remaining.
                            debugging('x-ratelimit-remaining = ' . $header['x-ratelimit-remaining']);
                        } else {
                            // If we have no API calls remaining, throw an exception.
                            $retry = false;
                        }
                    }

                    if ($retry) {
                        debugging('HTTP 429, will retry after ' . $timediff . ' seconds');
                        if ($timediff > 0) {
                            sleep($timediff);
                        }

                        $this->makecallretries += 1;
                        return $this->make_call($method, $path, $data);
                    }
                }
            }

            if ($message) {
                throw new api_exception($message . ' (' . $code . ')', $code);
            } else {
                throw new moodle_exception('error:api', 'tool_zoomapi', '', "HTTP Status $httpstatus");
            }
        }

        $this->makecallretries = 0;

        return $response;
    }

    /**
     * Make an series of API calls to handle paged results.
     *
     * @param string $url The URL to append to the API URL
     * @param array $data The data to attach to the call.
     * @param string $field The name of the array of the data to get.
     * @return array The retrieved data.
     * @see make_call()
     */
    protected function make_paginated_call($url, $data, $field) {
        $aggregatedata = [];
        $data['page_size'] = helper::MAX_PAGE_SIZE;

        do {
            $moredata = false;
            $response = $this->make_call('get', $url, $data);

            if ($response) {
                $aggregatedata[] = $response[$field];
                if (!empty($response['next_page_token'])) {
                    $data['next_page_token'] = $response['next_page_token'];
                    $moredata = true;
                } else if (!empty($response['page_number']) && $response['page_number'] < $response['page_count']) {
                    $data['page_number'] = $response['page_number'] + 1;
                    $moredata = true;
                }
            }
        } while ($moredata);

        return array_merge(...$aggregatedata);
    }

    /**
     * Get the minimum set of required scopes.
     *
     * @param string $type Scope type is either granular or classic.
     * @return string[]
     */
    public static function required_scopes($type = '') {
        $requiredscopes = [
            'granular' => [
                'user:read:user:admin',
            ],
            'classic' => [
                'user:read:admin',
            ],
        ];

        if (!isset($requiredscopes[$type])) {
            $type = 'granular';
        }

        return $requiredscopes[$type];
    }
}
