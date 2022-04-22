<?php
header("Access-Control-Allow-Origin: *");

/**
 * This class contains information about the request that was made to the API. Also sanitizes all data passed in automatically.
 *
 * @package api\objects
 * @license All Rights Reserved
 * @copyright Andrew Graber
 * @author Andrew Graber <graber15@purdue.edu>
 */
class Request {
	/**
	 * @var string $type The HTTP request method that was used (GET, POST, etc.)
	 * @var mixed[] $data The data that was given in this request. Should be JSON that gets parsed into an associative array.
	 */
	public $type;
	private $data;
	
	/**
	 * Constructor -- creates a new request and grabs the information it needs for $type and $data.
	 * 
	 * Automatically sanitizes any data passed in (except if the endpoint was email.php, because that endpoint needs unsanitized HTML)
	 */
	public function __construct() {
		$this->type = $_SERVER['REQUEST_METHOD'];
		if($this->type == "GET") {
			$this->data = $_GET;
		} else {
			$this->data = json_decode(file_get_contents("php://input"), true);
		}
		$this->data = $this->sanitize_data('htmlspecialchars', $this->data);
		$this->data = $this->sanitize_data('strip_tags', $this->data);
		$this->data = $this->sanitize_data('addslashes', $this->data);
	}
	
	/**
	 * Iterates through the given array and calls the given function on each field in the array. Works recursively for arrays inside $arr.
	 * 
	 * @param string $func The function to call on the fields in the array
	 * @param mixed[] $arr The array that should have its fields sanitized
	 * @return mixed[] The new array that is a squeaky clean version of the one passed into the function.
	 */
	public function sanitize_data($func, $arr) {
		$newArr = array();
		if(!empty($arr)) {
			foreach($arr as $key => $value) {
				if(is_array($value)) {
					$newArr[$key] = $this->sanitize_data($func, $value);
				} else if(is_string($value)) {
					if(is_array($func)) {
						$newArr[$key] = call_user_func_array($func, $value);
					} else {
						if(!($this->type == "POST" && $_SERVER['REQUEST_URI'] == "/itap/itpm-ls/labs/api/email")) {
							$newArr[$key] = $func($value);
						} else {
							$newArr[$key] = $value;
						}
					}
				} else {
					$newArr[$key] = $value;
				}
			}
		}
		return $newArr;
	}
	
	/**
	 * Checks to see if {@see \api\objects\Request::data Request::data} has an entry with the given key.
	 * 
	 * @param string $data The name of the field to check for
	 * @return boolean True if the field exists in the request, false otherwise.
	 */
	public function has_data($data) {
		return array_key_exists($data, $this->data);
	}
	
	/**
	 * Grabs the value of the entry in {@see \api\objects\Request::data Request::data} with the given key.
	 * 
	 * Often used in tandem with {@see \api\objects\Request::has_data() Request::has_data()}.
	 * 
	 * @param string $data The name of the field to grab the value of
	 * @return mixed The value of the field requested
	 */
	public function get_data($data) {
		return $this->data[$data];
	}
	
	/**
	 * Getter for {@see \api\objects\Request::data Request::data}
	 * 
	 * @return mixed[] This Request's data.
	 */
	public function fetch_all_data() {
		return $this->data;
	}
	
	/**
	 * Allows addition or modification of entries in this resource's {@see \api\objects\Request::data Request::data}
	 * 
	 * @param string $index The key for the pair to set
	 * @param mixed $value The value for the pair to set
	 * @return void
	 */
	public function set_data($index, $value) {
		$this->data[$index] = $value;
	}
}
?>