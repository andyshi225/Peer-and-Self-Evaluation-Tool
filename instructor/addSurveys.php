<?php

//error logging
error_reporting(-1); // reports all errors
ini_set("display_errors", "1"); // shows all errors
ini_set("log_errors", 1);
ini_set("error_log", "~/php-error.log");

// //start the session variable
session_start();

// //bring in required code
require_once "../lib/database.php";
require_once "../lib/constants.php";
require_once "../lib/infoClasses.php";
require_once "../lib/fileParse.php";


// //query information about the requester
$con = connectToDatabase();

// //try to get information about the instructor who made this request by checking the session token and redirecting if invalid
$instructor = new InstructorInfo();
$instructor->check_session($con, 0);


// store information about courses as array of array
$courses = array();

// get information about the courses
$stmt = $con->prepare('SELECT id, code, name, semester, year FROM course WHERE instructor_id=? ORDER BY year DESC, semester DESC');
$stmt->bind_param('i', $instructor->id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc())
{
  $course_info = array();
  $course_info['code'] = $row['code'];
  $course_info['name'] = $row['name'];
  $course_info['semester'] = SEMESTER_MAP_REVERSE[$row['semester']];
  $course_info['year'] = $row['year'];
  $course_info['id'] = $row['id'];
  array_push($courses, $course_info);
}

//stores error messages corresponding to form fields
$errorMsg = array();

// set flags
$course_id = NULL;
$rubric_id = NULL;
$start_date = NULL;
$end_date = NULL;
$start_time = NULL;
$end_time = NULL;
$pairing_mode = NULL;

