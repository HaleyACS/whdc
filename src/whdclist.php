<?php

/* WHDC webhook lister */

$DEBUG = false;
$result = "";
$content = "";
$error = array();
$func = "whdclist";

// Get date.
$date =  date("Y-m-d H:i:s");

// Load the token file.
include_once('/var/www/files/tokens.inc');

// Load the token file.
include_once('/var/www/files/auth.inc');


// Create handle for log file.
$handle = fopen("/var/www/logs/whdc.log", "a");

// Html header/start
$content = "<!DOCTYPE html>\n
<html xml:lang=\"en\" lang=\"en\">
";

// head
$content = "
<head>
<title>WHDC - Generic Webhook - Collector</title>
<link rel=\"stylesheet\" href=\"style.css\" type=\"text/css\" />
<SCRIPT LANGUAGE=\"JavaScript\" TYPE=\"text/javascript\" SRC=\"showhide.js\"></SCRIPT>
</head>
";

// Body start
$content .= "<body><div class=\"center\">";

$content .= "<table>";


if ((isset($_GET['Authorization'])) && (strlen($_GET['Authorization']) > 0)) {

  // Extract Token.
  $token = trim($_GET['Authorization']);
  $DEBUG && print "TOKEN: $token \n";

} else {
        header( "HTTP/1.1 401 Unauthorized" );
        // echo json_encode(["error" => "Authorization headers missing"]);
        $content .= "<table>
<tr class=\"h\">
<th>
<img src=\"webhook-logo.svg\" alt=\"WebHook Data Collector\" height=\"64\" width=\"64\">
\"Unauthenticated\" - problems to Jörg Mertin - 
<h1 class=\"p\">Received WebHook alerts</h1>
</th></tr>
<tr><td class=\"v\">
<div class=\"h2\">
Unauthenticated: Please submit the token identifier provided to your Webhook source!
</div class=\"h2\">
</td></tr>
</table>
";

        $content .= "<FORM action=\"whdclist.php\" method=\"get\" \>\n";
        $content .= "&nbsp; Token identifier: &nbsp;<input type=\"text\" class=\"text\" name=\"Authorization\" value=\"\" size=\"80\" maxlength=\"256\"> &nbsp;";
        $content .= "</FORM>";
        $content .= "</div></body>\n</html>";
        $content .= "</table>";
        print $content;
        exit;
}

// ==================================================================================================
// Extract Token key name, which is supposed to be a name of the wahtever app/DX OI system.
$token_sql = "SELECT * FROM whdc_tokens WHERE token='{$token}' ORDER BY date DESC";
$token_query =  mysqli_query($dbWhdc, $token_sql);
if (mysqli_error($dbWhdc)) {
    tolog("SQL/$func",  mysqli_error($dbWhdc), $handle);
}

$row = mysqli_fetch_assoc($token_query);

if ((isset($row['token'])) && (strlen($row['token'])) > 40) {
    
    // Validate token
    $is_token_valid = is_jwt_valid($row['token'], $secret);
    
} else {
    
    $is_token_valid = FALSE;
}


if ($is_token_valid === TRUE) {
    $DEBUG && print "Found token in access list \n"; 
    $token_name = $row['name'];
    // Table header
    $content .= "
<tr class=\"h\">
<th>
<img src=\"webhook-logo.svg\" alt=\"WebHook Data Collector\" height=\"64\" width=\"64\">
Welcome 
\"$token_name\" - problems to Jörg Mertin - 
<h1 class=\"p\">Received WebHook alerts</h1>
</th></tr>
<tr><td class=\"v\">
<div class=\"p\">
- The whdc lister will use \"Alarm Status\" and \"Alarm Name\" to build up the subject line. For Slack, it uses the \"text\" content.<br />
- Last 25 events will be shown. The system will delete old events automatically!
</div class=\"p\">
</td></tr>
</table>
";

} else {
    $line = "FATAL: Authentication error. No valid token provided. Access denied!";
    header( "HTTP/1.1 401 Unauthorized" );
    tolog("AUTH/$func",  $line, $handle);
    
    $content .= "<FORM action=\"whdclist.php\" method=\"post\" \>\n";
    $content .= "&nbsp; Token : &nbsp;<input type=\"text\" class=\"text\" name=\"Authorization\" value=\"\" size=\"80\" maxlength=\"256\"> &nbsp;";
    $content .= "</FORM>";
    $content .= "<tr><td class=\"v\">$line</td></tr>";
    $content .= "</div></body>\n</html>";
    $content .= "</table>";
    print $content;
    exit;
}

