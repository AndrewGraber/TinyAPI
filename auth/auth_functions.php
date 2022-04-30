<?php
/**
 * This file contains a set of static functions that are all used for authentication and authorization of requests throughout the LabsAPI.
 *
 * @package api\auth
 * @license All Rights Reserved
 * @copyright Andrew Graber
 * @author Andrew Graber <graber15@purdue.edu>
 */

require_once(__DIR__ . "/../objects/Database.php");

//Error codes used for authorization?
define("USER_NOT_FOUND", 10);
define("SCOPE_NOT_FOUND", 11);
define("SCOPE_NOT_ALLOWED", 12);

/**
 * Takes a temp_key and user_id and checks to see if a valid temp_key exists with those parameters.
 * This function is used by the auth/get_token endpoint to exchange a temp_key for a more permanent token.
 * 
 * @param string $temp_key The temp_key the user provided in their request. Should start with 'TEMP-' and have 64 randomly generated letters and numbers after
 * @param string $user_id The user_id of the user who holds the temp_key and wants to exchange for a token.
 * @return boolean True if the temp_key exists and is not expired. False otherwise.
 */
function check_temp_key($temp_key, $alias) {
	$conn = new Database();
	$now = date('Y-m-d H:i:s', time());
	$ret = $conn->select_first("SELECT * FROM APITempKeys WHERE TempKey = '$temp_key' AND user_id = '$alias' AND Expiration > '$now'", $res);
	return $ret;
}

/**
 * Checks each parameter from $request and looks for 'filter_' statements that include a field marked as a 'self_field' to see if the request is
 * filtering specifically for records that contain the user's user_id. This is for determining what scope should be used for this request.
 * 
 * Loops through for each request parameter. Then, when it finds one that is a filter statement, loops through for each 'self_field' and checks to see if
 * the filter is for that field and for the given user (i.e. the request is filtering by user for themselves).
 * 
 * @param \api\objects\Request $request The request that was sent to the endpoint that called this function
 * @param string $user The user_id of the person who made the request
 * @param \api\objects\ApiResource $resource The APIResource that corresponds to the endpoint of this request.
 * @return boolean Returns true if the request is filtering specifically for the given user, false otherwise.
 */
function req_filter_self($request, $user, $resource) {
	$data = $request->fetch_all_data();
	foreach($data as $data_key => $data_value) { //Loop for each parameter in the request
		if(substr($data_key, 0, 7) === "filter_") { //Check for filter statement
			$arr = explode(" ", $data_value);
			foreach($resource->get_self_fields() as $field) { //Loop for each self_field of the ApiResource (see the corresponding resource object for more info)
				if(count($arr) == 3 && $arr[0] == $field && $arr[1] == "equal" && $arr[2] == $user) { //Check if the filter is for the given user
					return true;
				}
			}
		}
	}
	return false;
}

/**
 * This function determines the scope that this request should require and checks to see if the given token has access to that scope.
 * 
 * Determines the resource and action based off of endpoint and request method (GET, POST, PATCH, DELTE, PUT), so the hardest part is finding
 * the specifier that should be used. If $has_self is true, checks first to see if the 'self' specifier is valid (request filters only for records that contain their identity)
 * then, if 'self' is invalid for the request or the token does not have that scope, checks 'others' and then finally 'all'. If $has_self is false, will only
 * check for 'all' specifier as that is the only valid specifier.
 * 
 * @param string $token The access token provided in this request. Used to check its scopes for authorization.
 * @param string $resource_name The name of the resource that the request corresponds to.
 * @param \api\objects\ApiResource $resource The resource object that was created for this request.
 * @param string $method The request method -- valid options are: GET, POST, PATCH, DELETE, PUT -- must be capitalized
 * @param string $err A variable used for reporting errors that take place in this function (passed by reference)
 * @param string $err_additional A secondary variable used for reporting specifics of errors that take place
 * @param boolean $has_self Whether or not the given resource's 'has_self' field is true.
 * @param \api\objects\Request $request The request object that was generated by this http request.
 * @return boolean True if the token has the proper permissions for this request, false if it does not.
 */