if($_SERVER['REQUEST_METHOD'] == 'POST')
{
  
  // make sure values exist
  if (!isset($_POST['pairing-mode']) or !isset($_FILES['pairing-file']))
  {
    http_response_code(400);
    echo "Bad Request: Missing parmeters.";
    exit();
  }
  
  // check the pairing mode
  $pairing_mode = trim($_POST['pairing-mode']);
  if (empty($pairing_mode))
  {
    $errorMsg['pairing-mode'] = 'Please choose a valid mode for the pairing file.';
  }
  else if ($pairing_mode != '1' and $pairing_mode != '2')
  {
    $errorMsg['pairing-mode'] = 'Please choose a valid mode for the pairing file.';
  }
  
  // check for any uploaded file errors
  if ($_FILES['pairing-file']['error'] == UPLOAD_ERR_INI_SIZE)
  {
    $errorMsg['pairing-file'] = 'The selected file is too large.';
  }
  else if ($_FILES['pairing-file']['error'] == UPLOAD_ERR_PARTIAL)
  {
    $errorMsg['pairing-file'] = 'The selected file was only paritally uploaded. Please try again.';
  }
  else if ($_FILES['pairing-file']['error'] == UPLOAD_ERR_NO_FILE)
  {
    $errorMsg['pairing-file'] = 'A pairing file must be provided.';
  }
  else if ($_FILES['pairing-file']['error'] != UPLOAD_ERR_OK)
  {
    $errorMsg['pairing-file'] = 'An error occured when uploading the file. Please try again.';
  }
  // start parsing the file
  else
  {
    // start parsing the file
    $file_string = file_get_contents($_FILES['pairing-file']['tmp_name']);
    
    // catch errors or continue parsing the file
    if ($file_string === false)
    {
      $errorMsg['pairing-file'] = 'An error occured when uploading the file. Please try again.';
    }
    else
    {
      $data = parse_pairings($pairing_mode, $file_string);
      echo var_dump($data);
    }
  }
  
  
  // check if main fields are valid
  if (!empty($errorMsg))
  {
    
    //check course is not empty
    $course_id = trim($_POST['course-id']);
    if (empty($course_id))
    {
      $errorMsg['course-id'] = "Please choose a course.";
    }
    
    //must handle case for empty question bank ("rubric-id") here!

    //check that dates are not empty
    $start_date = trim($_POST['start-date']);
    $end_date = trim($_POST['end-date']);
    date_default_timezone_set('America/New_York');
    $currentDate = date('Y/m/d');
   
    if(empty($start_date))
    {
      //check that dates are not empty. 
      $errorMsg['start-date'] = "Please choose a start date.";
    } elseif(strtotime($start_date) < strtotime($currentDate) && strtotime($currentDate) != '0000-00-00') 
    { 
      //If not empty, check they haven't already passed
      $errorMsg['start-date'] = "Start date has already passed."; 
    } 

    if(empty($end_date))
    {
      //check that dates are not empty. 
      $errorMsg['end-date'] = "Please choose a end date.";
    } elseif(strtotime($end_date) < strtotime($currentDate) && strtotime($currentDate) != '0000-00-00')
    {
      //If not empty, check they haven't already passed
      $errorMsg['end-date'] = "End date has already passed.";
    }
    
    //Check  date formatting is correct.
    if(strtotime($start_date) > strtotime($end_date) && !empty($start_date) && !empty($end_date))
    { 
      $errorMsg['start-date'] = "Start date cannot be after the end date."; 
      $errorMsg['end-date'] = "End date cannot be before the start date.";
    } 

    

    
    $start_time = trim($_POST['start-time']);
    $end_time = trim($_POST['end-time']);
    $currentTime = date('G:i');
    
    if(empty($start_time))
    {
      //check that start time isn't empty. 
      $errorMsg['start-time'] = "Please choose a start time.";
    } 
    elseif(strtotime($start_date) == strtotime($currentDate) && strtotime($start_time) <= strtotime($currentTime)) {
      //if its the current day, make sure time hasn't already passed.
      $errorMsg['start-time'] = "Start time has already passed.";
    }
    
    if(empty($end_time))
    {
      //check that end time isn't empty. 
      $errorMsg['end-time'] = "Please choose a end time.";
    } 
    elseif(strtotime($end_date) == strtotime($currentDate) && strtotime($end_time) <= strtotime($currentTime)) {
       //if its the current day, make sure time hasn't already passed.
       $errorMsg['end-time'] = "End time has already passed.";
    }

    if(strtotime($start_time) > strtotime($end_time) && !empty($start_time) && !empty($end_time) && strtotime($start_date) == strtotime($end_date) && !empty($start_date) && !empty($end_date))
    { 
      //if its the same day, make sure timing format is correct
      $errorMsg['start-time'] = "Start time cannot be after the end time."; 
      $errorMsg['end-time'] = "End time cannot be before the start time.";
    } 
  
  }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="https://www.w3schools.com/lib/w3-theme-blue.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="../styles/addSurveys.css">
    <title>Add Surveys</title>
</head>
<body>

<div class="w3-container w3-center">
    <h2>Survey Information</h2>
</div>


<span class="w3-card w3-red"><?php if(isset($errorMsg["duplicate"])) {echo $errorMsg["duplicate"];} ?></span>
<form action="addSurveys.php" method ="post" enctype="multipart/form-data" class="w3-container">
    <span class="w3-card w3-red"><?php if(isset($errorMsg["course-id"])) {echo $errorMsg["course-id"];} ?></span><br />
    <label for="course-id">Course:</label><br>
    <select id="course-id" class="w3-select w3-border" style="width:61%" name="course-id"><?php if ($course_id) {echo 'value="' . htmlspecialchars($course_id) . '"';} ?>
        <option value="0" disabled <?php if (!$course_id) {echo 'selected';} ?>>Select Course</option>
        <?php
        foreach ($courses as $course) {
          echo '<option value="' . $course['id'] . '">' . $course['code'] . ' ' . $course['name'] . ' - ' . $course['semester'] . ' ' . $course['year'] . '</option>';
        }
        ?>
    </select><br><br>
    
    <span class="w3-card w3-red"><?php if(isset($errorMsg["rubric-id"])) {echo $errorMsg["rubric-id"];} ?></span><br />
    <label for="rubric-id">Question Bank:</label><br>
    <select class="w3-select w3-border" style="width:61%" name="rubric-id" id="rubric-id" disabled>
        <option value="0" selected>Default</option>
    </select><br><br>

    <span class="w3-card w3-red"><?php if(isset($errorMsg["start-date"])) {echo $errorMsg["start-date"];} ?></span><br />
    <label for="start-date">Start Date:</label><br>
    <input type="date" id="start-date" class="w3-input w3-border" style="width:61%" name="start-date" <?php if ($start_date) {echo 'value="' . htmlspecialchars($start_date) . '"';} ?>><br>
    
    <span class="w3-card w3-red"><?php if(isset($errorMsg["start-time"])) {echo $errorMsg["start-time"];} ?></span><br />
    <label for="start-time">Start time:</label><br>
    <input type="time" id="start-time" class="w3-input w3-border" style="width:61%" name="start-time" <?php if ($start_time) {echo 'value="' . htmlspecialchars($start_time) . '"';} ?>><br>

    <span class="w3-card w3-red"><?php if(isset($errorMsg["end-date"])) {echo $errorMsg["end-date"];} ?></span><br />
    <label for="end-date">End Date:</label><br>
    <input type="date" id="end-date" class="w3-input w3-border" style="width:61%" name="end-date" <?php if ($end_date) {echo 'value="' . htmlspecialchars($end_date) . '"';} ?>><br>
    
    <span class="w3-card w3-red"><?php if(isset($errorMsg["end-time"])) {echo $errorMsg["end-time"];} ?></span><br />
    <label for="end-time">End time:</label><br>
    <input type="time" id="end-time" class="w3-input w3-border" style="width:61%" name="end-time" <?php if ($end_time) {echo 'value="' . htmlspecialchars($end_time) . '"';} ?>><br>

    <span class="w3-card w3-red"><?php if(isset($errorMsg["pairing-mode"])) {echo $errorMsg["pairing-mode"];} ?></span><br />
    <label for="pairing-mode">Pairing File Mode:</label><br>
    <select id="pairing-mode" class="w3-select w3-border" style="width:61%" name="pairing-mode">
        <option value="1" <?php if (!$pairing_mode) {echo 'selected';} ?>>Raw</option>
        <option value="2" <?php if ($pairing_mode == 2) {echo 'selected';} ?>>Team</option>
    </select><br><br>
    
    <span class="w3-card w3-red"><?php if(isset($errorMsg["pairing-file"])) {echo $errorMsg["pairing-file"];} ?></span><br />
    <label for="pairing-file">Pairings (CSV File):</label><br>
    <input type="file" id="pairing-file" class="w3-input w3-border" style="width:61%" name="pairing-file"><br>

    <input type="submit" class="w3-button w3-blue" value="Create Survey">
</form>
</body>
</html>