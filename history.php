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
*
*
* @package    local
* @subpackage paperattendance
* @copyright  2016 Jorge Cabané (jcabane@alumnos.uai.cl) 	
* @copyright  2016 Matías Queirolo (mqueirolo@alumnos.uai.cl) 
* @copyright  2016 Cristobal Silva (cristobal.isilvap@gmail.com) 					
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/
//Belongs to plugin PaperAttendance

require_once (dirname(dirname(dirname(__FILE__)))."/config.php");
require_once ($CFG->dirroot."/local/paperattendance/forms/history_form.php");
require_once ($CFG->dirroot."/local/paperattendance/forms/addstudent_form.php");
require_once($CFG->dirroot . '/local/paperattendance/locallib.php');

global $DB, $PAGE, $OUTPUT, $USER, $CFG;

// Possible actions -> view, scan or students attendance . Standard is view mode
$action = optional_param("action", "view", PARAM_TEXT);
$idattendance = optional_param("idattendance", null, PARAM_INT);
$idpresence = optional_param("idpresence", null, PARAM_INT);
$idcourse = required_param('courseid', PARAM_INT);

$context = context_course::instance($COURSE->id);
$url = new moodle_url("/local/paperattendance/history.php", array('courseid' => $idcourse));
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout("standard");

$contextsystem = context_system::instance();


//Page
$page = optional_param('page', 0, PARAM_INT);
$perpage = 26;
//for navbar
$course = $DB->get_record("course",array("id" => $idcourse));

require_login();
if (isguestuser()){
	die();
}
/*
if( !has_capability("local/paperattendance:history", $context) ){
	print_error("ACCESS DENIED");
}
*/
//Begins Teacher's View
$isteacher = paperattendance_getteacherfromcourse($idcourse, $USER->id);

$isstudent = paperattendance_getstudentfromcourse($idcourse, $USER->id);