function check_perms($token, $resource_name, $resource, $method, &$err, &$err_additional, $has_self, $request) {
	if($has_self) {
		$conn = new Database();
		$ret = $conn->select_first("SELECT * FROM APITokens WHERE Token = '$token'", $res);
		if($ret) {
			$token_id = $res['TokenId'];
			if(strtotime('now') < strtotime($res['Expiration'])) {
				$user = $res['user_id'];
				if(($resource->get_identifier() == "user_id" && $request->has_data("user_id") && $request->get_data("user_id") == $user) || req_filter_self($request, $user, $resource)) {
					$specifier = "self";
				} else {
					$is_self = false;
					foreach($resource->get_self_fields() as $self_field) {
						if(in_array($self_field, $resource->get_fields()) && $request->has_data($self_field) && $request->get_data($self_field) == $user) {
							$is_self = true;
							break;
						}
					}
					if($is_self) {
						$specifier = "self";
					} else {
						$specifier = "others";
					}
				}
				$ret2 = $conn->select_first("SELECT * FROM APIScopes WHERE Resource = '$resource_name' AND Action = '$method' AND Specifier = '$specifier'", $res2);
				if($ret2) {
					$scope_id = $res2['ScopeId'];
					$ret3 = $conn->select_first("SELECT * FROM APITokenScopes WHERE TokenId = $token_id AND ScopeId = $scope_id", $res3);
					if(!$ret3) {
						$err2 = "";
						$err_additional2 = "";
						$ret_recur = check_perms($token, $resource_name, $resource, $method, $err2, $err_additional2, false, $request); //Check for scope with "all" specifier.
						if($err2 != "") $err = $err2;
						if($err_additional2 != "") $err_additional = $err_additional2;
						return $ret_recur;
					}
					return $ret3;
				} else {
					$err = "NOSCOPE";
					$err_additional = $resource_name . "." . $method . "." . $specifier;
					return false;
				}
			} else {
				$err = "EXPIRED";
				$err_additional = $res['Expiration'];
				return false;
			}
		} else {
			$err = "NOTOKEN";
			return false;
		}
	} else {
		$specifier = "all";
		$conn = new Database();
		$ret = $conn->select_first("SELECT * FROM APITokens WHERE Token = '$token'", $res);
		if($ret) {
			$token_id = $res['TokenId'];
			if(strtotime('now') < strtotime($res['Expiration'])) {
				$ret2 = $conn->select_first("SELECT * FROM APIScopes WHERE Resource = '$resource_name' AND Action = '$method' AND Specifier = '$specifier'", $res2);
				if($ret2) {
					$scope_id = $res2['ScopeId'];
					$ret3 = $conn->select_first("SELECT * FROM APITokenScopes WHERE TokenId = $token_id AND ScopeId = $scope_id", $res3);
					if(!$ret3) {
						$err = "NOPERM";
						$err_additional = $resource_name . "." . $method . "." . $specifier;
					}
					return $ret3;
				} else {
					$err = "NOSCOPE";
					$err_additional = $resource_name . "." . $method . "." . $specifier;
					return false;
				}
			} else {
				$err = "EXPIRED";
				$err_additional = $res['Expiration'];
				return false;
			}
		} else {
			$err = "NOTOKEN";
			return false;
		}
	}
}

/**
 * Removes the given $temp_key from the database.
 * 
 * This usually takes place when a user exchanges a temp_key for an access token.
 * 
 * @param string $temp_key The temp_key that should be deleted. This function assumes the temp_key exists.
 * @return boolean True if something was deleted by this function, false otherwise
 */
function remove_temp_key($temp_key) {
	$conn = new Database();
	$ret = $conn->delete_from("DELETE FROM APITempKeys WHERE TempKey = '$temp_key'", $num_rows);
	return $ret;
}

/**
 * Finds the highest ReqAuth of all specifiers within a given resource and action
 * 
 * @param string $resource The resource to search in
 * @param string $action The action to search in
 * @return int The largest ReqAuth of all scopes in the given resource and action
 */
function get_largest_spec_auth($resource, $action) {
	$conn = new Database();
	$sql = "SELECT MAX(ReqAuth) AS max FROM APIScopes WHERE Resource = '" . $resource . "' AND Action = '" . $action . "';";
	$conn->select_first($sql, $res);
	return $res['max'];
}

