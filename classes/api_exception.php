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
 * Exception class for Zoom API errors.
 *
 * @package tool_zoomapi
 * @copyright 2026 Jonathan Champ
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_zoomapi;

use moodle_exception;

/**
 * API exception class.
 */
class api_exception extends moodle_exception {
    /**
     * Response.
     * @var string
     */
    public $response = null;

    /**
     * Error code.
     * @var int
     */
    public $zoomerrorcode = null;

    /**
     * The name of the string.
     * @var string
     */
    public $errorcode = 'error:api';

    /**
     * The name of the module.
     * @var string
     */
    public $module = 'tool_zoomapi';

    /**
     * Constructor
     *
     * @param string $response Response body.
     * @param int $zoomerrorcode Response error code.
     */
    public function __construct($response, $zoomerrorcode) {
        $this->response = $response;
        $this->zoomerrorcode = $zoomerrorcode;

        parent::__construct($this->errorcode, $this->module, '', $response);
    }
}
