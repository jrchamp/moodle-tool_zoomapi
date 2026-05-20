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
 * Unit tests for tool_zoomapi
 *
 * @package tool_zoomapi
 * @copyright 2026 Jonathan Champ
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_zoomapi;

use advanced_testcase;
use core\di;
use core\encryption;
use core\http_client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Unit tests for tool_zoomapi
 * @covers \tool_zoomapi\api
 * @covers \tool_zoomapi\helper
 */
final class api_test extends advanced_testcase {
    /**
     * @var \stdclass Course
     */
    private $course;

    /**
     * @var \stdclass Teacher
     */
    private $teacher;

    /**
     * @var \stdclass Student
     */
    private $student;

    /**
     * Create users if needed.
     */
    private function create_users(): void {
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
    }

    /**
     * Configure plugin when needed.
     */
    private function configure_plugin(): void {
        $this->resetAfterTest();

        set_config('accountid', 'test-account', 'tool_zoomapi');
        set_config('clientid', 'test-key', 'tool_zoomapi');
        set_config('clientsecret', encryption::encrypt('test-secret'), 'tool_zoomapi');
    }

    /**
     * Pre-set responses from the http_client.
     *
     * @param array $responses Array of mock Responses.
     */
    public function set_responses($responses) {
        $setup = [
            'token' => new Response(
                200,
                [],
                json_encode(
                    [
                        'access_token' => 'test-token',
                        'expires_in' => 3600,
                        'api_url' => 'https://localhost/api',
                        'scope' => implode(
                            ' ',
                            [
                                'user:read:admin',
                            ]
                        ),
                    ]
                )
            ),
        ];
        $handler = new MockHandler($setup + $responses);
        $client = new http_client(['handler' => HandlerStack::create($handler)]);
        di::set(http_client::class, $client);
    }

    /**
     * Test that get_user returns data in the expected format.
     */
    public function test_get_user(): void {
        $zoomteacherid = 'teacheruserid';
        $zoomstudentid = 'studentuserid';

        $this->create_users();
        $this->configure_plugin();
        $this->set_responses(
            [
                new Response(
                    200,
                    [],
                    json_encode(
                        [
                            'id' => $zoomstudentid,
                            'email' => $this->student->email,
                        ]
                    )
                ),
                new Response(
                    200,
                    [],
                    json_encode(
                        [
                            'id' => $zoomteacherid,
                            'email' => $this->teacher->email,
                        ]
                    )
                ),
            ]
        );

		$zoomstudent = helper::get_user(helper::get_api_identifier($this->student));
        $this->assertEquals($zoomstudentid, $zoomstudent['id']);
        $this->assertEquals($this->student->email, $zoomstudent['email']);

		$zoomteacher = helper::get_user(helper::get_api_identifier($this->teacher));
        $this->assertEquals($zoomteacherid, $zoomteacher['id']);
        $this->assertEquals($this->teacher->email, $zoomteacher['email']);
    }
}