if( $isteacher || is_siteadmin($USER)) {
	
	//breadcrumb for navigation
	$PAGE->navbar->ignore_active();
	$PAGE->navbar->add(get_string('courses', 'local_paperattendance'), new moodle_url('/course/index.php'));
	$PAGE->navbar->add($course->shortname, new moodle_url('/course/view.php', array("id" => $idcourse)));
	$PAGE->navbar->add(get_string('pluginname', 'local_paperattendance'));
	$PAGE->navbar->add(get_string('historytitle', 'local_paperattendance'), new moodle_url("/local/paperattendance/history.php", array("courseid" => $idcourse)));
	
	// action-> Students Attendance
	if ($action == "studentsattendance"){
		
		$PAGE->navbar->add(get_string('studentsattendance', 'local_paperattendance'),
				new moodle_url("/local/paperattendance/history.php", array("courseid" => $idcourse , "idattendance" => $idattendance, "action" => $action)));
		
		//Query for the total count of attendances
		$getstudentsattendancecount = 'SELECT
				count(*)
				FROM {paperattendance_presence} AS p
				INNER JOIN {user} AS u ON (u.id = p.userid)
				WHERE p.sessionid = ? ';
		
		$attendancescount = $DB->count_records_sql($getstudentsattendancecount, array($idattendance));
		
		//Query to get the table data of attendances
		$getstudentsattendance = 'SELECT
				p.id AS idp,
				u.lastname,
				u.firstname,
				u.email,
				p.status,
				p.omegasync,
				p.grayscale
				FROM {paperattendance_presence} AS p
				INNER JOIN {user} AS u ON (u.id = p.userid)
				WHERE p.sessionid = ?  ';
		
		//$attendances = $DB->get_records_sql($getstudentsattendance, array($idcourse, $idattendance));
		
		//Getting attendances per page, initial page = 0.
		//$attendances = $DB->get_records_sql($getstudentsattendance, $params, $page * $perpage, ($page + 1) * $perpage);
		$attendances = $DB->get_records_sql($getstudentsattendance, array($idattendance), $page * $perpage, $perpage);
		
		$attendancestable = new html_table();
		
		//Check if we have at least one attendance in the selected session
		if ($attendancescount > 0){
			if (is_siteadmin($USER)){
				$attendancestable->head = array(
						get_string('hashtag', 'local_paperattendance'),
						get_string('student', 'local_paperattendance'),
						get_string('mail', 'local_paperattendance'),
						get_string('attendance', 'local_paperattendance'),
						get_string('setting', 'local_paperattendance'),
						get_string('omegasync', 'local_paperattendance'),
						get_string('grayscale', 'local_paperattendance')
				);
			}
			else {
				$attendancestable->head = array(
						get_string('hashtag', 'local_paperattendance'),
						get_string('student', 'local_paperattendance'),
						get_string('mail', 'local_paperattendance'),
						get_string('attendance', 'local_paperattendance'),
						get_string('setting', 'local_paperattendance'),
						get_string('omegasync', 'local_paperattendance')
				);
			}
			//A mere counter for de number of records in the table
			$counter = $page * $perpage + 1;
			foreach ($attendances as $attendance){
				
				//Define synchronized or unsynchronized icon and url
				$urlomegasync = new moodle_url("#");
				
				$synchronizedicon = new pix_icon("i/checkpermissions", get_string('synchronized', 'local_paperattendance'));
				
				$synchronizediconaction = $OUTPUT->action_icon(
						$urlomegasync,
						$synchronizedicon
						);
				
				$unsynchronizedicon = new pix_icon("i/scheduled", get_string('unsynchronized', 'local_paperattendance'));
				
				$unsynchronizediconaction = $OUTPUT->action_icon(
						$urlomegasync,
						$unsynchronizedicon
						);
				
				//Define presente or ausente icon and url
				$urlattendance = new moodle_url("#");
				$presenticon = new pix_icon("i/valid", get_string('presentattendance', 'local_paperattendance'));
		
				$presenticonaction = $OUTPUT->action_icon(
						$urlattendance,
						$presenticon
						);
		
				$absenticon = new pix_icon("i/invalid", get_string('absentattendance', 'local_paperattendance'));
		
				$absenticonaction = $OUTPUT->action_icon(
						$urlattendance,
						$absenticon
						);
				
				// Define edition icon and url
				$editurlattendance = new moodle_url("/local/paperattendance/history.php", array(
						"action" => "edit",
						"idpresence" => $attendance->idp,
						"idattendance" => $idattendance,
						"courseid" => $idcourse
				));
				$editiconattendance = new pix_icon("i/edit", get_string('edithistory', 'local_paperattendance'));
				$editactionasistencia = $OUTPUT->action_icon(
						$editurlattendance,
						$editiconattendance
						);
				
				$name = ($attendance->firstname.' '.$attendance->lastname);
				
				//Now we check if the student is present or not
				if ($attendance->status == 1){
					//Now we check if the student is synchronized with omega or not	
					if ($attendance->omegasync == 1){
						
						if (is_siteadmin($USER)){
							$attendancestable->data[] = array(
									$counter,
									$name,
									$attendance->email,
									$presenticonaction,
									$editactionasistencia,
									$synchronizediconaction,
									$attendance->grayscale
							);
						}
						else {
							$attendancestable->data[] = array(
									$counter,
									$name,
									$attendance->email,
									$presenticonaction,
									$editactionasistencia,
									$synchronizediconaction
							);
						}
						
					}
					else {
						
						if (is_siteadmin($USER)){
							$attendancestable->data[] = array(
									$counter,
									$name,
									$attendance->email,
									$presenticonaction,
									$editactionasistencia,
									$unsynchronizediconaction,
									$attendance->grayscale
							);
							
						}
						else {
							$attendancestable->data[] = array(
									$counter,
									$name,
									$attendance->email,
									$presenticonaction,
									$editactionasistencia,
									$unsynchronizediconaction,
							);
							
						}
						
					}
					
				}
				else {
					
					if ($attendance->omegasync == 1){
						
						if (is_siteadmin($USER)){
							$attendancestable->data[] = array(
									$counter,
									$name,
									$attendance->email,
									$absenticonaction,
									$editactionasistencia,
									$synchronizediconaction,
									$attendance->grayscale
							);
						}
						else {
							$attendancestable->data[] = array(
									$counter,
									$name,
									$attendance->email,
									$absenticonaction,
									$editactionasistencia,
									$synchronizediconaction
							);
						}
						
					}
					else {
						
						if (is_siteadmin($USER)){
							$attendancestable->data[] = array(
									$counter,
									$name,
									$attendance->email,
									$absenticonaction,
									$editactionasistencia,
									$unsynchronizediconaction,
									$attendance->grayscale
							);
								
						}
						else {
							$attendancestable->data[] = array(
									$counter,
									$name,
									$attendance->email,
									$absenticonaction,
									$editactionasistencia,
									$unsynchronizediconaction
							);
							
						}
						
					}
						
				}
				$counter++;
			}
		}
		
		$viewbackbutton = new moodle_url("/local/paperattendance/history.php", array("action" => "view", "courseid" => $idcourse));	
		$insertstudenturl = new moodle_url("/local/paperattendance/history.php", array(
				"action" => "insertstudent",
				"courseid" => $idcourse,
				"idattendance" => $idattendance
		));
	}	
	// Edits an existent record for the students attendance view
	if($action == "edit"){
		if($idpresence == null){
			print_error(get_string('nonselectedstudent', 'local_paperattendance'));
			$canceled = new moodle_url("/local/paperattendance/history.php", array(
					"idattendance" => $idattendance,
					"courseid" => $idcourse
					));
			redirect($canceled);
		}
		else{
			
			if($attendance = $DB->get_record("paperattendance_presence", array("id" => $idpresence)) ){
			
				$editform = new editattendance(null, array(
						"idattendance" => $idattendance,
						"courseid" => $idcourse,
						"idpresence" => $idpresence
				));
	
				if($editform->is_cancelled()){
					$canceled = new moodle_url("/local/paperattendance/history.php", array(
							"action" => "studentsattendance",
							"idattendance" => $idattendance,
							"courseid" => $idcourse
					));
					redirect($canceled);
	
				}
				else if($editform->get_data()){
	
					$record = new stdClass();
					$record->id = $idpresence;
					$record->lastmodified = time();
					
					if ($editform->get_data()->status == 1){
						$record->status = 1;
					}
					else {
						$record->status = 0;
					}
							
					$DB->update_record("paperattendance_presence", $record);
					
					if(paperattendance_checktoken($CFG->paperattendance_omegatoken)){
					paperattendance_omegaupdateattendance($record->status, $record->omegaid);
					}
					
					$backurl = new moodle_url("/local/paperattendance/history.php", array(
							"action" => "studentsattendance",
							"idattendance" => $idattendance,
							"courseid" => $idcourse
					));
					redirect($backurl);										
				}
			}
			else{
				print_error(get_string('nonexiststudent', 'local_paperattendance'));
				$canceled = new moodle_url("/local/paperattendance/history.php", array(
						"action" => "studentsattendance",
						"idattendance" => $idattendance,
						"courseid" => $idcourse
				));
				redirect($canceled);
			
			}
		}
	}
	
	//Scan view
	if($action == "scan"){
		
		$backurl = new moodle_url("/local/paperattendance/history.php", array(
				"action" => "view",
				"idattendance" => $idattendance,
				"courseid" => $idcourse
				));
		
		$viewbackbutton = html_writer::nonempty_tag(
				"div",
				$OUTPUT->single_button($backurl, get_string('back', 'local_paperattendance')),
				array("align" => "left"
				));
		
		$getpdfname = 'SELECT
					   pdf
				       FROM {paperattendance_session} AS ps
				       WHERE ps.id = ?';
		
		$pdfname = $DB->get_record_sql($getpdfname, array($idattendance));
		
		//var_dump($contextsystem->id);
		//Context id as 1 because the var context->id gets the number 6 , check it later
		$url = moodle_url::make_pluginfile_url($contextsystem->id, 'local_paperattendance', 'draft', 0, '/', $pdfname->pdf);
	
		$viewerpdf = html_writer::nonempty_tag("embed", " ", array(
				"src" => $url,
				"style" => "height:75vh; width:60vw"
		));
	}
	
	// Lists all records in the database
	if ($action == "view"){
		$getattendances = "SELECT s.id, sm.date, CONCAT( m.initialtime, ' - ', m.endtime) AS hour, s.pdf
						   FROM {paperattendance_session} AS s
						   INNER JOIN {paperattendance_sessmodule} AS sm ON (s.id = sm.sessionid)
						   INNER JOIN {paperattendance_module} AS m ON (sm.moduleid = m.id)
						   WHERE s.courseid = ?
						   ORDER BY sm.date DESC";
		
		$attendances = $DB->get_records_sql($getattendances, array($idcourse));
		
		$attendancestable = new html_table();
		//we check if we have attendances for the selected course
		if (count($attendances) > 0){
			$attendancestable->head = array(
					get_string('hashtag', 'local_paperattendance'),
					get_string('date', 'local_paperattendance'),
					get_string('time', 'local_paperattendance'),
					get_string('scan', 'local_paperattendance'),
					get_string('studentsattendance', 'local_paperattendance'),
					get_string('omegasync', 'local_paperattendance')
			);
			$attendancestable->size = array(
					'9%',
					'33%',
					'19%',
					'16%',
					'16%',
					'17%'
			);
				
			$attendancestable->align = array(
					'left',
					'left',
					'left',
					'center',
					'center',
					'center'
			);
				
			//A mere counter for the number of records
			$counter = 1;
			foreach ($attendances as $attendance){
				// Define scan icon and url
				$scanurl_attendance = new moodle_url("/local/paperattendance/history.php", array(
						"action" => "scan",
						"idattendance" => $attendance->id,
						"courseid" => $idcourse
						
				));
				$scanicon_attendance = new pix_icon("e/new_document", get_string('see', 'local_paperattendance'));
				$scanaction_attendance = $OUTPUT->action_icon(
						$scanurl_attendance,
						$scanicon_attendance
						);
	
				// Define Asistencia alumnos icon and url
				$studentsattendanceurl_attendance = new moodle_url("/local/paperattendance/history.php", array(
						"action" => "studentsattendance",
						"idattendance" => $attendance->id,
						"courseid" => $idcourse
				));
				$studentsattendanceicon_attendance = new pix_icon("e/fullpage", get_string('seestudents', 'local_paperattendance'));
				$studentsattendanceaction_attendance = $OUTPUT->action_icon(
						$studentsattendanceurl_attendance,
						$studentsattendanceicon_attendance
						);
				
				//Define synchronized or unsynchronized icon and url
				$urlomegasync = new moodle_url("#");
				
				$synchronizedicon = new pix_icon("t/go", get_string('synchronized', 'local_paperattendance'));
				
				$synchronizediconaction = $OUTPUT->action_icon(
						$urlomegasync,
						$synchronizedicon
						);
				
				$unsynchronizedicon = new pix_icon("i/scheduled", get_string('unsynchronized', 'local_paperattendance'));
				
				$unsynchronizediconaction = $OUTPUT->action_icon(
						$urlomegasync,
						$unsynchronizedicon
						);
				//Convert the unix date to a local date
				$date= $attendance->date;
				$dateconverted = paperattendance_convertdate($date);
				
				//We get the total count of sync students for this session
				$synchronizedstudentnscount = paperattendance_getcountstudentssynchronizedbysession($attendance->id);
				//We get the total count of students for this session
				$studentscount = paperattendance_getcountstudentsbysession($attendance->id);
				//Check if this session is fully synchronized with omega and create de table
				if ( $synchronizedstudentnscount == $studentscount){
					
					$attendancestable->data[] = array(
							$counter,
							$dateconverted,
							$attendance->hour,
							$scanaction_attendance,
							$studentsattendanceaction_attendance,
							$synchronizediconaction
					);
					$counter++;
					
				}
				else {
					$attendancestable->data[] = array(
							$counter,
							$dateconverted,
							$attendance->hour,
							$scanaction_attendance,
							$studentsattendanceaction_attendance,
							$unsynchronizediconaction
					);
					$counter++;
				}
			}
		}
		$buttonurl = new moodle_url("/course/view.php", array("id" => $idcourse));
		
	}	
	
	if($action == "insertstudent"){
		$mform = new paperattendance_addstudent_form(null, array(
				"idcourse" => $idcourse,
				"idattendance" => $idattendance
		));
		if($mform->is_cancelled()){
			$goback = new moodle_url("/local/paperattendance/history.php", array(
					"action" => "studentsattendance",
					"idattendance" => $idattendance,
					"courseid" => $idcourse
			));
			redirect($goback);
		}
		else if($data = $mform->get_data()){
			$insertby = $data->insertby;
			$filter = $data->filter;
			$status = $data->status;
			$user = $DB->get_record("user", array($insertby => $filter));
			$addstudent = new stdClass();
			$addstudent->sessionid = $idattendance;
			$addstudent->userid = $user->id;
			$addstudent->status = $status;
			$addstudent->lastmodified = time();
			$addstudent->omegasync = 0;
			$insertattendance = $DB->insert_record("paperattendance_presence", $addstudent, false);
			$goback = new moodle_url("/local/paperattendance/history.php", array(
					"action" => "studentsattendance",
					"idattendance" => $idattendance,
					"courseid" => $idcourse
			));
			redirect($goback);
		}
	}
	
	$PAGE->set_title(get_string('historytitle', 'local_paperattendance'));
	$PAGE->set_heading(get_string('historyheading', 'local_paperattendance'));
	
	echo $OUTPUT->header();
	
	// Displays Students Attendance view
	if ($action == "studentsattendance"){
		if (count($attendances) == 0){
			echo html_writer::nonempty_tag("h4", get_string('nonprocessingattendance', 'local_paperattendance'), array("align" => "left"));
		}
		else{
			//displays button to add a student manually
			echo html_writer::nonempty_tag("div", $OUTPUT->single_button($insertstudenturl, get_string('insertstudentmanually', 'local_paperattendance')), array("align" => "left"));
			//displays the table
			echo html_writer::table($attendancestable);
			//displays de pagination bar
			echo $OUTPUT->paging_bar($attendancescount, $page, $perpage,
				 	$CFG->wwwroot . '/local/paperattendance/history.php?action=' . $action . '&idattendance=' . $idattendance . '&courseid=' . $idcourse . '&page=');
		}	
		echo html_writer::nonempty_tag("div", $OUTPUT->single_button($viewbackbutton, get_string('back', 'local_paperattendance')), array("align" => "left"));
		
	}
	
	// Displays the form to edit a record
	if( $action == "edit" ){
		$editform->display();
	}
	
	//Displays the scan file
	if($action == "scan"){
	
		// Donwload and back buttons
		echo $OUTPUT->action_icon($url, new pix_icon('i/grades', get_string('download', 'local_paperattendance')), null, array("target" => "_blank"));
		echo html_writer::nonempty_tag("h7", get_string('downloadassistance', 'local_paperattendance'), array("align" => "left"));
	
		echo $viewbackbutton;
	
		// Preview PDF
		echo $viewerpdf;
	}
	
	// Displays all the records and options
	if ($action == "view"){
	
		if (count($attendances) == 0){
			echo html_writer::nonempty_tag("h4", get_string('nonexistintingrecords', 'local_paperattendance'), array("align" => "left"));
		}
		else{
			echo html_writer::table($attendancestable);
		}
		echo html_writer::nonempty_tag("div", $OUTPUT->single_button($buttonurl, get_string('backtocourse', 'local_paperattendance')), array("align" => "left"));
	}
	
	//Displays the insert student form
	if($action == "insertstudent"){
		$mform->display();
	}

}	

