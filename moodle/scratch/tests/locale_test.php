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
 * Tests for the editor locale mapping used by the bundled Scratch editor.
 *
 * @package    mod_scratch
 * @copyright  2026 Scratch Code
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @covers     ::scratch_editor_locale
 */

namespace mod_scratch;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/scratch/lib.php');

/**
 * Locale mapping tests.
 */
final class locale_test extends \advanced_testcase {
    /**
     * Locales that the bundled Scratch GUI ships are passed through unchanged.
     */
    public function test_supported_locales(): void {
        $this->assertSame('ru', scratch_editor_locale('ru'));
        $this->assertSame('en', scratch_editor_locale('en'));
        $this->assertSame('de', scratch_editor_locale('de'));
        $this->assertSame('ja', scratch_editor_locale('ja'));
    }

    /**
     * Moodle uses underscores for regional variants, Scratch uses hyphens.
     */
    public function test_regional_variants(): void {
        $this->assertSame('pt-br', scratch_editor_locale('pt_br'));
        $this->assertSame('zh-cn', scratch_editor_locale('zh_cn'));
        $this->assertSame('en', scratch_editor_locale('en_us'));
    }

    /**
     * Variants the editor does not ship fall back to the base language.
     */
    public function test_unknown_regions_fall_back_to_base_language(): void {
        $this->assertSame('de', scratch_editor_locale('de_at'));
        $this->assertSame('es', scratch_editor_locale('es_mx'));
    }

    /**
     * Unknown languages fall back to English, exactly like the editor itself.
     */
    public function test_unknown_language_falls_back_to_english(): void {
        $this->assertSame('en', scratch_editor_locale('tlh'));
        $this->assertSame('en', scratch_editor_locale(''));
        $this->assertSame('en', scratch_editor_locale('   '));
    }

    /**
     * Case and surrounding whitespace are normalised.
     */
    public function test_normalisation(): void {
        $this->assertSame('ru', scratch_editor_locale('RU'));
        $this->assertSame('pt-br', scratch_editor_locale(' PT_BR '));
    }
}
