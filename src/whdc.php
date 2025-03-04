<?php

// In both cases below - mnake sure you have enouth space on disk! Logs cat grow fast
$DEBUG = false; // Setting to true, the script will dump all kind of info to the screen.
$INFO = false; // writes json + headers into log info log file.

// Var declaration
$result = "";

// Get date.
$date =  date("Y-m-d H:i:s");

// DB File
$sqdb_file = '/var/www/files/whdc_database.db';

// Load the token file holding the secret used to create tokens.
// This file is created at deployment.
include_once('/var/www/files/tokens.inc');

// Authentication code used by all php scripts.
include_once('/var/www/files/auth.inc');


// Create handle for log file.
$handle = fopen("/var/www/logs/whdc.log", "a");

// Create handle for info-log file.
$info = fopen("/var/www/logs/whdc-info.log", "a");

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
// Opening sqlite DB
$database = new SQLite3("$sqdb_file", SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);

// Errors are emitted as warnings by default, enable proper error handling.
$database->enableExceptions(true);
$database->exec('PRAGMA journal_mode = wal;');
$database->exec("PRAGMA busy_timeout=5000");

// Extract Token key name, which is supposed to be a name of the wahtever app/DX OI system.
$query = "SELECT * FROM whdc_tokens WHERE token='{$token}' ORDER BY date DESC LIMIT 1";
$DEBUG && print "$query \n";

$result = $database->query($query);
$row = $result->fetchArray(SQLITE3_ASSOC);
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
    $DEBUG && print "Found token in access list \n";
    // Extract Token key name, which is supposed to be a name of the wahtever app/DX OI system.
    $token_name = $row['name'];
} else {
    $line = "FATAL: Authentication error. No valid token provided. Access denied!";
    header( "HTTP/1.1 401 Unauthorized" );
    fwrite($handle, "$date - $line \n");
    print "$line \n";
    $database->close();
    fclose($handle);
    exit;
}

// Extract remote IP adress of sender
$remip = $_SERVER['REMOTE_ADDR'];

// Get remote IP (Public one) - I using a proxy, try using this.
// $remip = $_SERVER['HTTP_X_FORWARDED_FOR'];

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
$result_sql = "INSERT INTO whdc (subject, token, rem_address, json_base64, logs_base64, date) VALUES ('{$subject}','{$token_name}','{$remip}','{$encoded_payload}','$logs_base64','{$date}')";
$DEBUG && print "printing SQL insert: \n";
$DEBUG &&  print "$result_sql \n";

// Perform the insert
$database->exec($result_sql);

// Write into log file.
$line =  "Remote IP: {$remip}, Subject: {$subject}";
fwrite($handle, "$date - $line \n");
$DEBUG && fwrite($handle, "$date - Dataset inserted \n");

// Write detail into INFO file
if ($INFO) {
    fwrite($info, "$date - $line \n");
    $jsonline = print_r($decoded, true);
    fwrite($info, " => JSON payload \n $jsonline \n");
    $headers = getallheaders();
    $headers = print_r($headers, true);
    fwrite($info, " => Headers \n $headers \n\n");
}


// Clean up DB - we only want 25 entries max.
// Identify from which entry we want to remove stuff.
$count = "SELECT id FROM whdc WHERE token='{$token_name}' ORDER BY ID DESC LIMIT 1 OFFSET 24;";
$DEBUG && fwrite($handle, "$date - SQL Count: $count \n");
$limit = $database->querySingle($count);

if (strlen($limit) > 0) {
    $delete = "DELETE FROM whdc WHERE id < $limit AND token='{$token_name}';";
    $DEBUG && fwrite($handle, "$date - SQL delete: $delete \n");
    $database->exec($delete);
}

    
$database->close();
fclose($handle);
fclose($info);

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
