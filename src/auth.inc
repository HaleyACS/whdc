<?php

/* DB functions */
include_once('/var/www/files/.htconf.inc'); 

// Connect to system wide DB. We'll need it anyway.
$dbWhdc = mysqli_connect(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE);
mysqli_set_charset($dbWhdc, "utf8");

// check connection
if (mysqli_connect_errno()) {
    printf("DB Connection failed : %s\n", mysqli_connect_error());
    exit();
}

/* Log handler */

function tolog ($function, $text, $target) {
    global $handle, $infolog, $_SERVER;

    // Extract remote IP
    $remip = $_SERVER['REMOTE_ADDR'];


    $date =  date("Y-m-d H:i:s");
    $ftext = wordwrap($text, 100, "\n\t", true);

    fwrite($target, "$date [$function - $remip] $ftext \n");

} // tolog


/* JWT functions */

// Code written using https://roytuts.com/how-to-generate-and-validate-jwt-using-php-without-using-third-party-api/
// as template.

// Create the JWT token providing headers, payload and secret.
function generate_jwt($headers, $payload, $secret = 'secret') {
	$headers_encoded = base64url_encode(json_encode($headers));
	
	$payload_encoded = base64url_encode(json_encode($payload));
	
	$signature = hash_hmac('SHA256', "$headers_encoded.$payload_encoded", $secret, true);
	$signature_encoded = base64url_encode($signature);
	
	$jwt = "$headers_encoded.$payload_encoded.$signature_encoded";
	
	return $jwt;
    
} //  generate_jwt


// Encode string + clean-up in the process.
function base64url_encode($str) {
    return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
} // base64url_encode


// Token validation function
function is_jwt_valid($jwt, $secret = 'secret') {
	// split the jwt
	$tokenParts = explode('.', $jwt);
	$header = base64_decode($tokenParts[0]);
	$payload = base64_decode($tokenParts[1]);
	$signature_provided = $tokenParts[2];

	// check the expiration time - note this will cause an error if there is no 'exp' claim in the jwt
	$expiration = json_decode($payload)->exp;
	$is_token_expired = ($expiration - time()) < 0;

	// build a signature based on the header and payload using the secret
	$base64_url_header = base64url_encode($header);
	$base64_url_payload = base64url_encode($payload);
	$signature = hash_hmac('SHA256', $base64_url_header . "." . $base64_url_payload, $secret, true);
	$base64_url_signature = base64url_encode($signature);

	// verify it matches the signature provided in the jwt
	$is_signature_valid = ($base64_url_signature === $signature_provided);
	
	if ($is_token_expired || !$is_signature_valid) {
		return FALSE;
	} else {
		return TRUE;
	}
} // is_jwt_valid


?>