/**
 * Checks the database to see if the user has access to the given scopes. Used in token creation to ensure
 * the user has permission to create a token with the requested scopes.
 * 
 * Will look in the APIScopes table to see if the scope exists and compare the user's auth_level
 * against the ReqAuth of the scope for each scope in the array. If the auth_level of the user
 * is too low to gain access to the scope, checks the APIUserScopes table (which gives access to
 * specific scopes on a user-by-user basis) to see if the scope exists there for this user.
 *
 * @param string $user The user_id of the user requesting scopes
 * @param string[] $scopes Array of strings corresponding to the scope's RAS, one for each scope requested.
 * @param int $err An integer corresponding to the error code. This will only be set if the function returns false.
 *                 Possible $err values are: USER_NOT_FOUND, SCOPE_NOT_FOUND, SCOPE_NOT_ALLOWED.
 * @param string $denied_scope A string containing the RAS of the scope that was denied. Will only be set when
 *                             $err is set to SCOPE_NOT_ALLOWED.
 * @return bool True if the user exists and has access to every scope provided. False otherwise.
 */
function check_scope_access($user, $scopes, &$err, &$ret_scope) {
	$conn = new Database();
	$user_exists = $conn->select_first("SELECT auth_level FROM Users WHERE user_id = '$user';", $res);
	if($user_exists) {
		$auth = $res['auth_level'];
		foreach($scopes as $scope) {
			if($scope == "available") {
				continue;
			}
			$first_dot = strpos($scope, ".");
			$second_dot = strpos($scope, ".", $first_dot + 1);
			$resource = substr($scope, 0, $first_dot);
			$action = substr($scope, $first_dot + 1, $second_dot - $first_dot - 1);
			$specifier = substr($scope, $second_dot + 1);
			if($specifier === "*") {
				$req_auth = get_largest_spec_auth($resource, $action);
				if($req_auth > $auth) {
					$err = SCOPE_NOT_ALLOWED;
					$ret_scope = $scope;
					return false;
				}
			} else {
				$scope_found = $conn->select_first("SELECT ReqAuth, ScopeId FROM APIScopes WHERE Resource = '$resource' AND Action = '$action' AND Specifier = '$specifier'", $res);
				if($scope_found) {
					if($auth < $res['ReqAuth']) {
						$scope_id = $res['ScopeId'];
						$user_has_explicit_access = $conn->select_first("SELECT * FROM APIUserScopes WHERE user_id = '$user' AND ScopeId = '$scope_id';", $res);
						if(!$user_has_explicit_access) {
							$err = SCOPE_NOT_ALLOWED;
							$ret_scope = $scope;
							return false;
						}
					}
				} else {
					$err = SCOPE_NOT_FOUND;
					$ret_scope = $scope;
					return false;
				}
			}
		}
		return true;
	} else {
		$err = USER_NOT_FOUND;
		return false;
	}
}

/**
 * Returns a random number between $min and $max (inclusive)
 *
 * The number is generated using the cryptographically secure function
 * openssl_random_pseudo_bytes().
 * Credit where credit is due -- Thanks to Scott on StackOverflow for this function
 *
 * @param int $min The minimum number that can be generated, including $min
 * @param int $max The maximum number that can be generated, including $max
 * @return int An integer in the range [$min, $max]
 */
function crypto_rand_secure($min, $max) {
	$range = $max - $min;
	if($range < 1) return $min; // not so random...
	$log = ceil(log($range, 2));
	$bytes = (int) ($log / 8) + 1; //length in bytes
	$bits = (int) $log + 1; //length in bits
	$filter = (int) (1 << $bits) - 1; // set all lower bits to 1
	do {
		$rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
		$rnd = $rnd & $filter; //discard irrelevant bits
	} while ($rnd > $range);
	return $min + $rnd;
}

/**
 * Creates a string of $length characters, each generated by crypto_rand_secure
 *
 * Credit where credit is due -- Thanks to Scott on StackOverflow for this function
 *
 * @param int $length The length of the random string to generate
 * @return string A cryptographically secure random string of $length characters
 *                containing letters (upper and lowercase) and numbers.
 */
