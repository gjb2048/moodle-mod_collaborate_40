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
 * Privacy Subsystem implementation for mod_collaborate.
 *
 * @package    mod_collaborate
 * Based upon work done for mod_forum by:
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_forum\privacy;

use \core_grades\component_gradeitem as gradeitem;
use \core_privacy\local\request\userlist;
use \core_privacy\local\request\approved_contextlist;
use \core_privacy\local\request\approved_userlist;
use \core_privacy\local\request\deletion_criteria;
use \core_privacy\local\request\writer;
use \core_privacy\local\request\helper as request_helper;
use \core_privacy\local\metadata\collection;
use \core_privacy\local\request\transform;
use \tool_dataprivacy\context_instance;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/grade/grading/lib.php');

/**
 * Implementation of the privacy subsystem plugin provider for the collaborate activity module.
 *
 * Based upon work done for mod_forum by:
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    // This plugin has data.
    \core_privacy\local\metadata\provider,

    // This plugin currently implements the original plugin\provider interface.
    \core_privacy\local\request\plugin\provider,

    // This plugin is capable of determining which users have data within it.
    \core_privacy\local\request\core_userlist_provider
{

    use subcontext_info;

    /**
     * Returns meta data about this system.
     *
     * @param   collection     $items The initialised collection to add items to.
     * @return  collection     A listing of user data stored through this system.
     */
    public static function get_metadata(collection $items) : collection {
        // The 'forum_discussions' table stores the metadata about each forum discussion.
        $items->add_database_table('forum_discussions', [
            'name' => 'privacy:metadata:forum_discussions:name',
            'userid' => 'privacy:metadata:forum_discussions:userid',
            'assessed' => 'privacy:metadata:forum_discussions:assessed',
            'timemodified' => 'privacy:metadata:forum_discussions:timemodified',
            'usermodified' => 'privacy:metadata:forum_discussions:usermodified',
        ], 'privacy:metadata:forum_discussions');

        return $items;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * In the case of forum, that is any forum where the user has made any post, rated any content, or has any preferences.
     *
     * @param   int         $userid     The user to search.
     * @return  contextlist $contextlist  The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : \core_privacy\local\request\contextlist {
        $contextlist = new \core_privacy\local\request\contextlist();

        $params = [
            'modname'       => 'collaborate',
            'contextlevel'  => CONTEXT_MODULE,
            'userid'        => $userid,
        ];

        // Submissions.
        $sql = "SELECT c.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {collaborate} c ON c.id = cm.instance
                  JOIN {collaborate_submissions} cs ON cs.collaborateid = c.id
                 WHERE cs.userid = :userid
        ";
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $params = [
            'instanceid'    => $context->instanceid,
            'modulename'    => 'collaborate',
        ];

        // Submissions.
        $sql = "SELECT cs.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {collaborate} c ON c.id = cm.instance
                  JOIN {collaborate_submissions} cs ON cs.collaborateid = c.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist)) {
            return;
        }

        $user = $contextlist->get_user();
        $userid = $user->id;

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);
        $params = $contextparams;

        // Submissions.
        $sql = "SELECT
                    ctx.id AS contextid,
                    cs.userid AS submitted
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {collaborate} c ON c.id = cm.instance
                  JOIN {collaborate_submissions} cs ON cs.collaborateid = c.id
                 WHERE (
                    cs.userid = :userid AND
                    ctx.id {$contextsql}
                )
        ";
        $params['userid'] = $userid;
        $submitted = $DB->get_records_sql_menu($sql, $params);

        $sql = "SELECT
                    ctx.id AS contextid,
                    c.*,
                    cm.id AS cmid
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {collaborate} c ON c.id = cm.instance
                 WHERE (
                    ctx.id {$contextsql}
                )
        ";
        $params += $contextparams;

        // Keep a mapping of collaborateid to contextid.
        $mappings = [];

        $collaborates = $DB->get_recordset_sql($sql, $params);
        foreach ($collaborates as $collaborate) {
            $mappings[$collaborate->id] = $collaborate->contextid;

            $context = \context::instance_by_id($mappings[$collaborate->id]);

            // Store the main collaborate data.
            $data = request_helper::get_context_data($context, $user);
            writer::with_context($context)->export_data([], $data);
            request_helper::export_context_files($context, $user);

            // Store relevant metadata about this collaborate instance.
            if (isset($submitted[$collaborate->contextid])) {
                static::export_submission_data($userid, $collaborate, $submitted[$collaborate->contextid]);
            }
        }
        $collaborates->close();
    }

    /**
     * Store all information about all discussions that we have detected this user to have access to.
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     * @param   array       $mappings A list of mappings from collaborateid => contextid.
     * @return  array       Which submissions had data written for them.
     */
    protected static function export_submission_data(int $userid, array $mappings) {
        global $DB;

        // Find all of the discussions, and discussion subscriptions for this forum.
        list($collaborateinsql, $collaborateparams) = $DB->get_in_or_equal(array_keys($mappings), SQL_PARAMS_NAMED);
        $sql = "SELECT
                    cs.*
                  FROM {collaborate} c
                  JOIN {collaborate_submissions} cs ON cs.collaborateid = c.id
                 WHERE c.id ${collaborateinsql}
                   AND cs.userid = :userid
        ";

        $params = ['userid' => $userid];
        $params += $collaborateparams;

        // Keep track of the forums which have data.
        $submissionswithdata = [];

        $submissions = $DB->get_recordset_sql($sql, $params);
        foreach ($submissions as $submission) {
            $submissionswithdata[$submission->collaborateid] = true;
            $context = \context::instance_by_id($mappings[$submission->collaborateid]);

            $submissiondata = (object) [
                'id' => $submission->id,
                'collaborateid' => $submission->collaborateid,
                'page' => $submission->page,
                'timecreated' => transform::datetime($submission->timecreated),
                'timemodified' => transform::datetime($submission->timemodified),
                'grade' => $submission->grade
            ];

            $submissiondata->submission = writer::with_context($context)->rewrite_pluginfile_urls(
                $submission->id, 'mod_collaborate', 'submission', $submission->id, $submission->submission);

            $submissiondata->submission = format_text($submission->submission, $submission->submissionformat, (object) [
                'noclean' => true,
                'overflowdiv' => true,
                'context' => $context
            ]);

            // Store the submission content.
            writer::with_context($context)

            // Store the submission.
                ->export_data($submission->id, $postdata)

            // Store the associated files.
                ->export_area_files($submission->id, 'mod_collaborate', 'submission', $submission->id);
        }

        $submissions->close();

        return $submissionswithdata;
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param   context                 $context   The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        // Check that this is a context_module.
        if (!$context instanceof \context_module) {
            return;
        }

        // Get the course module.
        if (!$cm = get_coursemodule_from_id('collaborate', $context->instanceid)) {
            return;
        }

        $submissions = $DB->get_record('collaborate_submissions', ['collaborateid' => $cm->instance]);

        $DB->delete_records('collaborate_submissions', ['collaborateid' => $cm->instance]);
        
        // Delete all files from the submissions.
        $fs = get_file_storage();
        // Item id is the id in the respective table.
        foreach ($submissions as $submission) {
            $fs->delete_area_files($context->id, 'mod_collaborate', 'submisson', $submission->id);
        }

    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $user = $contextlist->get_user();
        $userid = $user->id;
        foreach ($contextlist as $context) {
            // Get the course module.
            $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
            $submission = $DB->get_record('collaborate_submissions', [
                'collaborateid' => $cm->instance,
                'userid' => $userid
            ]);

            $DB->delete_records('collaborate_submissions', [
                'collaborateid' => $cm->instance,
                'userid' => $userid
            ]);

            // Delete all files from the submissions.
            $fs = get_file_storage();
            // Item id is the id in the respective table.
            $fs->delete_area_files($context->id, 'mod_collaborate', 'submisson', $submission->id);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
        error_log('Userlist('.$cm->instance.'): '.print_r($userlist, true));

        /*
        $forum = $DB->get_record('forum', ['id' => $cm->instance]);

        list($userinsql, $userinparams) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = array_merge(['forumid' => $forum->id], $userinparams);

        $DB->delete_records_select('forum_track_prefs', "forumid = :forumid AND userid {$userinsql}", $params);
        $DB->delete_records_select('forum_subscriptions', "forum = :forumid AND userid {$userinsql}", $params);
        $DB->delete_records_select('forum_read', "forumid = :forumid AND userid {$userinsql}", $params);
        $DB->delete_records_select(
            'forum_queue',
            "userid {$userinsql} AND discussionid IN (SELECT id FROM {forum_discussions} WHERE forum = :forumid)",
            $params
        );
        $DB->delete_records_select('forum_discussion_subs', "forum = :forumid AND userid {$userinsql}", $params);

        // Do not delete discussion or forum posts.
        // Instead update them to reflect that the content has been deleted.
        $postsql = "userid {$userinsql} AND discussion IN (SELECT id FROM {forum_discussions} WHERE forum = :forumid)";
        $postidsql = "SELECT fp.id FROM {forum_posts} fp WHERE {$postsql}";

        // Update the subject.
        $DB->set_field_select('forum_posts', 'subject', '', $postsql, $params);

        // Update the subject and its format.
        $DB->set_field_select('forum_posts', 'message', '', $postsql, $params);
        $DB->set_field_select('forum_posts', 'messageformat', FORMAT_PLAIN, $postsql, $params);

        // Mark the post as deleted.
        $DB->set_field_select('forum_posts', 'deleted', 1, $postsql, $params);

        // Note: Do _not_ delete ratings of other users. Only delete ratings on the users own posts.
        // Ratings are aggregate fields and deleting the rating of this post will have an effect on the rating
        // of any post.
        \core_rating\privacy\provider::delete_ratings_select($context, 'mod_forum', 'post', "IN ($postidsql)", $params);

        // Delete all Tags.
        \core_tag\privacy\provider::delete_item_tags_select($context, 'mod_forum', 'forum_posts', "IN ($postidsql)", $params);

        // Delete all files from the posts.
        $fs = get_file_storage();
        $fs->delete_area_files_select($context->id, 'mod_forum', 'post', "IN ($postidsql)", $params);
        $fs->delete_area_files_select($context->id, 'mod_forum', 'attachment', "IN ($postidsql)", $params);

        list($sql, $params) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['forum'] = $forum->id;
        // Delete advanced grading information.
        $grades = $DB->get_records_select('forum_grades', "forum = :forum AND userid $sql", $params);
        $gradeids = array_keys($grades);
        $gradingmanager = get_grading_manager($context, 'mod_forum', 'forum');
        $controller = $gradingmanager->get_active_controller();
        if (isset($controller)) {
            // Careful here, if no gradeids are provided then all data is deleted for the context.
            if (!empty($gradeids)) {
                \core_grading\privacy\provider::delete_data_for_instances($context, $gradeids);
            }
        }
        */
    }
}
