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
 * Logs someone in as an example student/tutor, these are course sepcific and are created
 * if non-existant. This is almost exactly the same as the standard moodle loginas feature
 * and duplicates some of its code
 *
 * @package blocks
 * @subpackage viewasexample
 * @copyright 2011 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('../../course/lib.php');
require_once('../../group/lib.php');

global $USER, $CFG, $OUTPUT, $DB;

$return = optional_param('return', 0, PARAM_BOOL);// return to the page we came from
$login = optional_param('login', '', PARAM_TEXT);

//-------------------------------------
// Reset user back to their real self?
if (!empty($USER->realuser)) {
    $sesskey = optional_param('sesskey', '', PARAM_TEXT);
    if ($sesskey == '' || $login != '') {
        // might have accidentally refreshed page instead of continuing
        $id = optional_param('id', 0, PARAM_INT);
        if ($id>0) {
            $link = $CFG->wwwroot . '/course/view.php?id=' . $id;
        } else {
            $link = $CFG->wwwroot . '/course/';
        }
        $realuser = $DB->get_record('user', array('id'=>$USER->realuser));
        $fullname = fullname($realuser, true);
        print_error('viewasexamplealready', 'block_viewasexample', $link, $fullname);
    }
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad');
    }
    // Course info required for PAGE context
    $id = required_param('id', PARAM_INT);// course id
    if (!$course = $DB->get_record('course', array('id'=>$id))) {
        print_error('invalidcourseid');
    }
    $coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);
    $PAGE->set_context($coursecontext);
    $USER = get_complete_user_data('id', $USER->realuser);
    // Load all this user's normal capabilities
    load_all_capabilities();
    // Restore previous "current group" cache.
    if (isset($SESSION->oldcurrentgroup)) {
        $SESSION->currentgroup = $SESSION->oldcurrentgroup;
        unset($SESSION->oldcurrentgroup);
    }
    // Restore previous timeaccess settings
    if (isset($SESSION->oldtimeaccess)) {
        $USER->timeaccess = $SESSION->oldtimeaccess;
        unset($SESSION->oldtimeaccess);
    }
    // Restore grade defaults if any
    if (isset($SESSION->grade_last_report)) {
        $USER->grade_last_report = $SESSION->grade_last_report;
        unset($SESSION->grade_last_report);
    }
    // That's all we wanted to do, so let's go back
    if ($return and isset($_SERVER["HTTP_REFERER"])) {
        redirect($_SERVER["HTTP_REFERER"]);
    } else {
        redirect($CFG->wwwroot.'/course/');
    }
}

// Get course to login for
$id = required_param('id', PARAM_INT);// course id
$metacourseid = optional_param('metacourseid', 0,  PARAM_INT);// meta course id
if (!$course = $DB->get_record('course', array('id'=>$id))) {
    print_error('invalidcourseid');
}
$coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);
$viewfullnames = has_capability('moodle/site:viewfullnames', $coursecontext);

// General checking of capabilities etc
require_login($course);

