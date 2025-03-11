<?php

/* ====================================================
 * WHDC WebHook Data Collector administration Interface
 */

$DEBUG = false;
$result = "";
$content = "";
$status = "";
$message = "Unknown";
$error = array();
$error['query'] = "";
$error['del'] = "";
$error['mail'] = "";
$error['name'] = "";

// tokens file.
include_once('/var/www/files/tokens.inc');

// tokens file.
include_once('/var/www/files/auth.inc');

// Create handle for log file.
$handle = fopen("/var/www/logs/whdc.log", "a");

// Grab that out of the env.
$username = getenv('USERNAME');
$known_pwd_hash = getenv('PASSWORD');
$date =  date("Y-m-d H:i:s");

$content = "<!DOCTYPE html>
";

// head
$content = "
<html xml:lang=\"en\" lang=\"en\">
<body><div class=\"center\">
<head>
<meta charset=\"UTF-8\" />
<title>WHDC - Generic Webhook Collector Manager</title>
<link rel=\"stylesheet\" href=\"style.css\" type=\"text/css\" />
<SCRIPT LANGUAGE=\"JavaScript\" TYPE=\"text/javascript\" SRC=\"showhide.js\"></SCRIPT>
</head>
";


// Create admin access PWD during boot.
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="Webhook Collector Token Manager"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<body><h1>Authentication cancelled, well, thanks for the fish... Solong!</h1></body></html>';
    $logtext = "No username provided";
    tolog("AUTH", $logtext, $handle);
    exit;
    
} else {

    // Set value to fakse.
    $auth_check = false;
    if ((isset($_SERVER['PHP_AUTH_USER'])) && ($_SERVER['PHP_AUTH_USER'] == $username)) {
        $auth_check = true;
        $logtext = "Valid username";
        $DEBUG && tolog("AUTH", $logtext, $handle);
    } else {
        $auth_check = false;
        $logtext = "Login name incorrect.";
        $auth_message = "$date - $logtext";
        $DEBUG && tolog("AUTH", $logtext, $handle);
    }

    $provided_pwd_hash = hash('sha256', $_SERVER['PHP_AUTH_PW']);
    if ((isset($_SERVER['PHP_AUTH_PW'])) && ($provided_pwd_hash == $known_pwd_hash)) {
        $auth_check = true;
        $logtext = "Valid password.";
        $DEBUG && tolog("AUTH", $logtext, $handle);
    } else {
        $auth_check = false;
        $logtext = "Invalid password.";
        $auth_message = "$date - $logtext";
        $DEBUG && tolog("AUTH", $logtext, $handle);
    }

    if ($auth_check) {
        $message = ucfirst($username);
        $logtext = "Login by $message granted.";
        $DEBUG && tolog("AUTH", $logtext, $handle);

    } else {
        // Clear global variables.
        unset($_SERVER['PHP_AUTH_USER']);
        unset($_SERVER['PHP_AUTH_PW']);
        header('WWW-Authenticate: Basic realm="Webhook Collector Token Manager"');
        header('HTTP/1.0 401 Unauthorized');
        echo "<body><h1>$auth_message <br />Well, thanks for the fish... Solong!</h1></body></html>";
        $DEBUG && tolog("AUTH", $auth_message, $handle);
        $DEBUG && tolog("PROVIDED", $provided_pwd_hash, $handle);
        $DEBUG && tolog("COMPARED", $known_pwd_hash, $handle);
        exit;
    }
}

$content = "<!DOCTYPE html>";
// head
$content = "
<html xml:lang=\"en\" lang=\"en\">
<head>
<title>WHDC - Generic Webhook Collector Manager</title>
<link rel=\"stylesheet\" href=\"style.css\" type=\"text/css\" />
<SCRIPT LANGUAGE=\"JavaScript\" TYPE=\"text/javascript\" SRC=\"showhide.js\"></SCRIPT>
</head>
";

// Body start
$content .= "<body><div class=\"center\">";

// Table header
$content .= "<table>
<tr class=\"h\"><th>
<img src=\"webhook-logo.svg\" alt=\"WebHook Collector Token Manager\" height=\"64\" width=\"64\">
- Welcome \"$message\" - problems to Jörg Mertin - 
<h1 class=\"p\">WebHook Collector Token Manager</h1>
</th></tr>
</table>
";

$instructions = "1. Create a new token entry. Provide the identifier (tenant name + extra tag) and E-Mail of submitter (Used to identify the submitter)<br />";
$instructions .= "2. Go on the just created entry line, open the  \"Show token\" box and copy the token<br />";
$instructions .= "3. In DX O2 Notification channel configuration, select \"Token authentication\" as Authentication Type, and paste this token in the Token field.<br />&nbsp; &nbsp; As \"Webhook (generic) URL\", use: <b><kbd>https://{$_SERVER['HTTP_HOST']}/whdc.php</kbd></b> <br />";
$instructions .= "4. Select \"View # rows\" link on the just created tenant line and see the content of the test request.<br />";
$instructions .= "<br /><b>WARNING</b>: Deleting an entry is straigh ahead. No warning, no questions asked!";

