<?php

define('NO_DEBUG_DISPLAY', true);

// @codingStandardsIgnoreLine This script does not require login.
require("../../config.php");
require_once("lib.php");
require_once($CFG->libdir.'/eventslib.php');
require_once($CFG->libdir.'/enrollib.php');
require_once($CFG->libdir . '/filelib.php');


set_exception_handler(\enrol_paybank\util::get_exception_handler());


$data = new stdClass();
foreach ($_POST as $key => $value) {
    if ($key !== clean_param($key, PARAM_ALPHANUMEXT)) {
        throw new moodle_exception('invalidrequest', 'core_error', '', null, $key);
    }
    if (is_array($value)) {
        throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Unexpected array param: '.$key);
    }
    $req .= "&$key=".urlencode($value);
    $data->$key = fix_utf8($value);
}

if (empty($data->custom)) {
    throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Missing request param: custom');
}

$custom = explode('-', $data->custom);
unset($data->custom);

if (empty($custom) || count($custom) < 3) {
    throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Invalid value of the request param: custom');
}

$data->userid           = (int)$custom[0];
$data->courseid         = (int)$custom[1];
$data->instanceid       = (int)$custom[2];
//$data->payment_gross    = $data->mc_gross;
$data->payment_currency = $data->mc_currency;
$data->timeupdated      = time();

$user = $DB->get_record("user", array("id" => $data->userid), "*", MUST_EXIST);
$course = $DB->get_record("course", array("id" => $data->courseid), "*", MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);
$PAGE->set_context($context);

$plugin_instance = $DB->get_record("enrol", array("id" => $data->instanceid, "enrol" => "paybank", "status" => 0), "*", MUST_EXIST);
$plugin = enrol_get_plugin('paybank');
$cost = $DB->get_record("enrol", array("id" => $data->instanceid, "enrol" => "paybank", "status" => 0 ),"cost",MUST_EXIST);
$b = $DB->get_record("user_info_data", array("id" => $USER->id ),"data",MUST_EXIST);
$balance= $b->data;

/*
        if (!$user = $DB->get_record('user', array('id'=>$data->userid))) {   // Check that user exists
            \enrol_paybank\util::message_paybank_error_to_admin("User $data->userid doesn't exist", $data);
            die;
        }

        if (!$course = $DB->get_record('course', array('id'=>$data->courseid))) { // Check that course exists
            \enrol_paybank\util::message_paybank_error_to_admin("Course $data->courseid doesn't exist", $data);
            die;
        }

        $coursecontext = context_course::instance($course->id, IGNORE_MISSING);
*/
        // Check that amount paid is the correct amount
        if ( (float) $plugin_instance->cost <= 0 ) {
            $cost = (float) $plugin->get_config('cost');
        } else {
            $cost = (float) $plugin_instance->cost;
        }

        // Use the same rounding of floats as on the enrol form.
        $cost = format_float($cost, 2, false);

        if ($balance->data < $cost)
         {
            \enrol_paybank\util::message_paybank_error_to_admin("Amount paid is not enough ($balance->data < $cost))", $data);
            die;
          }
        // Use the queried course's full name for the item_name field.
        else {
          $DB->insert_record("enrol_paybank", $data);

          if ($plugin_instance->enrolperiod) {
              $timestart = time();
              $timeend   = $timestart + $plugin_instance->enrolperiod;
          } else {
              $timestart = 0;
              $timeend   = 0;
          }

          // Enrol user
          $plugin->enrol_user($plugin_instance, $user->id, $plugin_instance->roleid, $timestart, $timeend);

          header('Location: user/profile.php');
        }
        $data->item_name = $course->fullname;

        // ALL CLEAR !
