<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Simple debugging class
 *
 * @package    mod_simplemod.
 * @copyright  2019 Richard Jones richardnz@outlook.com
 * @copyright  2021 G J Barnard.
 * @author     G J Barnard - {@link http://moodle.org/user/profile.php?id=442195}.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_simplemod\local;

defined('MOODLE_INTERNAL') || die();

class debugging {
    public static function logit($message, $value) {
        error_log(print_r($message, true));
        error_log(print_r($value, true));
        try {
            throw new \Exception();
        } catch(\Exception $e) {
            error_log('Trace: '.$e->getTraceAsString().PHP_EOL);
        }
    }
}
