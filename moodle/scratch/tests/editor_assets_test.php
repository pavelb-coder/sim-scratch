<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Basic asset presence tests for mod_scratch.
 *
 * @package    mod_scratch
 * @copyright  2026 Scratch Code
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 */

namespace mod_scratch;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests that bundled editor assets are present.
 */
final class editor_assets_test extends \advanced_testcase {

    /**
     * Required editor files exist.
     */
    public function test_assets_present(): void {
        $base = \core_component::get_component_directory('mod_scratch') . '/assets/';
        $this->assertFileExists($base . 'editor.js');
        $this->assertFileExists($base . 'editor.css');
        $this->assertFileExists($base . 'vendor/react.production.min.js');
        $this->assertFileExists($base . 'vendor/react-dom.production.min.js');
        $this->assertFileExists($base . 'vendor/scratch-gui/dist/scratch-gui.js');
        $this->assertFileExists($base . 'vendor/scratch-gui/dist/chunks/fetch-worker.ca18555b9ac45d3fc5e1.js');
    }

    /**
     * The webpack public path is patched for Moodle.
     */
    public function test_public_path_patched(): void {
        $base = \core_component::get_component_directory('mod_scratch') . '/assets/';
        $gui = file_get_contents($base . 'vendor/scratch-gui/dist/scratch-gui.js');
        $this->assertStringContainsString('__scratchGuiPublicPath', $gui);
    }
}
