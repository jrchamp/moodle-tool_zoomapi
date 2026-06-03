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
 * Database upgrade steps.
 *
 * @package tool_zoomapi
 * @copyright 2026 Jonathan Champ
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Run upgrade steps added since the provided old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tool_zoomapi_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026060300) {
        $table = new xmldb_table('tool_zoomapi_user_mappings');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_field('zoom_userid', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null);
        $table->add_field('zoom_email', XMLDB_TYPE_CHAR, '255', null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN_UNIQUE, ['userid'], 'user', ['id']);

        $table->add_index('idx-zoom-userid', XMLDB_INDEX_UNIQUE, ['zoom_userid']);

        $dbman->create_table($table);

        upgrade_plugin_savepoint(true, 2026060300, 'tool', 'zoomapi');
    }

    return true;
}
