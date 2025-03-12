<?php

// In both cases below - mnake sure you have enouth space on disk! Logs cat grow fast
$DEBUG = false; // Setting to true, the script will dump all kind of info to the screen.
$INFO = false; // writes json + headers into log info log file.

// Var declaration
$result = "";

// Get date.
$date =  date("Y-m-d H:i:s");

// Load the token file holding the secret used to create tokens.
// This file is created at deployment.
include_once('/var/www/files/tokens.inc');

// Authentication code used by all php scripts.
include_once('/var/www/files/auth.inc');


// Create handle for log file.
$handle = fopen("/var/www/logs/whdc.log", "a");

// Create handle for info-log file.
$infolog = fopen("/var/www/logs/whdc-info.log", "a");

// Extract remote IP adress of sender
$remip = $_SERVER['REMOTE_ADDR'];

// Get remote IP (Public one) - I using a proxy, try using this.
// $remip = $_SERVER['HTTP_X_FORWARDED_FOR'];

if ($DEBUG == true) {
    $result = "$date whdc submission - POST array below: \n";
    $result .= "======================================================================\n";
    $result .= print_r($_POST, true) . "\n";
    print "$result \n";
    //$result .= phpinfo();
}


//================  Security - Token extraction and access check. =====================
$headers = getallheaders();

if ((isset($headers['Authorization'])) && (strlen($headers['Authorization']) > 0)) {

    // Check if bearer token is available.
    if (substr($headers['Authorization'], 0, 7) === 'Bearer ') {
        $token = trim(substr($headers['Authorization'], 6));
        $DEBUG && print "Baerer TOKEN: \n\"$token\" \n";
    } else {
        // else go without
        $token = trim($headers['Authorization']);
        $DEBUG && print "TOKEN: \n\"$token\" \n";
    }

} else {
    // Reject - because no authorization header provided.
    header( "HTTP/1.1 401 Unauthorized" );
    echo json_encode(["error" => "Authorization headers missing"]);
    exit;
}

// ==================================================================================================
// Extract Token key name, which is supposed to be a name of the wahtever app/DX OI system.
$token_sql = "SELECT * FROM whdc_tokens WHERE token='{$token}' ORDER BY date DESC LIMIT 1";
$DEBUG && print "$query \n";

$token_query =  mysqli_query($dbWhdc, $token_sql);
if (mysqli_error($dbWhdc)) {
    tolog("SQL/$func", "$remip " . mysqli_error($dbWhdc), $handle);
}
$row = mysqli_fetch_assoc($token_query);
/* Used for deep troubleshooting only.
  if ($database->LastErrorCode()) {
  fwrite($handle, "$date - ERROR: " . $database->LastErrorMsg() . "\n");
  fwrite($handle, "$date - SQL: $query \n");
  fwrite($handle, "$date - RESULT: " . print_r($row, true) . " \n");
  $headers = getallheaders();
  fwrite($handle, "$date - RESULT: " . print_r($headers, true) . " \n");
  }
*/
// Print it out in debug mode.
$DEBUG && print_r($row);

// Check if we have a match.
if ((isset($row['token'])) && (strlen($row['token'])) > 40) {

    // Validate token
    $is_token_valid = is_jwt_valid($row['token'], $secret);

} else {
    $is_token_valid = FALSE;
}

// Check if we have a registered/valid token
if ($is_token_valid === TRUE) {
    $DEBUG && print "$remip Found token in access list \n";
    // Extract Token key name, which is supposed to be a name of the wahtever app/DX OI system.
    $token_name = $row['name'];
} else {
    $line = "FATAL: $remip Authentication error. No valid token provided. Access denied!";
    header( "HTTP/1.1 401 Unauthorized" );
    tolog("AUTH/$func", "$remip " . $line, $handle);
    tolog("TOKEN/$func", "$remip " . $headers['Authorization'], $handle);
    print "$line \n";
    fclose($handle);
    exit;
}

// ===== Actual Code ===========================================================

// make sure we can read the json code.
$json = file_get_contents('php://input');

