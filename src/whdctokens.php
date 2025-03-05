<?php

/* WADC webhook lister
 */

$DEBUG = false;
$result = "";
$content = "";

// DB File
$sqdb_file = '/var/www/files/whdc_database.db';

// tokens file.
include_once('/var/www/files/tokens.inc');

// tokens file.
include_once('/var/www/files/auth.inc');

// Create handle for log file.
$handle = fopen("/var/www/logs/whdc.log", "a");

if (file_exists($sqdb_file)) {
    $create_tables = false;
} else {
    $create_tables = true;
}

// Grab that out of the env.
$username = getenv('USERNAME');
$password = getenv('PASSWORD');

//================  Database - if it does not exists, create it =======================
$database = new SQLite3("$sqdb_file", SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
// Errors are emitted as warnings by default, enable proper error handling.
$database->enableExceptions(true);
$database->exec('PRAGMA journal_mode = wal;');
$database->exec("PRAGMA busy_timeout=5000");

if ($create_tables) {

    $query = "CREATE TABLE IF NOT EXISTS whdc_tokens (
       id INTEGER PRIMARY KEY AUTOINCREMENT,
       name varchar NOT NULL,
       token VARCHAR NOT NULL,
       email VARCHAR NOT NULL,
       date DATETIME NOT NULL,
       UNIQUE(name,email)
    )";
    $database->exec($query);
    if ($database->LastErrorCode()) {
        fwrite($handle, "$date - ERROR: " . $database->LastErrorMsg() . "\n");
        exit;
    }

    $query = "CREATE TABLE IF NOT EXISTS whdc (
       id INTEGER PRIMARY KEY AUTOINCREMENT,
       token VARCHAR NOT NULL,
       subject VARCHAR NOT NULL,
       rem_address VARCHAR NOT NULL,
       json_base64 TEXT NOT NULL,
       logs_base64 TEXT NOT NULL,
       date DATETIME NOT NULL
    )";
    $database->exec($query);
    if ($database->LastErrorCode()) {
        fwrite($handle, "$date - ERROR: " . $database->LastErrorMsg() . "\n");
        exit;
    }

} // If Database file exists.

$content = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">\n
<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\" lang=\"en\">
";
// Body start
$content .= "<body><div class=\"center\">";

// head
$content = "
<head>
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
    exit;
    
} else {
    /* For Debugging
    print "<PRE>";
    print "$password \n";
    print hash('sha256', "{$_SERVER['PHP_AUTH_PW']}");
    print "<PRE>";
    exit;
    */
    // We compute the hash of the PWD.
    $pwd_hash = hash('sha256', "{$_SERVER['PHP_AUTH_PW']}");
    
    if ((isset($_SERVER['PHP_AUTH_USER'])) && ("{$_SERVER['PHP_AUTH_USER']}" == "$username") && (isset($_SERVER['PHP_AUTH_PW'])) && ("$pwd_hash" == "$password")) {
        $message = ucfirst($username);
    } else {
        header('WWW-Authenticate: Basic realm="Webhook Collector Token Manager"');
        header('HTTP/1.0 401 Unauthorized');
        echo '<body><h1>Well, thanks for the fish... Solong!</h1></body></html>';
        exit;
    }
}



$content = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">\n
<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\" lang=\"en\">
";

// head
$content = "
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
// We have a new entry to process
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
            $mail['error_msg'] = htmlentities("This is not a Valid E-Mail address", ENT_QUOTES, 'UTF-8');
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
            $name['error_msg'] = htmlentities("This is not a Valid Name", ENT_QUOTES, 'UTF-8');
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
    $query = "INSERT INTO whdc_tokens (name, token, email, date) VALUES ('{$name['valid']}', '{$jwt_token}', '{$mail['valid']}', '{$date}')";
    $DEBUG && print "$query \n";

    // If submit is set - execute query
    // $submit = false;
    if ($submit) {
        $result = $database->query($query);
        if ($database->LastErrorCode()) {
            fwrite($handle, "$date - ERROR: " . $database->LastErrorMsg() . "\n");
            $content .= "<table>";
            $content .= "<tr>";
            $content .= "<td class=\"v\">" . $database->LastErrorMsg() . "</td>";
            $content .= "</tr>";
            $content .= "</table>";
            $content .= "</div></body>\n</html>";
            print "$content";              
            exit;
        }
    }
}

$query = "SELECT * FROM whdc_tokens ORDER BY date DESC";
$result = $database->query($query);

if ($database->LastErrorCode()) {
    fwrite($handle, "$date - ERROR: " . $database->LastErrorMsg() . "\n");
}

$content .= "<table>";
$content .= "<tr class=\"h\">";
$content .= "<th colspan=\"4\">Registered tenant listing</th>";
$content .= "<tr>";
$content .= "<tr class=\"e\">";
$content .= "<th>Date</th>";
$content .= "<th>WebHook source identifier</th>";
$content .= "<th>Owner E-Mail</th>";
$content .= "<th>Webhook Token</th>";
$content .= "</tr>";

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $DEBUG && print_r ($row);

    $token = "<textarea name=\"Token\" cols=\"40\" rows=\"5\">{$row['token']}</textarea>";
    $row_id = "row_{$row['id']}";
    $token_link = "<A href=\"javascript:hideshow(document.getElementById('{$row_id}'))\">Show Token</A>";
    $token_link .= "<div id='{$row_id}' style=\"white-space; pre-wrap; display: none\" align=\"left\" >$token</div>";
    $cnt_entries = "SELECT count(id) as count FROM whdc WHERE token='{$row['name']}'";
    $entries = $database->querySingle($cnt_entries);
    
    $content .= "<tr class=\"h\">";
    $content .= "<td class=\"v\">{$row['date']}</td>";
    $content .= "<td class=\"v\"> {$row['name']}</td>";
    $content .= "<td class=\"v\"> {$row['email']}</td>";
    $content .= "<td class=\"v\" width=\"360\"> => $token_link (# $entries rows)</td>";
    $content .= "</tr>";
} // While loop through tokens

$content .= "</table>";


// ==================================================================================================
$content .= "</div></body>\n</html>";

// Close all
$database->close();
fclose($handle);
print "$content";

?>