// Database structure as below
/*
  $query = "CREATE TABLE IF NOT EXISTS whdc (
       id INTEGER PRIMARY KEY AUTOINCREMENT,
       token VARCHAR NOT NULL,
       subject VARCHAR NOT NULL,
       rem_address VARCHAR NOT NULL,
       json_base64 TEXT NOT NULL,
       logs_base64 TEXT NOT NULL,
       date DATETIME NOT NULL
    )";
*/

$data_sql = "SELECT * FROM whdc WHERE tenant_name='{$token_name}' ORDER BY date DESC";
$data_query =  mysqli_query($dbWhdc, $data_sql);
if (mysqli_error($dbWhdc)) {
    tolog("SQL/$func",  mysqli_error($dbWhdc), $handle);
}

$count = 1;
while ($row = mysqli_fetch_assoc($data_query)) {
    // Handy for troubleshooting
    //$line =  "ID: {$row['id']}, Remote IP: {$row['rem_address']}, Subject: {$row['subject']}, Date: {$row['date']}<br>";
    //fwrite($handle, "$date - $line \n");
    $DEBUG && print_r ($row);

    $fixed_tmp = json_decode(base64_decode($row['json_base64']), true);
    if (isset($fixed_tmp['Alarm Status'])) {
        $status = $fixed_tmp['Alarm Status'];
    } else {
        if (isset($fixed_tmp['text'])) {
            // Probably a slack message. Taking all out of text.
            $status = "Slack";
            $fixed_tmp['text'] = preg_replace("/\\n /m", "<br /> ", $fixed_tmp['text']); 
        } else {
            $status = "Unknown";
        }
    }
    
    $logs_id = $row['id'] . "_logs";
    $log_link = "<A href=\"javascript:hideshow(document.getElementById('{$logs_id}'))\">Headers</A>";
    $logs =base64_decode($row['logs_base64']);
    $log_link .= "<div id='{$logs_id}' style=\"white-space; pre-wrap; display: none\" align=\"left\" ><pre>$logs</pre></div>";
    
    $content .= "<table>";
    $content .= "<tr class=\"h\"><td colspan=\"2\"> $status - " . str_replace("_", " ", $row['subject']) . "</td></tr>";
    $content .= "<tr><td class=\"e\">Recording Date GMT</td><td class=\"v\"> {$row['date']}   </td></tr>";
    $content .= "<tr><td class=\"e\">Sender </td><td class=\"v\"> {$row['tenant_name']} from {$row['rem_address']}</td></tr>";
    $fixed_text = json_encode($fixed_tmp, JSON_PRETTY_PRINT);
    
    $content .= "<tr><td class=\"v\" colspan=\"2\"><pre>$fixed_text</pre></td></tr>";
    $content .= "<tr><td class=\"v\" colspan=\"2\"> => Troubleshooting: {$log_link} <br /></td></tr>";
    $content .= "</table>";
    $count++;

} // While loop

if ($count == 1) {
    $content .= "<table>";
    $content .= "<tr class=\"h\"><td>No requests for this Tenant have been recorded! - displaying last 20 log entries</td></tr>";

    // Extract last 10 lines
    $file = file("/var/www/logs/whdc.log");
    $data = array_slice(file('/var/www/logs/whdc.log'), -20);
    $logcontent = "";
    $linecnt = 1;
    foreach ($data as $line) {
        $logcontent.= $line;
        $linecnt++;
    }
    if ($linecnt == 1) {
        $logcontent = ' FATAL => Logfile empty. No data!';
    }

    $content .= "<tr class=\"v\"><td><PRE>$logcontent</PRE></td></tr>";
    
    $content .= "</table>";
}

// ==================================================================================================
$content .= "</div></body>\n</html>";

// Close all
fclose($handle);
print "$content";


?>
