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
 * sqlupiti question definition class.
 *
 * @package    qtype
 * @subpackage sqlupiti
 * @copyright  2014 Krunoslav Smrekar (ksmrekar@riteh.hr)

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Represents a sqlupiti question.
 *
 * @copyright  2014 Krunoslav Smrekar (ksmrekar@riteh.hr)

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_sqlupiti_question extends question_graded_automatically_with_countback {
    
    public $sqlanswer;
    public $server;
    public $username;
    public $password;
    public $dbname;

    public function get_expected_data() {
        return array('answer' => PARAM_NOTAGS);
    }

    public function summarise_response(array $response) {
        if (!array_key_exists('answer', $response)) {
            return null;
        } else {
            return $response['answer'];
        }
        return null;
    }
    
    public function get_right_answer_summary() {
        return $this->sqlanswer;
    }

    public function is_complete_response(array $response) {
        return array_key_exists('answer', $response);
    }

    public function get_validation_error(array $response) {
        if ($this->is_gradable_response($response)) {
            return '';
        }
        return get_string('pleaseenterquery', 'qtype_sqlupiti');
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        return question_utils::arrays_same_at_key_missing_is_blank(
                $prevresponse, $newresponse, 'answer');
    }

    public function get_correct_response() {
        // TODO.
        return array('sqlanswer' => $this->sqlanswer);
    }

    public function check_file_access($qa, $options, $component, $filearea,
            $args, $forcedownload) {
        //access to image of ER model
        if ($component == 'qtype_sqlupiti' && $filearea == 'ermodel') {
            $question = $qa->get_question();
            $itemid = reset($args);
            return $itemid == $question->id;
        }
    }

    public function grade_response(array $response) {
        $mysqli = new mysqli($this->server, $this->username, $this->password, $this->dbname);

        $correct_query = $this->sqlanswer;
        $student_query = $response['answer'];
        
        $correct_result = $mysqli->query($correct_query);
        $correct_row_cnt = $correct_result->num_rows;
        
        $student_result = $mysqli->query($student_query);
        $student_row_cnt = $student_result->num_rows;
        
        if (strpos($correct_query, ';')){
            $correct_query = str_replace(';', '', $correct_query);
        }
        if (strpos($student_query, ';')){
            $student_query = str_replace(';', '', $student_query);
        }
        
        $check_equality = "SELECT CASE WHEN count1 = count2 AND count1 = count3 THEN 'identical' ELSE 'mis-matched' END
            FROM
            (
            SELECT
            (SELECT COUNT(*) FROM (" . $correct_query . ") AS foo1) AS count1,
            (SELECT COUNT(*) FROM (" . $student_query . ") AS foo2) AS count2,
            (SELECT COUNT(*) FROM (SELECT * FROM (" . $correct_query . ") AS foo3 UNION SELECT * FROM (" . $student_query . ") AS foo4) AS unioned) AS count3
            )
            AS counts";
        
        $equality_result = $mysqli->query($check_equality);
        if($equality_result){
            $is_equal = $equality_result->fetch_all();
        } else {
            $is_equal = array(array('mis-matched'));
        }
        
        if ($correct_row_cnt == $student_row_cnt) {
            if ($is_equal[0][0] === "identical") {
                $fraction = 1;
            } else {
                $fraction = 0;
            }
        } else {
            $fraction = 0;
        }

        return array($fraction, question_state::graded_state_for_fraction($fraction));
    }

    public function compute_final_grade($responses, $totaltries) {
        // TODO.
        return 0;
    }
}