$row_id = "Instructions_1";
$help_link = "<b><A href=\"javascript:hideshow(document.getElementById('Instructions_1'))\">Instructions</A></b>";
$help_link .= "<div id='{$row_id}' style=\"white-space; pre-wrap; display: none\" align=\"left\" >$instructions</div><br />";

$content .= "<table><tr><td>" .  $help_link . "</td></tr></table>";
$content .= "<FORM action=\"whdctokens.php\" method=\"post\" \>\n";
$content .= "<table>";
$content .= "<tr class=\"e\">";
$content .= "<th>Tenant name + WebHook channel identifier</th>";
$content .= "<th>Owner E-Mail</th>";
$content .= "<th>&nbsp;</th>";
$content .= "</tr>";
$content .= "<tr class=\"h\">";
$content .= "<td class=\"v\">Identifier: <input type=\"text\" class=\"text\" name=\"name\" value=\"\" size=\"20\" maxlength=\"80\"></td>";
$content .= "<td class=\"v\">Mail: <input type=\"text\" class=\"text\" name=\"mail\" value=\"\" size=\"30\" maxlength=\"80\"></td>";
$content .= "<td class=\"v\"><input type=\"submit\" name=\"submit\" value=\"Save\"/ class=\"submit\"></td>";
$content .= "</tr";
$content .= "</table>";
$content .= "</FORM>";


$mail = array();
$name = array();
$submit = true;

// Processing submit
if ((isset($_POST['submit'])) && ("{$_POST['submit']}" == "Save")) {
    // Get date.
    $date =  date("Y-m-d H:i:s");

    // Check E-Mail
    $mail['request'] = $_POST['mail'];
    // Validate the E-Mail
    if (strlen($mail['request']) > 0) {
        if (filter_var($mail['request'], FILTER_VALIDATE_EMAIL))
        {
            $mail['valid'] = htmlentities($mail['request'], ENT_QUOTES, 'UTF-8');
        } else {
            $mail['invalid'] = htmlentities($mail['request'], ENT_QUOTES, 'UTF-8');
            $error['mail'] = htmlentities("This is not a Valid E-Mail address", ENT_QUOTES, 'UTF-8');
            $submit = false;
        }
    }

    // Check name
    $name['request'] = $_POST['name'];
    // Validate the Name - regular string.
    if (strlen($name['request']) > 0) {
        if ($name["valid"] = filter_var($name['request'], FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES))
        {
            $name['valid'] = htmlentities($name['request'], ENT_QUOTES, 'UTF-8');
        } else {
            $name['invalid'] = htmlentities($name['request'], ENT_QUOTES, 'UTF-8');
            $error['name'] = htmlentities("This is not a Valid Name", ENT_QUOTES, 'UTF-8');
            $submit = false;
        }
    }

    // Create the JWT header
    $header = array("alg" => "HS256",
                    "typ" => "JWT");

    // set token expiration time to 1 year.
    $expiration = (time() + (60*60*24*365));
    // token metadata
    $payload = array("name" => "{$name['valid']}",
                     "email" => "{$mail['valid']}",
                     "admin" => "false",
                     "exp" => "{$expiration}");

    // create header + payload
    $header_json = json_encode($header);
    $payload_json = json_encode($payload);
    // Create actual token - no need to encode as it is 7bit ascii.
    $jwt_token = generate_jwt($header, $payload, $secret);

    // prepare SQL statement.
    $query_sql = "INSERT INTO whdc_tokens (name, token, email, date) VALUES ('{$name['valid']}', '{$jwt_token}', '{$mail['valid']}', '{$date}')";
    $DEBUG && print "$query \n";

    
    // If submit is set - execute query
    // $submit = false;
    if ($submit) {
        $query_result =  mysqli_query($dbWhdc, $query_sql);
        if (mysqli_error($dbWhdc)) {
            tolog("SQL", mysqli_error($dbWhdc), $handle);
        }

        if (strlen($error['query']) > 0) {
            $error_message = "$date - ERROR: " . $error['query'] . "\n";
            tolog("SQL", $error_message, $handle);
            $status = $error_message;
            $content .= "<table>";
            $content .= "<tr>";
            $content .= "<td class=\"v\">" . $error_message . "</td>";
            $content .= "</tr>";
            $content .= "</table>";
            $content .= "</div></body>\n</html>";
            print "$content";              
            exit;
        }

        $logmessage = "Added entry: {$name['valid']} / {$mail['valid']} \n";
        tolog("SQL", $logmessage, $handle);
                    
        $status = "<table>";
        $status .= "<tr class=\"v\">";
        $status .= "<td align=\"left\"> *** Added entry {$name['valid']} / {$mail['valid']}</td>";
        $status .= "<tr>";
        $status .= "</table>";
        $status .= "<meta http-equiv=\"refresh\" content=\"3; url=https://{$_SERVER['HTTP_HOST']}/whdctokens.php\" />";
    }
}

