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
 * Reports.
 *
 * @package    mod_collaborate
 * @copyright  202 Richard Jones richardnz@outlook.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 * @see https://github.com/moodlehq/moodle-mod_simplemod
 * @see https://github.com/justinhunt/moodle-mod_simplemod
 */

namespace mod_collaborate\output;

use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Create a new student instructions renderable object.
 */
class reports implements renderable, templatable {

    protected $collaborate;
    protected $id;

    public function __construct($collaborate, $id) {
        $this->collaborate = $collaborate;
        $this->id = $id;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {

        $data = new stdClass();
        $data->title = $this->collaborate->title;
        $data->submissions = self::get_submission_records($this->collaborate->id);
        $data->headers = self::get_submission_record_headers();

        // The tabs.
        $reports = new moodle_url('/mod/collaborate/reports.php', ['cid' => $this->collaborate->id]);
        $view = new moodle_url('/mod/collaborate/view.php', ['id' => $this->id]);
        $data->url_reports = $reports->out(false);
        $data->url_view = $view->out(false);

        return $data;
    }

    /**
     * Return an array of records from the 'collaborate_submissions' table with
     * extra information from the 'user' table.
     *
     * @param int $cid our collaborate instance id.
     *
     * @return array An array of records.
     */
    protected function get_submission_records($cid) {
        global $DB;

        $records = $DB->get_records('collaborate_submissions', ['collaborateid' => $cid]);
        $submissions = array();

        // Prepare a table of records.
        foreach ($records as $record) {
            $data = array();
            $data['id'] = $record->id;
            $data['title'] = $this->collaborate->title;
            
           // Format the submission to grab text only.
            $submission = \format_string($record->submission);
            $submission = \strip_tags($submission);
            $data['submission'] = $submission;
            
            // Could also have used SQL and a JOIN here.  Better for large tables, probably.
            $user = $DB->get_record('user', ['id' => $record->userid], '*', MUST_EXIST);
            $data['firstname'] = $user->firstname;
            $data['lastname'] = $user->lastname;
            $data['grade'] = $record->grade;
            $submissions[] = $data;
        }

        return $submissions;
    }

     /**
     * Set the headers to match the record query and required report fields.
     *
     * @return string array of report column headers.
     */
    public static function get_submission_record_headers() {
        return [
            get_string('id', 'mod_collaborate'),
            get_string('reporttitle', 'mod_collaborate'),
            get_string('submission','mod_collaborate'),
            get_string('firstname', 'core'),
            get_string('lastname', 'core'),
            get_string('grade', 'core_grades')
        ];
    }
}
