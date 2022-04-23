<?php
/**
 * This API endpoint is the second step in the authentication flow of the API.
 * 
 * This endpoint should not be opened in a browser, but rather called via AJAX request, like the regular API requests. It requires the
 * following JSON parameters:
 * 
 * scopes - An array of strings that correspond to scopes the requester wishes for this token to contain. Scopes are of the form: [resource].[action].[specifier]
 * temp_key - A temp_key obtained from the {@see \api\auth\cas_auth cas_auth} endpoint. This is a 69-character string starting with 'TEMP-'
 * user_id - A string containing the user_id of the user who the token should be assigned to.
 * 
 * The temp_key that was obtained from the {@see \api\auth\cas_auth cas_auth} endpoint will only be valid for 60 seconds after it is created. This is because it
 * should immediately be exchanged through this endpoint for an access token. The access token that is returned from this endpoint will last exactly 24 hours
 * after it is created. It would be wise to store this in a cookie in the user's browser, if that is where you will be making API calls, so you don't have to
 * request a new key too frequently.
 *
 * @package api\auth
 * @license All Rights Reserved
 * @copyright Andrew Graber
 * @author Andrew Graber <graber15@purdue.edu>
 */

//*
//Error Reporting Remove comments when Debugging
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE); // */
require_once("../../resources/CAS-1.3.3/CAS.php");
require_once("auth_functions.php");
require_once("../objects/Request.php");
require_once("../objects/Response.php");

$request = new Request();
$response = new Response();
if($request->type == "POST") {
	if($request->has_data("scopes") && $request->has_data("temp_key") && $request->has_data("user_id")) { //Check for parameters
		$userAuthenticated = check_temp_key($request->get_data("temp_key"), $request->get_data("user_id")); //Check to see that temp_key is valid
		if($userAuthenticated) {
			remove_temp_key($request->get_data("temp_key")); //Delete the temp_key so it can't be used again
			$userHasScopeAccess = check_scope_access($request->get_data("user_id"), $request->get_data("scopes"), $err, $ret_scope); //check to ensure that the user has access to all the requested scopes
			if($userHasScopeAccess) {
				$tokenData = create_access_token($request->get_data("user_id"), $request->get_data("scopes"), "TAPI"); //Create a new access token
				$response->set_status(OK);
				$response->ok(true);
				$response->add_data("token", $tokenData);
			} else { //User does not have scope access
				if($err == USER_NOT_FOUND) {
					$response->set_status(NOT_FOUND);
					$response->add_data("ok", false);
					$response->add_data("error", "User was not found");
					$response->add_data("request_user_id", $request->get_data("user_id"));
				} else if($err == SCOPE_NOT_FOUND) {
					$response->set_status(NOT_FOUND);
					$response->add_data("ok", false);
					$response->add_data("error", "One of the requested scopes was not found.");
					$response->add_data("request_scope", $ret_scope);
				} else if($err == SCOPE_NOT_ALLOWED) {
					$response->set_status(UNAUTHORIZED);
					$response->add_data("ok", false);
					$response->add_data("error", "This user is not permitted access to one of the requested scopes.");
					$response->add_data("request_user_id", $request->get_data("user_id"));
					$response->add_data("request_scope", $ret_scope);
				}
			}
		} else { //Either temp_key was expired, nonexistent, or wrong user was given
			$response->set_status(UNAUTHORIZED);
			$response->add_data("ok", false);
			$response->add_data("error", "Login attempt failed! Either temp_key doesn't exist, is expired, or is validated to another user!");
			$response->add_data("request_user_id", $request->get_data("user_id"));
			$response->add_data("request_temp_key", $request->get_data("temp_key"));
		}
	} else { //Request was missing one of the parameters
		$response->set_status(BAD_REQUEST);
		$response->add_data("ok", false);
		$response->add_data("error", "Missing required data: this request requires 'scopes'");
	}
} else if($request->type == "OPTIONS") {
	header("Access-Control-Allow-Origin: *"); //Allows requests from outside domains to go through
	header("Access-Control-Allow-Methods: POST"); //These are the only methods that should be allowed
	header("Access-Control-Allow-Headers: Content-Type, Authorization"); //Only allow these headers
	$response->set_status(OK);
	$response->add_data("ok", true);
} else { //Request type was not POST. This request should always be POST, as it will not be revealed in the query parameters
	$response->set_status(NOT_ALLOWED);
	$response->add_data("ok", false);
	$response->add_data("error", "The request type used is not permitted here. This page only accepts POST requests.");
}

$response->respond();
?>