if (strlen($json) < 1) {
    // Handle exception where user has not provided the Alarm name
    $subject = "No data provided";
    $encoded_payload = "{ \"Alarm Name\": \"$subject\"}";
} else {
    // Extract json and compute title if possible
    $decoded = json_decode($json, true);
    $encoded_payload = base64_encode($json);
    if (isset($decoded['Alarm Name'])) {
        $subject = $decoded['Alarm Name'];
    } else {
        // In case we have slack message - it will all be in the text tag.
        if (isset($decoded['text'])) {
            // we have a text field -> probably Slack
            $subject = "{$decoded['text']}";
        } else {
            $subject = "No \"Alarm Name\" field provided";
        }
    }
}


// Grab logs
$logs_base64 = logs_catcher("encode", $decoded);

// Get date.
$date =  date("Y-m-d H:i:s");

// Compute SQL code to insert data into sqlite DB.
$result_sql = "INSERT INTO whdc (subject, tenant_name, rem_address, json_base64, logs_base64, date) VALUES ('{$subject}','{$token_name}','{$remip}','{$encoded_payload}','$logs_base64','{$date}')";
$DEBUG && print "printing SQL insert: \n";
$DEBUG &&  print "$result_sql \n";

// Perform the insert
$result_query =  mysqli_query($dbWhdc, $result_sql);
if (mysqli_error($dbWhdc)) {
    tolog("SQL/$func", "$remip " . mysqli_error($dbWhdc), $handle);
}

// Write into log file.
$line =  "From \"{$remip}\" for \"{$token_name}\", Subject: {$subject}";
tolog("WHDC/$func", "$remip " . $line, $handle);

// Write detail into INFO file
if ($INFO) {
    tolog("INFO/$func", "$remip " . $line, $infolog);
    $jsonline = print_r($decoded, true);
    tolog("INFO/$func", "$remip " . " => JSON payload \n $jsonline", $infolog);
    $headers = getallheaders();
    $headers = print_r($headers, true);
    tolog("INFO/$func", "$remip " . " => Headers \n $headers \n\n", $infolog);

}


// Clean up DB - we only want 25 entries max.
// Identify from which entry we want to remove stuff.
$count_sql = "SELECT id FROM whdc WHERE tenant_name='{$token_name}' ORDER BY ID DESC LIMIT 1 OFFSET 24;";
$count_query =  mysqli_query($dbWhdc, $count_sql);
if (mysqli_error($dbWhdc)) {
    tolog("SQL/$func", "$remip " . mysqli_error($dbWhdc), $handle);
}

$entries = mysqli_num_rows($count_query);
$DEBUG && tolog("SQL/$func", "$remip " . "$entries row(s) to delete.", $handle);

// In case previous query gave some resulting row, check on the deletion function.
if ($entries > 0) {
    
    $row = mysqli_fetch_assoc($count_query);

    $limit = $row['id'];

    if (strlen($limit) > 0) {
        $delete_sql = "DELETE FROM whdc WHERE id < $limit AND tenant_name='{$token_name}';";
        $DEBUG && tolog("SQL/$func", "$remip " . "$delete_sql", $handle);
        $delete_query =  mysqli_query($dbWhdc, $delete_sql);
        if (mysqli_error($dbWhdc)) {
            tolog("SQL/$func", "$remip " . mysqli_error($dbWhdc), $handle);
        }
    }

}

fclose($handle);
fclose($infolog);

// Functions below

// logs_catcher
// Grabs logs to display or to encode base64
// action : encode / decode
// payload: to encode or decode.
function logs_catcher($action, $payload="") {
    global $_SERVER, $_POST, $_GET, $date;
    
    if ("$action" == "decode") {
        
        return(base64_decode($payload));
        
    } else {
        
        // Extract the data.
        $headers = getallheaders();
        $resp = print_r($headers, true);
        $post = print_r($_POST, true);
        $get = print_r($_GET, true);
        // Format that data
        $logentry = "\n <br />*** $date \n";
        $logentry .= "==> Headers ===================================================\n{$resp} \n";
        $logentry .= "==> GET =======================================================\n{$get} \n";
        $logentry .= "==> POST ======================================================\n{$post} \n";
        $_SERVER['PASSWORD'] = "**********";
        $_SERVER['HTTP_AUTHORIZATION'] = "**********";
        $_SERVER['GPG_KEYS'] = "**********";
        $_SERVER['PHP_SHA256'] = "**********";
        $server = print_r($_SERVER, true);
        $logentry .= "==> SERVER ====================================================\n {$server} \n";
        // encode the data
        return(base64_encode($logentry));

    } 

} // logs_catcher


?>