// Switch determining if logging in as student(default) or tutor
$tutor = optional_param('tutor', false, PARAM_BOOL);
if (!$tutor) {
    require_capability('block/viewasexample:loginasstudent', $coursecontext);
} else {
    require_capability('block/viewasexample:loginastutor', $coursecontext);
}
//-------------------------------------
// User submitted and they want to loginas?
if ($login != '') {
    //do loginas

    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad');
    }

    // work out who the example user for this course is
    $firstname = $course->shortname;
    // this is hard coded because it's language independent
    $secondname = $tutor ? 'example tutor' : 'example student';

    // does the example account exist - get userid, if not do creation process
    if (!$userid = $DB->get_record_select('user', "firstname = ? AND lastname = ? AND username
            LIKE 'qxx%'", array($firstname, $secondname))) {
        // no account, create one, first find a username which hasn't been used
        $userobj = new stdClass();

        // Find the first discountinued username
        $run = $DB->get_records_sql($sql = "SELECT u1.username AS uname1
                FROM {user} u1
                    LEFT JOIN {user} u2 ON u2.username =".
                    $DB->sql_concat("'qxx'",
                        $DB->sql_cast_char2int($DB->sql_substr("u1.username", 4)) . " + 1") . "
                WHERE u1.username LIKE 'qxx%' AND u2.id IS NULL
                ORDER BY u1.username", array(), 0, 1);

        $usernamesuffix = 1000;
        if ($run) {
            $usernamesuffix = intval(substr(reset($run)->uname1, 3)) + 1;
        }
        $userobj->username = 'qxx' . $usernamesuffix;
        $userobj->idnumber = 'I000' . $usernamesuffix;
        $userobj->firstname = $firstname;
        $userobj->lastname = $secondname;

        // sams auth not appropriate, users not created by dataload
        $userobj->auth = 'manual';
        $userobj->confirmed = 1;
        $userobj->lastip = getremoteaddr();
        $userobj->timemodified = time();
        $userobj->firstaccess = $userobj->timemodified;
        $userobj->mnethostid = $CFG->mnet_localhost_id;
        $userobj->emailstop = 1;
        $userobj->email = $CFG->noreplyaddress;
        $userobj->description = 'Example user for ' . $course->shortname;
        // sams enrolment not appropriate unless users created by dataload
        $plugin = enrol_get_plugin('manual');
        // now add the user
        $userid = $DB->insert_record('user', $userobj, true);

        if (!$tutor) {
            // Enrol user on this course as student role and contributing student role
            if ($roleid = $DB->get_field('role', 'id', array('shortname'=>'student'))) {
                role_assign($roleid, $userid, $coursecontext->id, '', '', time());
            }
            if ($roleid = $DB->get_field('role', 'id', array('shortname'=>'contributingstudent'))) {
                role_assign($roleid, $userid, $coursecontext->id, '', '', time());
            }
        } else {
            // Enrol user on this course as tutor (non-editing teacher) role
            if ($roleid = $DB->get_field('role', 'id', array('shortname'=>'teacher'))) {
                role_assign($roleid, $userid, $coursecontext->id, '', '', time());
            }
        }
        $instance = $DB->get_record('enrol', array('courseid'=>$course->id, 'enrol'=>'manual'),
                '*', MUST_EXIST);

        $plugin->enrol_user($instance, $userid, $roleid, '', '');

    } else {
        // User exists in db
        // Make sure the example user is enrolled in the course
        if (!is_enrolled($coursecontext, $userid->id)) {
            $plugin = enrol_get_plugin('manual');
            // Now add the user back to the course
            $instance = $DB->get_record('enrol', array('courseid'=>$course->id, 'enrol'=>'manual'),
                    '*', MUST_EXIST);
            $plugin->enrol_user($instance, $userid->id, NULL, '', '');
        }
        // Make sure the example user have correct role assigned
        if ($tutor) {
            // Enrol user on this course as tutor (non-editing teacher) role
            if ($roleid = $DB->get_field('role', 'id', array('shortname'=>'teacher'))) {
                role_assign($roleid, $userid->id, $coursecontext->id, '', '', time());
            }
        } else {
            // Enrol user on this course as student role and contributing student role
            if ($roleid = $DB->get_field('role', 'id', array('shortname'=>'student'))) {
                role_assign($roleid, $userid->id, $coursecontext->id, '', '', time());
            }
            if ($roleid = $DB->get_field('role', 'id', array('shortname'=>'contributingstudent'))) {
                role_assign($roleid, $userid->id, $coursecontext->id, '', '', time());
            }
        }
        $userid = $userid->id;
    }

    // Now need to create tutor group for the account and add account to any required tutor groups
    // First: check for suitable groupings; if exists create new testing group and add to groupings
    $result = $DB->get_field('groupings', 'id', array('courseid'=>$course->id,
            'name' => 'Tutor groups ('  . $course->shortname . ')'));
    $result2 = $DB->get_field('groupings', 'id', array('courseid'=>$course->id,
            'name' => $course->shortname . ' variant tutor groups'));
    if ($result || $result2) {
        // check if test group exists
        if (!$test=$DB->get_field('groups', 'id', array('courseid'=>$course->id,
                'name'=>'Testing TG [000000] ' . $course->shortname))) {
            $groupdata = new stdClass();
            $groupdata->name = 'Testing TG [000000] ' . $course->shortname;
            $groupdata->courseid = $course->id;
            $groupdata->description = 'Test group';
            $test = groups_create_group($groupdata);
        }
        // add user to group
        groups_add_member($test, $userid);

        // Tutor groups (X123-11B)
        if ($result) {
            groups_assign_grouping($result, $test);
        }
        // X123-11B variant tutor groups
        if ($result2) {
            groups_assign_grouping($result2, $test);
        }
    }
    // Second: check for R80 Group and if exists add user
    if ($result = $DB->get_field('groups', 'id', array('courseid'=>$course->id,
            'name'=>$course->shortname . ' R80 Group'))) {
        groups_add_member($result, $userid);
    }
    // Third: Check for Course Group and if exists add user
    if ($result = $DB->get_field('groups', 'id', array('courseid'=>$course->id,
            'name'=>$course->shortname . ' Course Group'))) {
        groups_add_member($result, $userid);
    }

    $systemcontext = get_context_instance(CONTEXT_SYSTEM);

    $capability = $tutor ? 'block/viewasexample:loginastutor' :
        'block/viewasexample:loginasstudent';

    // double check that new user is enrolled in this course
    if (!is_enrolled($coursecontext, $userid)) {
        print_error('usernotincourse');
    }

    // If they chose to hide the form in future, save this
    if (optional_param('nevershow', 0, PARAM_INT)) {
        set_user_preference('block_viewasexample_nevershow', 1);
    }

    // Remember current timeaccess settings for later
    if (isset($USER->timeaccess)) {
        $SESSION->oldtimeaccess = $USER->timeaccess;
    }
    if (isset($USER->grade_last_report)) {
        $SESSION->grade_last_report = $USER->grade_last_report;
    }

    $oldfullname = fullname($USER, $viewfullnames);
    $olduserid   = $USER->id;

    // Create the new USER object with all details and reload needed capabilitites
    // to login as this user and return to course home page.
    $_SESSION['SESSION']  = new stdClass();
    $_SESSION['REALUSER'] = $_SESSION['USER'];

    $USER = get_complete_user_data('id', $userid);
    $USER->realuser = $olduserid;
    $USER->loginascontext = $coursecontext;
    // reload capabilities
    load_all_capabilities();
    // Remember current cache setting for later
    if (isset($SESSION->currentgroup)) {
        $SESSION->oldcurrentgroup = $SESSION->currentgroup;
        unset($SESSION->currentgroup);

    }

    $newfullname = fullname($USER, $viewfullnames);

    add_to_log($course->id, "course", "loginasexample", "../user/view.php?tutor=$tutor&amp;
            id=$course->id&amp;user=$userid", "$oldfullname -> $newfullname");

    $redirectid = $course->id;
    if ($metacourseid) {
        $redirectid = $metacourseid;
    }

    redirect("$CFG->wwwroot/course/view.php?id=$redirectid");
}

// Check metacourse and redirect if needed
$metacourseid = 0;
if (isset($course->metacourse)) {
    if (!$childid = $DB->get_field('course_meta', 'child_course', 'parent_course', $course->id)) {
        print_error('loginasnochildcourse', 'block_viewasexample');
    }
    $metacourseid = $course->id;
    redirect(new moodle_url('/blocks/viewasexample/loginasexample.php',
            array('id'=>$childid, 'metacourseid'=>$metacourseid, 'tutor'=>$tutor)));
}

// If they chose to skip it
if (get_user_preferences('block_viewasexample_nevershow', '0')) {
    redirect(new moodle_url('/blocks/viewasexample/loginasexample.php',
            array('id'=>$id, 'sesskey'=>sesskey(), 'login'=>1, 'tutor'=>$tutor)));
}

//-------------------------------------
// Front screen
$strloggedinas = $tutor ? get_string('viewastutorhead', 'block_viewasexample') :
        get_string('viewasstudenthead', 'block_viewasexample');

// Print header
$title = format_string($course->fullname) . ': ' . $strloggedinas;
$PAGE->navbar->add($strloggedinas);
$url = new moodle_url('/blocks/viewasexample/loginasexample.php', array('id'=>$course->id));
if (!empty($return)) {
    $url->param('return', $return);
}
if (!empty($login)) {
    $url->param('login', $login);
}
if (!empty($tutor)) {
    $url->param('tutor', $tutor);
}
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_course($course);
$PAGE->set_context($coursecontext);
echo $OUTPUT->header();

// instructions, name hard coded because it is language independent
$name = $tutor ? 'example tutor' : 'example student';
$a = new stdClass();
$a->newusername = $course->shortname . ' ' . $name;
$a->fullname = fullname($USER, $viewfullnames);
print_string('viewasinst', 'block_viewasexample', $a);

// form, include sesskey and id
$sesskey = sesskey();
$butname = $tutor ? get_string('viewastutorbut', 'block_viewasexample') :
        get_string('viewasstudentbut', 'block_viewasexample');


$neverstr = get_string('nevershowagain', 'block_viewasexample');

$formstr = <<<FORM
<form action="" method="post">
<div>
<input type="hidden" name="id" value="$id"/>
<input type="hidden" name="sesskey" value="$sesskey"/>
<input type="submit" name="login" value="$butname"/>
<input type="hidden" name="tutor" value="$tutor"/>
</div>
<div class="block-viewasexample-nevershow">
<input id="nevershow" type="checkbox" name="nevershow" value="1"/>
<label for="nevershow">$neverstr</label>
</div>
</form>
FORM;

echo $formstr;
echo $OUTPUT->footer();
