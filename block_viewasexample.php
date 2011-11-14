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

require_once($CFG->dirroot . '/blocks/moodleblock.class.php');

/**
 * Block for login as example student or tutor.
 *
 * @package blocks
 * @subpackage viewasexample
 * @copyright 2011 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_viewasexample extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_viewasexample');
    }

    public function applicable_formats() {
        return array('all' => true);
    }


    public function instance_allow_multiple() {
        return false;
    }

    public function get_content() {

        if ($this->content !== null) {
            return $this->content;
        }
        global $CFG, $USER, $SESSION, $OUTPUT;

        $course = $this->page->course;
        $this->content = new stdClass;
        $this->content->footer = '';

        $coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);
        //check wherether the block should be shown at all
        if (isguestuser() || session_is_loggedinas() ||
                !has_capability('block/viewasexample:loginasstudent', $coursecontext)) {
            return;
        }

        $this->content->text .= '<ol class="list">';
        $baseurl = new moodle_url('/blocks/viewasexample/loginasexample.php');
        $url = new moodle_url($baseurl);

        if (has_capability('block/viewasexample:loginasstudent', $coursecontext)) {
            $url->params(array('id'=>$course->id));
            $icon = '<img src="' . $OUTPUT->pix_url('i/users') . '" class="icon" alt="" />&nbsp;';
            $this->content->text .= '<li><a href="' . $url->out() . '">' . $icon . 'Student' .
                    '</a></li>';
        }
        if (has_capability('block/viewasexample:loginastutor', $coursecontext)) {
            $url->params(array('id'=>$course->id, 'tutor'=>1));
            $icon = '<img src="' . $OUTPUT->pix_url('i/users') . '" class="icon" alt="" />&nbsp;';
            $this->content->text .= '<li><a href="' . $url->out() . '">' . $icon . 'Tutor' .
                    '</a></li>';
        }

        $this->content->text .= "</ol>\n";
        return $this->content;
    }

    /**
     * Called by the theme. If the user is currently logged in as the example student or
     * tutor, will display a box offering them the opportunity to turn back into themselves.
     * @return string HTML code to display
     */
    public static function header_html() {
        global $USER, $DB, $COURSE;
        $out = '';
        if (!empty($USER->realuser) && strpos($USER->username, 'qxx')===0) {
            $realuser = $DB->get_record('user', array('id' => $USER->realuser), '*', MUST_EXIST);
            $out = html_writer::start_tag('div', array('class'=>'block-viewasexample-stripe'));
            $out .= get_string('loginasinfo', 'block_viewasexample', fullname($USER, true)) . ' ';
            $out .= html_writer::start_tag('a', array('href'=>#
                    new moodle_url('/blocks/viewasexample/loginasexample.php', array('return'=>1,
                        'id'=>$COURSE->id, 'sesskey'=>sesskey()))));
            $out .= get_string('loginasback', 'block_viewasexample', fullname($realuser, true));
            $out .= html_writer::end_tag('a') . html_writer::end_tag('div');
        }
        return $out;
    }
}