//Ends Teacher's view


//Begins Student's view
else if ($isstudent) {
	
	//breadcrumb for navigation
	$PAGE->navbar->ignore_active();
	$PAGE->navbar->add(get_string('courses', 'local_paperattendance'), new moodle_url('/course/index.php'));
	$PAGE->navbar->add($course->shortname, new moodle_url('/course/view.php', array("id" => $idcourse)));
	$PAGE->navbar->add(get_string('pluginname', 'local_paperattendance'));
	$PAGE->navbar->add(get_string('historytitle', 'local_paperattendance'), new moodle_url("/local/paperattendance/history.php", array("courseid" => $idcourse)));
	
	// Lists all records in the database
	if ($action == "view"){
		$getstudentattendances = "SELECT s.id, sm.date, CONCAT( m.initialtime, ' - ', m.endtime) AS hour, p.status, m.name
				FROM {paperattendance_session} AS s
				INNER JOIN {paperattendance_sessmodule} AS sm ON (s.id = sm.sessionid)
				INNER JOIN {paperattendance_module} AS m ON (sm.moduleid = m.id)
				INNER JOIN {paperattendance_presence} AS p ON (s.id = p.sessionid)
				INNER JOIN {user} AS u ON (u.id = p.userid)
				WHERE s.courseid = ? AND u.id = ?
				ORDER BY sm.date DESC";
	
		$attendances = $DB->get_records_sql($getstudentattendances, array($idcourse, $USER->id));
	
		$attendancestable = new html_table();
	
		if (count($attendances) > 0){
			$attendancestable->head = array(
					get_string('hashtag', 'local_paperattendance'),
					get_string('date', 'local_paperattendance'),
					get_string('module', 'local_paperattendance'),
					get_string('time', 'local_paperattendance'),
					get_string('attendance', 'local_paperattendance')
			);
			//A mere counter for the numbers of records
			$counter = 1;
			foreach ($attendances as $attendance){
				
				$urlattendance = new moodle_url("#");
				
				$presenticon = new pix_icon("i/valid", get_string('presentattendance', 'local_paperattendance'));
				
				$presenticonaction = $OUTPUT->action_icon(
						$urlattendance,
						$presenticon
						);
				
				$absenticon = new pix_icon("i/invalid", get_string('absentattendance', 'local_paperattendance'));
				
				$absenticonaction = $OUTPUT->action_icon(
						$urlattendance,
						$absenticon
						);
				//We check if the student is present or not
				if ($attendance->status == 1){
					
						$attendancestable->data[] = array(
						$counter,
						date("d-m-Y", $attendance->date),
						$attendance->name,
						$attendance->hour,
						$presenticonaction
						);
				}
				else {
					
						$attendancestable->data[] = array(
						$counter,
						date("d-m-Y", $attendance->date),
						$attendance->name,
						$attendance->hour,
						$absenticonaction
						);
				}
				$counter++;
			}
		}
	
		$backbuttonurl = new moodle_url("/course/view.php", array("id" => $idcourse));
	
	}
	
	$PAGE->set_title(get_string('historytitle', 'local_paperattendance'));
	$PAGE->set_heading(get_string('historyheading', 'local_paperattendance'));
	echo $OUTPUT->header();
	// Displays all the records and options
	if ($action == "view"){
	
		if (count($attendances) == 0){
			echo html_writer::nonempty_tag("h4", get_string('nonexistintingrecords', 'local_paperattendance'), array("align" => "left"));
		}
		else{
			echo html_writer::table($attendancestable);
		}
		echo html_writer::nonempty_tag("div", $OUTPUT->single_button($backbuttonurl, get_string('backtocourse', 'local_paperattendance')), array("align" => "left"));
	}
	
}
//Ends Student's view
else{
	print_error(get_string('error', 'local_paperattendance'));
}

echo $OUTPUT->footer();

