<?php

define('NO_DEBUG_DISPLAY', false);

// @codingStandardsIgnoreLine This script does not require login.
require("../../config.php");
require_once("lib.php");
require_once($CFG->libdir.'/eventslib.php');
require_once($CFG->libdir.'/enrollib.php');
require_once($CFG->libdir . '/filelib.php');

global $USER, $COURSE;

$data = new stdClass();


//$custom = explode('-', $data->custom);

$instanceid = $_POST['instance_id'];
$courseid = $_POST['course_id'];

$data->userid           = (int)$USER->id;
$data->courseid         = (int)$COURSE->id;
$data->instanceid       = (int)$instanceid;
//$data->payment_gross    = $data->mc_gross;
//$data->payment_currency = $data->mc_currency;
$data->timeupdated      = time();

$user = $DB->get_record("user", array("id" => $data->userid), "*");
$course = $DB->get_record("course", array("id" =>$COURSE->id), "*");
$context = context_course::instance($course->id);
$PAGE->set_context($context);

$plugin_instance = $DB->get_record("enrol", array("id" => $instanceid, "enrol" => "paybank", "status" => 0), "*");
$plugin = enrol_get_plugin('paybank');
$cost = $DB->get_record("enrol", array("id" => "$data->instanceid", "enrol" => "paybank", "status" => 0 ),"cost");
$b = $DB->get_record("user_info_data", array("userid" => $USER->id ),"data");
$balance= $b->data;
$uidid = $DB->get_record("user_info_data", array("userid" => $USER->id ),"id");
$user_info_data_id = $uidid->id;

        // Check that amount paid is the correct amount

        // Use the same rounding of floats as on the enrol form.

        if ($balance >= $plugin_instance->cost)
         {
          $newData = new stdClass();


                $timestart = time();
                $timeend   = $timestart + $plugin_instance->enrolperiod;


                $newData->timestart = $timestart;
                $newData->timeend   = $timeend;
                $newData->userid    = $USER->id;
                $newData->courseid    = $COURSE->id;
                $newData->instanceid    = $instanceid;



                $DB->insert_record("enrol_paybank", $newData);
                $balance = $balance - $plugin_instance->cost;

          $newBalance = new stdClass();
          $newBalance->data   = $balance;
          $newBalance->id = $user_info_data_id;

          $DB->update_record("user_info_data", $newBalance);

          // Enrol user
          $plugin->enrol_user($plugin_instance, $user->id, $plugin_instance->roleid, $timestart, $timeend);
            header('Location: ../../course/view.php?id='.urlencode($_POST['course_id']));
        }
        else {
          echo "<script>
            alert('Your balance is not enough, you will be directed to your profile !');
              window.location.href='../../user/profile.php';
                </script>";


          }
        $data->item_name = $course->fullname;

        // ALL CLEAR !