function get_token($length, $type) {
	$token = "";
	$codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
	$codeAlphabet .= "abcdefghijklmnopqrstuvwxyz";
	$codeAlphabet .= "0123456789";
	$max = strlen($codeAlphabet) - 1;
	for($i=0; $i < $length; $i++) {
		$token .= $codeAlphabet[crypto_rand_secure(0, $max)];
	}
	
	return $type . "-" . $token;
}

/**
 * Checks if a token with string $token already exists in the database
 * 
 * @param string $token The token that should be checked
 * @return boolean True if the token exists, false otherwise.
 */
function token_exists($token) {
	$conn = new Database();
	$exists = $conn->select_first("SELECT * FROM APITokens WHERE Token = '$token';", $res);
	return $exists;
}

/**
 * Creates an access token for the API. If $type is "TEMP", it will make a temp_key that only lasts 60 seconds. This temp_key
 * should be exchanged for an access token via the auth/get_token endpoint. If the $type is not "TEMP", it will attempt to
 * create an access token that contains the requested scopes. This access token will expire exactly 24 hours after it is
 * distributed and a new one will need to be requested. This function assumes that the user has access to all the given $scopes.
 * 
 * @param string $user The user_id of the person who made the request for this token
 * @param string[] $scopes An array of the scopes that the created access token should be linked to.
 * @param string $type The type of token to be created. Valid options are: 'TEMP' or 'TAPI'
 */
function create_access_token($user, $scopes, $type) {
	$conn = new Database();
	$token = "";
	do {
		$token = get_token(64, $type);
	} while (token_exists($token)); //Highly unlikely that token will already exist but still possible
	
	if ($type == "TEMP") {
		$expiration = date("Y-m-d H:i:s", time() + 60);
		$conn->conn->query("INSERT INTO APITempKeys (user_id, TempKey, Expiration) VALUES ('$user', '$token', '$expiration');");
	} else {
		$res = $conn->select_first("SELECT * FROM APITokens WHERE user_id = '$user';", $rows);
		if($res) {
			$ret = $conn->delete_from("DELETE FROM APITokens WHERE user_id = '$user';", $nrows);
		}
		$expiration = date("Y-m-d H:i:s", time() + 86400);
		$conn->conn->query("INSERT INTO APITokens (user_id, Token, Expiration) VALUES ('$user', '$token', '$expiration');");
		$token_id = $conn->conn->insert_id;
		if(count($scopes) == 1 && $scopes[0] == "available") {
			$ret = $conn->select_first("SELECT auth_level FROM Users WHERE user_id = '$user';", $res);
			$auth = $res['auth_level'];
			$rows = $conn->select("SELECT * FROM APIScopes WHERE ReqAuth <= $auth");
			foreach($rows as $row) {
				$scope_id = $row['ScopeId'];
				$conn->conn->query("INSERT INTO APITokenScopes (TokenId, ScopeId) VALUES ($token_id, $scope_id);");
			}
		} else {
			foreach($scopes as $scope) {
				$first_dot = strpos($scope, ".");
				$second_dot = strpos($scope, ".", $first_dot + 1);
				$resource = substr($scope, 0, $first_dot);
				$action = substr($scope, $first_dot + 1, $second_dot - $first_dot - 1);
				$specifier = substr($scope, $second_dot + 1);
				if($specifier === "*") {
					$wild_scopes = $conn->select("SELECT ScopeId FROM APIScopes WHERE Resource = '$resource' AND Action = '$action'");
					foreach($wild_scopes as $wild_scope) {
						$scope_id = $row['ScopeId'];
						$conn->conn->query("INSERT INTO APITokenScopes (TokenId, ScopeId) VALUES ($token_id, $scope_id);");
					}
				} else {
					$res = $conn->select_first("SELECT ScopeId FROM APIScopes WHERE Resource = '$resource' AND Action = '$action' AND Specifier = '$specifier';", $row);
					$scope_id = $row['ScopeId'];
					$conn->conn->query("INSERT INTO APITokenScopes (TokenId, ScopeId) VALUES ($token_id, $scope_id);");
				}
			}
		}
	}
	return $token;
}
?>