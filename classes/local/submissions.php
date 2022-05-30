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
 * Class for handling student submissions.
 *
 * @package   mod_collaborate
 * @copyright 2018 Richard Jones https://richardnz.net
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
namespace mod_collaborate\local;
use \mod_collaborate\local\collaborate_editor;
use \mod_collaborate\local\debugging;
use \mod_collaborate\local\submission_form;

defined('MOODLE_INTERNAL') || die();

class submissions {
    /**
     * Add a submission record to the DB.
     *
     * @param object $data - The data to add.
     * @param object $context - Our module context.
     * @param int $cid Our collaborate instance id.
     *
     * @return int $id - The id of the inserted record.
     */
    public static function save_submission($data, $context, $cid, $page) {
        global $DB, $USER;

        $exists = self::get_submission($cid, $USER->id, $page);
        if ($exists) {
            $data->id = $exists->id;
            $data->timemodified = time();
        } else {
            // Insert a dummy record and get the id.
            $data->timecreated = time();
            $data->timemodified = 0;
            $data->collaborateid = $cid;
            $data->userid = $USER->id;
            $data->page = $page;
            $data->submission = ' ';
            $data->submissionformat = FORMAT_HTML;
            $dataid = $DB->insert_record('collaborate_submissions', $data);
            $data->id = $dataid;
        }

        $options = collaborate_editor::get_editor_options($context);

        // Massage the data into a form for saving.
        $data = file_postupdate_standard_editor(
            $data,
            'submission',
            $options,
            $context,
            'mod_collaborate',
            'submission',
            $data->id
        );

        // Update the record with full editor data.
        $DB->update_record('collaborate_submissions', $data);

        return $data->id;
    }

    /**
     * Retrieve a submission record from the DB.
     *
     * @param int $cid Our collaborate instance id.
     * @param int $userid The user making the submission.
     * @param int $page The page identifier (a or b).
     * @return object Representing the record or null if it doesn't exist.
     */
    public static function get_submission($cid, $userid, $page) {
        global $DB;
        return $DB->get_record('collaborate_submissions', ['collaborateid' => $cid, 'userid' => $userid, 'page' => $page], '*', IGNORE_MISSING);
    }

    /**
     * Retrieve a submission record for grading.
     *
     * @param object $collaborate Our collaborate instance.
     * @param int $sid The submission id.
     * @return object $data The data required for the grading form.
     */
    public static function get_submission_to_grade($collaborate, $sid) {
        global $DB;

        $record = $DB->get_record('collaborate_submissions', ['id' => $sid], '*', MUST_EXIST);
        $data = new \stdClass();
        $data->title = $collaborate->title;
        $data->submission = $record->submission;

        $user = $DB->get_record('user', ['id' => $record->userid], '*', MUST_EXIST);
        $data->name = $user->firstname.' '.$user->lastname;
        $data->grade = (is_null($record->grade)) ? '-' : $record->grade;  // So that '-' is shown when first not graded.

        return $data;
    }

    /**
     * Update a submission grade.
     *
     * @param int $sid The submission id.
     * @param int $grade The submission grade.
     * @return none.
     */

    public static function update_grade($sid, $grade) {
        global $DB;
        $DB->set_field('collaborate_submissions', 'grade', $grade, ['id' => $sid]);
    }
}
