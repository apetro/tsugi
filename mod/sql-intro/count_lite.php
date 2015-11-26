<?php

use \Tsugi\Core\LTIX;
use \Tsugi\Util\LTI;
use \Tsugi\Util\Mersenne_Twister;

$MAX_UPLOAD_FILE_SIZE = 100000;

require_once "sql_util.php";


$answer = array(
"iupui.edu" => 536,
"umich.edu" => 491,
"indiana.edu" => 178,
"caret.cam.ac.uk" => 157,
"vt.edu" => 110
);

$oldgrade = $RESULT->grade;

if ( isset($_FILES['database']) ) {
    $fdes = $_FILES['database'];

    // Check to see if they left off the file
    if( $fdes['error'] == 4) {
        $_SESSION['error'] = 'Missing file, make sure to select a file before pressing submit';
        header( 'Location: '.addSession('index.php') ) ;
        return;
    }

    if ( $fdes['size'] > $MAX_UPLOAD_FILE_SIZE ) {
        $_SESSION['error'] = "Uploaded file must be < $MAX_UPLOAD_FILE_SIZE bytes";
        header( 'Location: '.addSession('index.php') ) ;
        return;
    }

    if ( ! endsWith($fdes['name'],'.sqlite') ) {
        $_SESSION['error'] = "Uploaded file must have .sqlite suffix: ".$fdes['name'];
        header( 'Location: '.addSession('index.php') ) ;
        return;
    }
    $file = $fdes['tmp_name'];


    $fh = fopen($file,'r');
    $prefix = fread($fh, 100);
    fclose($fh);
    if ( ! startsWith($prefix,'SQLite format 3') ) {
        $_SESSION['error'] = "Uploaded file is not SQLite3 format: ".$fdes['name'];
        header( 'Location: '.addSession('index.php') ) ;
        return;
    }

    $db = new SQLite3($file);
    $results = $db->query('SELECT org, count FROM Counts ORDER BY count DESC LIMIT 5');
    $good = 0;

    while ($row = $results->fetchArray()) {
        if ( !isset($answer[$row[0]]) ) continue;
        if ( $answer[$row[0]] != $row[1] ) continue;
        $good++;
    }

    if ( $good < 5 ) {
        $_SESSION['error'] = "Data is incorrect: ".$fdes['name'];
        header( 'Location: '.addSession('index.php') ) ;
        return;
    }

    $gradetosend = 1.0;
    $scorestr = "Your answer is correct, score saved.";
    if ( $dueDate->penalty > 0 ) {
        $gradetosend = $gradetosend * (1.0 - $dueDate->penalty);
        $scorestr = "Effective Score = $gradetosend after ".$dueDate->penalty*100.0." percent late penalty";
    }
    if ( $oldgrade > $gradetosend ) {
        $scorestr = "New score of $gradetosend is < than previous grade of $oldgrade, previous grade kept";
        $gradetosend = $oldgrade;
    }

    // Use LTIX to send the grade back to the LMS.
    $debug_log = array();
    $retval = LTIX::gradeSend($gradetosend, false, $debug_log);
    $_SESSION['debug_log'] = $debug_log;

    if ( $retval === true ) {
        $_SESSION['success'] = $scorestr;
    } else if ( is_string($retval) ) {
        $_SESSION['error'] = "Grade not sent: ".$retval;
    } else {
        echo("<pre>\n");
        var_dump($retval);
        echo("</pre>\n");
        die();
    }

    // Redirect to ourself
    header('Location: '.addSession('index.php'));
    return;
}

if ( $LINK->grade > 0 ) {
    echo('<p class="alert alert-info">Your current grade on this assignment is: '.($LINK->grade*100.0).'%</p>'."\n");
}

if ( $dueDate->message ) {
    echo('<p style="color:red;">'.$dueDate->message.'</p>'."\n");
}
?>
<p>
<form name="myform" enctype="multipart/form-data" method="post" >
To get credit for this assignment, perform the instructions below and 
upload your SQLite3 database here: <br/>
<input name="database" type="file"> 
(Must have a .sqlite suffix and be &lt; 100K in size)<br/>
Hint: The top organizational count is <?= $answer['iupui.edu'] ?>.<br/>
<input type="submit">
</form>
</p>
<h1>Counting Organizations</h1>
<p>
This application will read the mailbox data (mbox.txt) count up the
number email messages per organization (i.e. domain name of the email
address) using a database with the following schema to maintain the counts.
<pre>
CREATE TABLE Counts (org TEXT, count INTEGER)
</pre>
When you have run the program on <b>mbox.txt</b> upload the resulting
database file above for grading.
</p>
<p>
If you run the program multiple times in testing or with dfferent files, 
make sure to empty out the data before each run.
<p>
You can use this code as a starting point for your application:
<a href="http://www.pythonlearn.com/code/emaildb.py" target="_blank">
http://www.pythonlearn.com/code/emaildb.py</a>.  
The data file for this application is the same as in previous assignments:
<a href="http://www.pythonlearn.com/code/mbox.txt" target="_blank">
http://www.pythonlearn.com/code/mbox.txt</a>.  
</p>