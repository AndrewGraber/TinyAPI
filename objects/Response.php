<?php
define("OK", 200);
define("CREATED", 201);
define("ACCEPTED", 202);
define("NO_CONTENT", 204);
define("BAD_REQUEST", 400);
define("UNAUTHORIZED", 401);
define("FORBIDDEN", 403);
define("NOT_FOUND", 404);
define("NOT_ALLOWED", 405);
define("SERVER_ERROR", 500);
define("NOT_IMPLEMENTED", 501);
define("SERVICE_UNAVAILABLE", 503);

/**
 * This class is a container for everything that we need to send back to the client - i.e.: a response
 * 
 * Above are a few defined names for HTTP response codes that the API uses (to make things easier to read syntactically).
 * The response has an associative array (that will often become a multilevel array) that gets turned into a JSON object
 * upon sending the response. This means that if the client is using JavaScript, the response will automatically become an
 * object for them, which is pretty handy.
 *
 * @package api\objects
 * @license All Rights Reserved
 * @copyright Andrew Graber
 * @author Andrew Graber <graber15@purdue.edu>
 */
class Response {
	/**
	 * @param mixed[] $data The associative array of all the response data that will be parsed to JSON
	 */
	private $data;
	
	/**
	 * Constructor -- Creates a new response and sets the content-type and charset of this response.
	 */
	public function __construct() {
		header('Content-Type: application/json; charset=UTF-8');
		$this->data = array();
	}
	
	/**
	 * This function is just an expansion of syntax that calls http_response_code($status).
	 * This is just because it makes more sense to say $response->set_status(OK) than it does
	 * to say http_response_code(OK) (and fits the style of the API)
	 * 
	 * @param int $status The HTTP resposne code to set
	 * @return void
	 */
	public function set_status($status) {
		http_response_code($status);
	}
	
	/**
	 * Adds a key, value pair to {@see \api\objects\Response::data Response::data} associative array
	 * 
	 * @param string $name The key to use for this (k,v) pair
	 * @param mixed $data The data to add to the response object
	 */
	public function add_data($name, $data) {
		$this->data[$name] = $data;
	}
	
	/**
	 * Syntactic sugar -- Makes it easier to set the 'ok' data in the response to true.
	 * 
	 * $response->ok(true) just seems better to me than $response->add_data("ok", true)
	 * 
	 * @param boolean $value True or false -- whether the response is OK or not
	 * @return void
	 */
	public function ok($value) {
		$this->add_data("ok", $value);
	}
	
	/**
	 * Easy way to set the status to errored. Calls $this->ok(false) and adds error message.
	 * 
	 * @param string $message The error message to send
	 * @return void
	 */
	public function error($message) {
		$this->ok(false);
		$this->add_data("error", $message);
	}
	
	/**
	 * Sets error message for when a request was missing a required field.
	 * 
	 * @param string $fieldName The name of the field that was missing
	 * @param string $requestType The HTTP request method that was used (usually just $request->type)
	 * @param string $resourceName The name of the resource that was requested
	 * @return void
	 */
	public function err_missing_field($fieldName, $requestType, $resourceName) {
		$this->set_status(BAD_REQUEST);
		$this->error("Missing required field - Field '" . $fieldName . "' is required for Resource '" . $resourceName . "' with request type " . $requestType);
	}

	public function err_malformed_filters($resource_name, $request_type) {
		$this->set_status(BAD_REQUEST);
		$this->error("Malformed Filter Query! Provided filter query for Resource '" . $resource_name . "' with request type " . $request_type . " could not be interpreted.");
	}

	public function err_unknown_field($resource_name, $request_type, $field_name) {
		$this->set_status(BAD_REQUEST);
		$this->error("Unknown Field! Could not recognize Field '" . $field_name . "' for Resource '" . $resource_name . "' with request type " . $request_type);
	}
	
	/**
	 * Sets error message for when a record of a resource could not be found.
	 * 
	 * @param string $fieldName The name of the field that the request gave to identify the record
	 * @param string $field The value of the field that the request gave to identify the record
	 * @param string $resourceName The name of the resource that was requested
	 * @return void
	 */
	public function err_not_found($fieldName, $field, $resourceName) {
		$this->set_status(NOT_FOUND);
		$this->error("Resource of type '" . $resourceName . "' with given '" . $fieldName . "' could not be found.");
		$this->add_data("request_" . $fieldName, $field);
	}
	
	/**
	 * Sets error message for when the user has attempted to create a record of a resource that
	 * already exists in our database. Probably only called if method is POST.
	 * 
	 * @param string $resourceName The name of the resource that was requested
	 * @return void
	 */
	public function err_already_exists($resourceName) {
		$this->set_status(BAD_REQUEST);
		$this->error("A Resource of type '" . $resourceName . "' with those defining values already exists!");
	}
	
	/**
	 * Sets error message for when a field that was provided has the wrong type (type being string, int, etc..)
	 * 
	 * @param string $resource_name The name of the resource that was requested
	 * @param string $mistyped_field The name of the field that contained a value of the wrong type
	 * @param string $mistyped_type The type that the $mistyped_field was given to us as
	 * @param string $correct_type The type that the value of the field should have been
	 * @return void
	 */
	public function err_incorrect_type($resource_name, $mistyped_field, $mistyped_type, $correct_type) {
		$this->set_status(BAD_REQUEST);
		$this->error("Incorrect type! Field '" . $mistyped_field . "' for Resource '" . $resource_name . "' requires type '" . $correct_type . "'. Type '" . $mistyped_type . "' was provided.");
	}
	
	/**
	 * Sets error message for when the value of a field is too large. For strings, this can mean that the number
	 * of characters was larger than the respective {@see \api\objects\ApiResource::field_sizes ApiResource::field_sizes} value. For ints,
	 * this means that the actual value of the number was larger than the maximum value defined in {@see \api\objects\ApiResource::field_sizes ApiResource::field_sizes}
	 * 
	 * @param string $resource_name The name of the resource that was requested
	 * @param string $oversize_field The name of the field whose value was too large
	 * @param int $given_size The size of the field that was too large
	 * @param int $max_size The maximum allowed size of this field
	 * @return void
	 */
	public function err_oversize_field($resource_name, $oversize_field, $given_size, $max_size) {
		$this->set_status(BAD_REQUEST);
		$this->error("One of the given fields was too large! Field '" . $oversize_field . "' for Resource '" . $resource_name . "' has max size '" . $max_size . "'. Provided size was '" . $given_size . "'.");
	}

	public function err_incorrect_format($resource_name, $incorrect_field, $given_field) {
		$this->set_status(BAD_REQUEST);
		$this->error("One of the given fields was formatted incorrectly! Field '" . $incorrect_field . "' for Resource '" . $resource_name . "' had bad format!");
		$this->add_data("request_" . $incorrect_field, $given_field);
	}
	
	/**
	 * This function encode the data inside this response object as JSON and echo it out, essentially sending the response to the client.
	 * 
	 * If {@see \api\objects\Response::data Response::data} does not actually contain any data, it will respond with SERVER_ERROR and give
	 * an error message saying the server didn't build any response data. This is defined to be an error, because even if there's nothing
	 * for the server to say, it should always have at least the 'ok' parameter.
	 * 
	 * @return void
	 */
	public function respond() {
		if(sizeof($this->data) == 0) {
			$this->set_status(SERVER_ERROR);
			$this->add_data("ok", false);
			$this->add_data("error", "For some reason, the server did not build any response data!");
		}
		echo json_encode($this->data);
	}
}
?>