// Processing deletion
if ((isset($_GET['delete'])) && ("{$_GET['delete']}" == "Yes")) {

    $date =  date("Y-m-d H:i:s");
    // we need the ID to delete
    if ((isset($_GET['deleteid'])) && (is_numeric($_GET['deleteid']))) {
        // Get data-string first
        $tkrow_sql = "SELECT * FROM whdc_tokens WHERE id={$_GET['deleteid']};";
        $tkrow_query =  mysqli_query($dbWhdc, $tkrow_sql);        
        if (mysqli_error($dbWhdc)) {
            tolog("SQL", mysqli_error($dbWhdc), $handle);
        }
        $rowtodel = mysqli_fetch_assoc($tkrow_query);

        $delete_token = "DELETE FROM whdc_tokens WHERE id='{$rowtodel['id']}';";
        $deltoken_query =  mysqli_query($dbWhdc, $delete_token);
        if (mysqli_error($dbWhdc)) {
            tolog("SQL", mysqli_error($dbWhdc), $handle);
        }
        $logmessage = "Removed tenant-config for {$rowtodel['name']}";
        tolog("DEL", $logmessage, $handle);
        
        $delete_data = "DELETE FROM whdc WHERE tenant_name='{$rowtodel['name']}';";
        $deldata_query =  mysqli_query($dbWhdc, $delete_data);
        if (mysqli_error($dbWhdc)) {
            tolog("SQL", mysqli_error($dbWhdc), $handle);
        }
        $logmessage = "Removed tenant-data for {$rowtodel['name']}";
        tolog("DEL", $logmessage, $handle);
        
        $status = "<table>";
        $status .= "<tr class=\"v\">";
        $status .= "<td align=\"left\"> *** Deleted entry {$rowtodel['name']} + data </td>";
        $status .= "<tr>";
        if ( strlen($error['del']) ) {
            $status .= "<tr class=\"v\">";
            $status .= "<td align=\"left\"> *** ERROR: Error detected <br /> <PRE>{$error['del']}</PRE> </td>";
            $status .= "<tr>";
        }

        $status .= "</table>";
        $status .= "<meta http-equiv=\"refresh\" content=\"3; url=https://{$_SERVER['HTTP_HOST']}/whdctokens.php\" />";
    }
}


$query_sql = "SELECT * FROM whdc_tokens ORDER BY date DESC";
$query_result =  mysqli_query($dbWhdc, $query_sql);
if (mysqli_error($dbWhdc)) {
    tolog("SQL", mysqli_error($dbWhdc), $handle);
}

if (strlen($error['query']) > 0) {
    $error_message = "$date - ERROR: " . $error['query'] . "\n";
    tolog("SQL", $error_message, $handle);
    $status = $error_message;
}

$content .= "<table>";
$content .= "<tr class=\"h\">";
$content .= "<th colspan=\"5\">Registered tenant listing</th>";
$content .= "<tr>";
$content .= "<tr class=\"e\">";
$content .= "<th>Date</th>";
$content .= "<th>WebHook source identifier</th>";
$content .= "<th>Owner E-Mail</th>";
$content .= "<th>Collected # entries</th>";
$content .= "<th>Webhook Token</th>";
$content .= "</tr>";

while ($row = mysqli_fetch_assoc($query_result)) {
    // $DEBUG && print_r ($row);

    $token = "<textarea name=\"Token\" cols=\"40\" rows=\"5\">{$row['token']}</textarea>";
    $row_id = "row_{$row['id']}";
    $token_link = "<A href=\"javascript:hideshow(document.getElementById('{$row_id}'))\">Show Token</A>";
    $token_link .= "<div id='{$row_id}' style=\"white-space; pre-wrap; display: none\" align=\"left\" >$token</div>";
    
    $cnt_entries = "SELECT count(id) as count FROM whdc WHERE tenant_name='{$row['name']}'";
    $cnt_result = mysqli_query($dbWhdc, $cnt_entries);
    $entries = mysqli_fetch_assoc($cnt_result);
    $delurl = "(<A href=\"https://{$_SERVER['HTTP_HOST']}/whdctokens.php?delete=Yes&deleteid={$row['id']}\">del</A>)";
    $content .= "<tr class=\"h\">";
    $content .= "<td class=\"v\"> {$row['date']} </td>";
    $content .= "<td class=\"v\"> {$row['name']} $delurl</td>";
    $content .= "<td class=\"v\"> {$row['email']} </td>";
    $content .= "<td class=\"v\"> <A href=\"https://{$_SERVER['HTTP_HOST']}/whdclist.php?Authorization={$row['token']}\" target=\"{$row['name']}\">View {$entries['count']} rows</A></td>";
    $content .= "<td class=\"v\" width=\"360\"> => $token_link </td>";
    $content .= "</tr>";
} // While loop through tokens

$content .= "</table>";
$content .= $status;

// ==================================================================================================
$content .= "</div></body>\n</html>";

// Close all
fclose($handle);
print "$content";

?>
