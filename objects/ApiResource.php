<?php
/**
 * Abstract class that represents a "resource" which is just a type of entry in our database. Examples of resources include employee, machine, milestone, etc.
 * 
 * Each resource object has a corresponding endpoint in the {@see \api api} folder. These are the locations that clients should send requests to. Upon receiving
 * a request, the endpoint will create a new {@see \api\objects\Request Request} object (which sanitizes all input data automatically) and an instance of the 
 * corresponding resource (which is a child of this class) and call {@see \api\objects\ApiResource::handle_request() ApiResource::handle_request()}.
 * ApiResource::handle_request() will automatically check the types and ranges of any fields in the request that the api will use to help ensure errors don't take
 * place. Then, it will determine which handler to run based off the http request method (GET, POST, PUT, DELETE). Below is a list of request types and their corresponding
 * handlers:
 * 
 * GET - {@see \api\objects\ApiResource::handle_get() ApiResource::handle_get()} - Used for filtering through records in the database and GETting information about them.
 * POST - {@see \api\objects\ApiResource::handle_post() ApiResource::handle_post()} - Used for creating new records in the database.
 * PUT - {@see \api\objects\ApiResource::handle_put() ApiResource::handle_put()} - Used for modifying existing records in the database.
 * DELETE - {@see \api\objects\ApiResource::handle_delete() ApiResource::handle_delete()} - Used for deleting records in the database.
 *
 * @package api\objects
 * @license All Rights Reserved
 * @copyright Andrew Graber
 * @author Andrew Graber <graber15@purdue.edu>
 */

//*
//Error Reporting Remove comments when Debugging 
error_reporting(E_ALL); 
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE); //*/ 
 
require_once("ApiResourceInterface.php");
require_once("Request.php");
require_once("Response.php");
require_once(__DIR__ . "/../auth/auth_functions.php");

/**
 * This class represents a "resource" which is just a type of entry in our database. Examples of resource include employee, machine, milestone, etc.
 * 
 * In each child class of this one, you can find explicit definitions of many different fields that help to shape what each resource is. These fields
 * change how the requests to the API should be formed and how the api will respond to your requests. The most important of these fields is probably
 * the identifier of the class. This identifier will be used to determine what record should be changed. It should be a field that also exists in the
 * $fields variable. It will likely be the same as the primary key of the database table this resource corresponds to. For example, the idendifier of
 * the {@see \api\objects\Milestone Milestone} resource is 'milestone_id', which is also the primary key of the database table for milestones.
 * 
 * @package api\objects
 * 
 */
class ApiResource implements ApiResourceInterface {
	/**
	 * Associative array for determining whether a PHP type is equivalent to an SQL type
	 */
	static $php_sql_types = array(
		"boolean" => ["boolean", "tinyint", "bit"],
		"integer" => ["smallint", "mediumint", "int", "bigint"],
		"double" => ["decimal", "float", "double", "real"],
		"string" => ["char", "varchar", "tinytext", "text", "mediumtext", "longtext", "tinyblob", "blob", "mediumblob", "longblob", "enum", "set", "date", "datetime", "timestamp", "time", "year"],
		"array" => [],
		"object" => [],
		"resource" => [],
		"null" => []
	);

	/**
	 * Associative array containing default length values for SQL types.
	 */
	static $sql_default_sizes = array(
		"tinyint" => 127,
		"smallint" => 32767,
		"mediumint" => 8388607,
		"int" => PHP_INT_MAX,
		"bigint" => PHP_INT_MAX,
		"decimal" => 9999999999,
		"float" => PHP_INT_MAX,
		"double" => PHP_INT_MAX,
		"real" => PHP_INT_MAX,
		"bit" => 1,
		"boolean" => 1,
		"serial" => PHP_INT_MAX,
		"date" => 10,
		"datetime" => 19,
		"timestamp" => 19,
		"time" => 8,
		"year" => 4,
		"char" => 1,
		"varchar" => 1,
		"tinytext" => 255,
		"text" => 65535,
		"mediumtext" => 16777215,
		"longtext" => PHP_INT_MAX,
		"binary" => 1,
		"varbinary" => 1,
		"tinyblob" => 255,
		"mediumblob" => 16777215,
		"blob" => 65535,
		"longblob" => PHP_INT_MAX,
		"enum" => PHP_INT_MAX,
		"set" => PHP_INT_MAX
	);

	/**
	 * @var \api\objects\Database $db          An instance of the database object (used for querying)
	 * @var string    $table_name              The name (case sensitive) of the table that corresponds to this resource in the database
	 * @var string    $resource_name              The name of this resource (i.e.: Employee, ApiResource, etc. -- Usually starts with uppercase letter)
	 * @var string    $snake_name           The name of this resource in lowercase snake case (i.e.: employee, api_resource, etc. -- Used in responses)
	 * @var string    $snake_name_plural    The plural form of {@see \api\objects\ApiResource::snake_name ApiResource::snake_name}
	 * @var string    $identifier              The name of the column that can uniquely identify an entry of this resource
	 * @var int       $default_list_amt        The amount of records that will be shown when listing and no 'limit' parameter is given
	 * @var int       $max_list                The maximum number of records that can be shown when listing, regardless of 'limit' parameter
	 * @var string[]  $fields                  An indexed array containing all of the fields (columns in the database) this resource has
	 * @var string[]  $field_types             An associative array, with field name as the key, containing the type that each field should have. Options are: "string", "integer"
	 * @var int[]     $field_sizes             An associative array, with field name as the key, stating the maximum size of each field. For strings, this is max number of characters. For integers, this is the max value it can hold
	 * @var mixed[]   $field_values            An associative array, with field name as the key, containing all the values of each field for this record. This should not be explicitly defined in a resource class, as it will be filled when the resource is read from the database.
	 * @var string[]  $post_required           An indexed array listing all fields that are required on a POST request to create a new record.
	 * @var string[]  $post_optional           An indexed array listing all fields that are optional on a POST request to create a new record.
	 * @var string[]  $put_options             An indexed array listing all the fields that can be changed via a PUT request
	 * @var boolean   $has_self                Boolean that indicates whether this resource is linked to a person. For example, this would be true for {@see \api\objects\Employee Employee} and false for {@see \api\objects\Machine Machine}.
	 * @var string[]  $self_fields             An indexed array listing all the fields that should contain a user_id. Generally, this will either be empty or will be {"user_id"}. However, some resources do not follow this naming pattern. One example of this is the field "author" in {@see \api\objects\PerformanceReport PerformanceReport}.
	 */
	protected $db; //Passed in via constructor
	protected $table_name; //Meta in APIResourceData
	protected $resource_name; //Meta in APIResourceData
	protected $snake_name; //Meta in APIResourceData
	protected $snake_name_plural; //Meta in APIResourceData
	protected $identifier; //Automatic
	protected $default_list_amt; //Meta in APIResourceData
	protected $max_list; //Meta in APIResourceData
	protected $fields; //Automatic
	protected $field_types; //Automatic
	protected $field_sizes; //Automatic
	protected $field_values; //Automatic
	protected $post_required; //Automatic
	protected $post_optional; //Automatic
	protected $put_options; //Automatic
	protected $has_self; //Automatic
	protected $self_fields; //Automatic
	
	/**
	 * Constructor -- initializes all the fields pertaining to the resource. Automatically grabs all resource information and fills out this instance
	 * of the ApiResource object to reflect that.
	 * 
	 * @param api\objects\Database $db A database object that has already been created.
	 * @param string $resource The ResourceName of the resource that was requested.
	 */
	public function __construct($db, $resource, &$err) {
		$this->db = $db;
		$this->fields = array();
		$this->field_types = array();
		$this->field_sizes = array();
		$this->post_required = array();
		$this->post_optional = array();
		$this->put_options = array();
		$this->has_self = false;
		$this->self_fields = array();

		//Grab the metadata for the requested resource (from APIResourceData table)
		$res = $this->db->conn->query("SELECT * FROM APIResourceData WHERE SnakeName = '$resource'");
		if($res && $res->num_rows > 0) {
			$row = $res->fetch_assoc();
			$this->table_name = $row['TableName'];
			$this->resource_name = $row['ResourceName'];
			$this->snake_name = $row['SnakeName'];
			$this->snake_name_plural = $row['SnakeNamePlural'];
			$this->default_list_amt = $row['DefaultListAmount'];
			$this->max_list = $row['MaxListAmount'];
		} else {
			$err = true;
		}

		//Grab information about the table for this resource
		if($err !== true) {
			$res = $this->db->conn->query("SHOW FULL COLUMNS FROM " . $this->table_name);
			while($row = $res->fetch_assoc()) {
				//Add field to object
				$this->fields[] = $row['Field'];

				//Parse type of field and add it
				$type = substr($row['Type'], 0, strpos($row['Type'], '('));
				$this->field_types[$row['Field']] = $type;

				//Parse size (defined in sql) of field and add it
				$size = substr($row['Type'], strpos($row['Type'], '(') + 1, strpos($row['Type'], ')') - strpos($row['Type'], '(') - 1);
				$this->field_sizes[$row['Field']] = intval($size);

				if(in_array($this->field_types[$row['Field']], self::$php_sql_types['integer'])) {
					$this->field_sizes[$row['Field']] = self::$sql_default_sizes[$this->field_types[$row['Field']]];
				} else if($type == "enum") {
					$this->field_sizes[$row['Field']] = self::$sql_default_sizes[$this->field_types[$row['Field']]];
				}

				//Some data types don't have an included length
				if(strpos($row['Type'], '(') === false) { //Triple equal comparison to false needed for strpos, because 0 is evaluated as false, but is a valid position
					$this->field_types[$row['Field']] = $row['Type'];
					$this->field_sizes[$row['Field']] = self::$sql_default_sizes[$row['Type']];
				}

				//Check if field is primary key. If so, it will be used as the identifier for the resource
				if($row['Key'] == "PRI") {
					//Set identifier
					$this->identifier = $row['Field'];

					if($row['Extra'] == "auto_increment") {
						$this->post_optional[] = $row['Field']; //Because the ID auto-increments, we won't need to specify it in POST requests.
					} else {
						$this->post_required[] = $row['Field']; //Because the ID cannot be determined automatically, we must specify it in POST requests.
					}
				} else {
					//Add this as an option for values you can change in PUT requests.
					$this->put_options[] = $row['Field'];

					//If this field has a default, then we don't need to require it for POST requests, since it will be set automatically.
					if(($row['Default'] != NULL || $row['Null'] == "YES") && $row['Comment'] != "required") {
						$this->post_optional[] = $row['Field'];
					} else {
						$this->post_required[] = $row['Field'];
					}
				}

				//Check for "self" metadata in the field's comment to determine if this is a "self" field.
				if($row['Comment'] == "self") {
					if(!$this->has_self) $this->has_self = true;
					$this->self_fields[] = $row['Field'];
				}
			}
			
			//Initialize field_values;
			$this->field_values = array();
			$err = false;
		}
	}

	/**
	 * Getter for {@see \api\objects\ApiResource::identifier ApiResource::identifier}
	 * 
	 * @return string This resource's {@see \api\objects\ApiResource::identifier identifier}
	 */
	public function get_identifier() {
		return $this->identifier;
	}

	/**
	 * Getter for {@see \api\objects\ApiResource::self_fields ApiResource::self_fields}
	 * 
	 * @return string[] This resource's {@see \api\objects\ApiResource::self_fields self_fields}
	 */
	public function get_self_fields() {
		return $this->self_fields;
	}

	public function get_fields() {
		return $this->fields;
	}

	/**
	 * Compares types to translate for differences between SQL types and PHP types.
	 *
	 * For example, a $php_type of "string" and an $sql_type of "varchar" will
	 * return true, because they are considered equivalent.
	 *
	 * @param string $php_type A string representing the type of the php variable (i.e.: "string", "integer")
	 * @param string $sql_type A string representing the type of the sql variable (i.i.: "varchar", "int")
	 * @return boolean True if types are considered equal, false otherwise.
	 */
	public function compare_types($php_type, $sql_type) {
		return in_array($sql_type, self::$php_sql_types[$php_type]);
	}

	/**
	 * Determines the proper PHP type to use based on the given SQL type.
	 * 
	 * Uses self::$php_sql_types to determine mappings. Will only work properly if there are no duplicated
	 * SQL types in self::$php_sql_types. I.e. TINYINT cannot be used for both boolean and int. For this
	 * reason, the SQL types BIT and TINYINT will always be translated as booleans, regardless of
	 * whether this was intended or not.
	 */
	public function get_type_from_sql($sql_type) {
		foreach(self::$php_sql_types as $php_type => $type_arr) {
			if(in_array($sql_type, $type_arr)) {
				return $php_type;
			}
		}
		return "NULL";
	}

	/**
	 * Converts PHP values into 'SQL values'.
	 *
	 * Essentially, what it does is add quotes around any value that requires it, and converts booleans to
	 * ints (0 for false, 1 for true). If the type of the PHP value does not map to the $sql_type, the
	 * function will return null and $err will be set to true.
	 *
	 * @param mixed $php_value A value of any type to be converted into a valid value for SQL (arrays and objects are never valid for SQL).
	 * @param string $sql_type A string containing the SQL type to convert to. Used for comparison to ensure types are compatible.
	 * @return mixed The converted value of $php_value that can be used for SQL. Will return NULL if the type of $php_value does not map to $sql_type in self::$php_sql_types.
	 */
	public function convert_php_sql($php_value, $sql_type, &$err = NULL) {
		//This sort of functions as a failsafe, because we should have already checked field types
		if($php_value === "true" || $php_value === "false") return $php_value === "true" ? 1 : 0;
		if(in_array($sql_type, self::$php_sql_types[gettype($php_value)])) {
			$err = false;
			if(gettype($php_value) == "string" && strpos($php_value, "\"") === false && $php_value !== "NULL") {
				return "\"" . $php_value . "\"";
			} else if(gettype($php_value) == "boolean") {
				return $php_value === true ? 1 : 0;	
			} else {
				return $php_value;
			}
		} else {
			$err = true;
			return NULL;
		}
	}

	/**
	 *
	 */
	public function convert_php_sql_filter($php_value, $sql_type, &$err = NULL) {
		$err = false;
		if(in_array($sql_type, self::$php_sql_types['boolean'])) {
			return $php_value == "true" ? 1 : 0;
		} else if(in_array($sql_type, self::$php_sql_types['integer'])) {
			return (int) $php_value;
		}

		if(gettype($php_value) == "string" && strpos($php_value, "\"") === false && $php_value !== "NULL") {
			return "\"" . $php_value . "\"";
		} else if(gettype($php_value) == "boolean") {
			return $php_value ? 1 : 0;	
		} else {
			return $php_value;
		}
	}
	
	/**
	 * Checks all fields from the request that match this resource's fields to ensure they are of the correct type (string, int, etc..)
	 * 
	 * @param api\objects\Request $request The request object for this call to the API
	 * @param string $mistyped_field A variable passed by reference that is used to give caller the name of the field that has the wrong type. Only set if a field is incorrect.
	 * @param string $mistyped_type A variable passed by reference that contains the type of the mistyped_field that was given. Only set if a field is incorrect.
	 * @return boolean False if one of the fields in the request was of the wrong type, true otherwise.
	 */
	public function check_field_types($request, &$mistyped_field, &$mistyped_type) {
		foreach($this->fields as $field) {
			if($request->has_data($field)) {
				if(!$this->compare_types(gettype($request->get_data($field)), $this->field_types[$field])) {
					$mistyped_field = $field;
					$mistyped_type = gettype($request->get_data($field));
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * This function fixes the types of fields upon receiving request. This function is called upon GET requests, as GET requests do not have a body, so
	 * all data must be included in the query string. Because of this, when we receive GET requests, all of the fields are strings. So, this function
	 * fixes that. Since $request is passed by reference, it just changes the values in the request and nothing is returned.
	 *
	 * @param api\objects\Request $request The request to fix, passed by reference.
	 */
	public function fix_types(&$request) {
		foreach($this->fields as $field) {
			if($request->has_data($field)) {
				$php_type = $this->get_type_from_sql($this->field_types[$field]);
				switch($php_type) {
					case "boolean":
						$request->set_data($field, json_decode($request->get_data($field)));
						break;
					case "double":
						$request->set_data($field, (double) $request->get_data($field));
						break;
					case "integer":
						$request->set_data($field, (integer) $request->get_data($field));
						break;
				}
			}
		}
	}
	
	/**
	 * Checks the formatting of each field in the given request that matches a field of this resource.
	 * 
	 * Essentially, what it does is check to ensure that each field is smaller than or equal in size to
	 * its defined maximum in {@see \api\objects\ApiResource::field_sizes ApiResource::field_sizes}. For
	 * strings, this means that the number of characters in the string must be less than or equal to the
	 * field_size. For integers, the value of the number must be less than or equal to the field_size. Additionally,
	 * it will ensure that DATE, DATETIME, TIMESTAMP, TIME, and YEAR data types have the proper format.
	 * 
	 * @param api\objects\Request $request The request object for this call to the API
	 * @param boolean $is_oversize A boolean passed by reference that tells whether the error was an oversize field. If false, a field's format is incorrect.
	 * @param string $incorrect_field A variable passed by reference that contains the field that was too large or formatted incorrectly. Only set if an oversized field was found in the request.
	 * @param string $given_size A variable passed by reference that contains the size of the field that was too large. Only set if an oversized field was found in the request.
	 * @return boolean False if one of the fields in the request was oversized or formatted incorrectly, true otherwise.
	 */
	public function check_field_formats($request, &$is_oversize, &$incorrect_field, &$given_size) {
		foreach($this->fields as $field) {
			if($request->has_data($field)) {
				$php_type = gettype($request->get_data($field));
				
				if($php_type == "string") {
					if(strlen($request->get_data($field)) > $this->field_sizes[$field]) {
						$is_oversize = true;
						$incorrect_field = $field;
						$given_size = strlen($request->get_data($field));
						return false;
					}

					if($this->field_types[$field] == "DATE") {
						if(!preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $request->get_data($field))) {
							$is_oversize = false;
							$incorrect_field = $field;
							return false;
						}
					} else if($this->field_types[$field] == "DATETIME" || $this->field_types[$field] == "TIMESTAMP") {
						if(!preg_match('/\A\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}\z/', $request->get_data($field))) {
							$is_oversize = false;
							$incorrect_field = $field;
							return false;
						}
					} else if($this->field_types[$field] == "TIME") {
						if(!preg_match('/\A\d{2}:\d{2}:\d{2}\z/', $request->get_data($field))) {
							$is_oversize = false;
							$incorrect_field = $field;
							return false;
						}
					} else if($this->field_types[$field] == "YEAR") {
						if(!preg_match('/\A\d{4}\z/', $request->get_data($field))) {
							$is_oversize = false;
							$incorrect_field = $field;
							return false;
						}
					}
				} else if($php_type == "integer" || $php_type == "double") {
					if($request->get_data($field) > $this->field_sizes[$field]) {
						$is_oversize = true;
						$incorrect_field = $field;
						$given_size = $request->get_data($field);
						return false;
					}
				}
			}
		}
		return true;
	}
	
	/**
	 * Called by the resource's endpoint to route the request to the proper handler. This decision is made based on the {@see \api\objects\Request::type Request::type}.
	 * 
	 * Before routing the request to its proper handler, it will run {@see \api\objects\ApiResource::check_field_types() ApiResource::check_field_types()} and
	 * {@see \api\objects\ApiResource::check_field_formats() ApiResource::check_field_formats()} on the request it received. If any problems are found in these
	 * checks, it will respond with a descriptive error message and execution will terminate. Once the appropriate handler has been run, the response will be
	 * sent.
	 * 
	 * @param api\objects\Request $request The request that was created for this call to the API.
	 * @return void
	 */
	public function handle_request($request) {
		$origin_url = $request->has_data("_api_url") ? $request->get_data("_api_url") : "unknown";
		$user_id = "unknown";
		if($request->has_data("_token")) {
			$ret = $this->db->select_first("SELECT * FROM APITokens WHERE Token = '" . $request->get_data("_token") . "'", $res);
			if($ret) {
				$user_id = $res['user_id'];
			}
		}
		$this->db->conn->query("INSERT INTO APILog (origin_url, resource, request_type, user_id, request_ip) VALUES ('" . $origin_url . "', '" . $this->resource_name . "', '" . $request->type . "', '" . $user_id . "', '" . $_SERVER['REMOTE_ADDR'] . "');");

		$response = new Response();
		if($_SERVER['REQUEST_METHOD'] == "GET") $this->fix_types($request);
		if($this->check_field_types($request, $mistyped_field, $mistyped_type)) {
			if($this->check_field_formats($request, $is_oversize, $incorrect_field, $given_size)) {
				switch($request->type) {
					case "GET":
						$this->handle_get($request, $response);
						break;
					case "POST":
						$this->handle_post($request, $response);
						break;
					case "PUT":
						$this->handle_put($request, $response);
						break;
					case "DELETE":
						$this->handle_delete($request, $response);
						break;
					case "OPTIONS":
						$this->handle_options($request, $response);
						break;
					default:
						$this->handle_unknown($request, $response);
				}
			} else {
				if($is_oversize) {
					$response->err_oversize_field($this->snake_name, $incorrect_field, $given_size, $this->field_sizes[$incorrect_field]);
				} else {
					$response->err_incorrect_format($this->snake_name, $incorrect_field, $request->get_data($incorrect_field));
				}
			}
		} else {
			$response->err_incorrect_type($this->snake_name, $mistyped_field, $mistyped_type, $this->get_type_from_sql($this->field_types[$mistyped_field]));
		}
		$response->respond();
	}
	
	/* Default response messages - Override these to implement custom handler function for a resource */

	/**
	 * Handler for requests using 'GET' http method. Requests using 'GET' should be used to retrieve records for a resource.
	 *
	 * The request does not need to contain anything at all. By default, it will just list out the first results for the resource,
	 * the quantity of which is determined by the resource's default list amount.
	 *
	 * Fields can be provided to filter out any records that don't match the field provided. Additionally, a filter string can be given for
	 * more complex filtering of records.
	 *
	 * There are also several other fields that can be provided to manipulate the way the data is displayed. Check the documentation for more
	 * information.
	 * 
	 * See {@see \api\objects\ApiResource::handle_request() ApiResource::handle_request()} for more information on the general flow of API requests.
	 */
	public function handle_get($request, &$response) {
		$err = "";
		//Check to ensure permissions are met for this request
		if($request->has_data("_token")) {
			$perms = check_perms($request->get_data("_token"), $this->resource_name, $this, "GET", $err, $err_additional, $this->has_self, $request);
		} else {
			$perms = false;
		}
		if ($perms) {
			//Determine field to sort by
			if($request->has_data("_sort_by")) {
				$sort_field = $request->get_data("_sort_by");
			} else {
				$sort_field = $this->identifier;
			}

			//Determine sort direction
			if($request->has_data("_reverse_sort") && $request->get_data("_reverse_sort")) {
				$sort_direction = "DESC";
			} else {
				$sort_direction = "ASC";
			}

			//Determine the max amount of items to list
			if($request->has_data("_limit") && $request->get_data("_limit") > 0) {
				$limit = min($request->get_data("_limit"), $this->max_list);
			} else {
				$limit = $this->default_list_amt;
			}

			//Determine the page (offset)
			if($request->has_data("_page")) {
				$page = $request->get_data("_page");
			} else {
				$page = 0;
			}

			//build filter string here
			$filter_res = $this->build_filters($request, $filters, $err, $err_additional);
			if($filter_res) {
				$this->read_multiple($limit, $page, $sort_field, $sort_direction, $filters, $resources);
				$response->ok(true);
				$response->add_data("_page", $page);
				$response->add_data($this->snake_name_plural, $resources);
			} else {
				if($err == "BAD_FORMAT") {
					$response->err_malformed_filters($this->resource_name, "GET");
				} else if($err == "UNK_FIELD") {
					$response->err_unknown_field($this->resource_name, "GET", $err_additional);
				}
			}
		} else {
			//Something went wrong with authorization
			$response->set_status(UNAUTHORIZED);
			if($err == "") {
				$response->error("No access token was provided!");
			} else {
				switch($err) {
					case "NOTOKEN":
						$response->error("The given access token could not be found");
						$response->add_data("request_token", $request->get_data("_token"));
						break;
					case "EXPIRED":
						$response->error("The provided token was expired! Please acquire a new token");
						$response->add_data("expired", $err_additional);
						break;
					case "NOSCOPE":
						$response->error("The permission scope associated with this action could not be found.");
						$response->add_data("scope", $err_additional);
						break;
					case "NOPERM":
						$response->error("The token you provided does not include the requested action in its scope.");
						$response->add_data("scope", $err_additional);
						break;
					default:
						$response->error("An unknown error took place during permission check!");
				}
			}
		}
	}
	
	/**
	 * Handler for requests using 'POST' http method. Requests using 'POST' should be used to create new records of this resource.
	 * 
	 * The request must contain every field listed in this resource's {@see \api\objects\ApiResource::post_required ApiResource::post_required}.
	 * The request may also include fields set in this resource's {@see \api\objects\ApiResource::post_optional ApiResource::post_optional} but they are not required.
	 * 
	 * See {@see \api\objects\ApiResource::handle_request() ApiResource::handle_request()} for more information on the general flow of API requests.
	 */
	public function handle_post($request, &$response) {
		if($request->has_data("_token") && check_perms($request->get_data("_token"), $this->resource_name, $this, "POST", $err, $err_additional, $this->has_self, $request)) {
			$hasRequiredFields = true;
			$missingField = '';
			foreach($this->post_required as $requirement) {
				if(!$request->has_data($requirement)) {
					$hasRequiredFields = false;
					$missingField = $requirement;
				}
			}
			
			if($hasRequiredFields) {
				$sql = "SELECT * FROM " . $this->table_name . " WHERE";
				$isFirst = true;
				foreach($this->post_required as $field) {
					if($isFirst) {
						$isFirst = false;
					} else {
						$sql .= " AND";
					}
					$field_formatted = $this->needs_quotes($field) ? "'" . $request->get_data($field) . "'" : $request->get_data($field);
					$sql .= " " . $field . " = " . $field_formatted;
				}
				$ret = $this->db->select_first($sql, $res);
				if($ret) {
					$response->err_already_exists($this->snake_name);
				} else {
					foreach($this->post_required as $requirement) {
						$this->field_values[$requirement] = $request->get_data($requirement);
					}
					foreach($this->post_optional as $option) {
						if($request->has_data($option)) {
							$this->field_values[$option] = $request->get_data($option);
						}
					}
					if($this->create()) {
						$response->set_status(OK);
						$response->ok(true);
						if($this->needs_quotes($this->identifier)) {
							$this->db->select_first("SELECT * FROM " . $this->table_name . " WHERE " . $this->identifier . " = '" . $request->get_data($this->identifier) . "'", $new_resource);
						} else {
							$this->db->select_first("SELECT * FROM " . $this->table_name . " WHERE " . $this->identifier . " = " . $this->db->conn->insert_id, $new_resource);
						}
						$response->add_data($this->snake_name, $new_resource);
					} else {
						$response->set_status(SERVER_ERROR);
						$response->error($err);
					}
				}
			} else {
				$response->err_missing_field($missingField, "POST", $this->snake_name);
			}
		} else {
			$response->set_status(UNAUTHORIZED);
			if(!isset($err)) {
				$response->error("No access token was provided!");
			} else {
				switch($err) {
					case "NOTOKEN":
						$response->error("The given access token could not be found");
						$response->add_data("request_token", $request->get_data("_token"));
						break;
					case "EXPIRED":
						$response->error("The provided token was expired! Please acquire a new token");
						$response->add_data("expired", $err_additional);
						break;
					case "NOSCOPE":
						$response->error("The permission scope associated with this action could not be found.");
						$response->add_data("scope", $err_additional);
						break;
					case "NOPERM":
						$response->error("The token you provided does not include the requested action in its scope.");
						$response->add_data("scope", $err_additional);
						break;
					default:
						$response->error("An unknown error took place during permission check!");
				}
			}
		}
	}
	
	/**
	 * Handler for requests using 'PUT' http method. Requests using 'PUT' should be used to modify existing records of this resource.
	 * 
	 * The request must contain at least one field listed in this resource's {@see \api\objects\ApiResource::put_options ApiResource::put_options}
	 * but can contain as many of them as desired. Each of these fields will be modified.
	 * 
	 * Additionally, the request MUST contain a value for this resource's {@see \api\objects\ApiResource::identifier ApiResource::identifier} so that the
	 * system can determine which record to modify.
	 * 
	 * See {@see \api\objects\ApiResource::handle_request() ApiResource::handle_request()} for more information on the general flow of API requests.
	 */
	public function handle_put($request, &$response) {
		if($request->has_data("_token") && check_perms($request->get_data("_token"), $this->resource_name, $this, "PUT", $err, $err_additional, $this->has_self, $request)) {
			if($request->has_data($this->identifier)) {
				if($this->read($request->get_data($this->identifier))) {
					$is_ok = true;
					foreach($this->put_options as $option) {
						if($request->has_data($option) && $option != $this->identifier) {
							if(!$this->edit_field($request->get_data($this->identifier), $option, $request->get_data($option))) {
								$is_ok = false;
							}
						}
					}
					if($is_ok) {
						$this->read($request->get_data($this->identifier));
						$response->set_status(OK);
						$response->ok(true);
						$response->add_data($this->snake_name, $this->get_array());
					} else {
						$response->set_status(SERVER_ERROR);
						$response->error("Something went wrong while attempting to update values");
					}
				} else {
					$response->err_not_found($this->identifier, $request->get_data($this->identifier), $this->snake_name);
				}
			} else {
				$response->err_missing_field($this->identifier, "PUT", $this->snake_name);
			}
		} else {
			$response->set_status(UNAUTHORIZED);
			if(!isset($err)) {
				$response->error("No access token was provided!");
			} else {
				switch($err) {
					case "NOTOKEN":
						$response->error("The given access token could not be found");
						$response->add_data("request_token", $request->get_data("_token"));
						break;
					case "EXPIRED":
						$response->error("The provided token was expired! Please acquire a new token");
						$response->add_data("expired", $err_additional);
						break;
					case "NOSCOPE":
						$response->error("The permission scope associated with this action could not be found.");
						$response->add_data("scope", $err_additional);
						break;
					case "NOPERM":
						$response->error("The token you provided does not include the requested action in its scope.");
						$response->add_data("scope", $err_additional);
						break;
					default:
						$response->error("An unknown error took place during permission check!");
				}
			}
		}
	}
	
	/**
	 * Handler for requests using 'DELETE' http method. Requests using 'DELETE' should be used to remove existing records of this resource.
	 * 
	 * The request must contain a value for this resource's {@see \api\objects\ApiResource::identifier ApiResource::identifier} so that the system
	 * can determine which record to delete. Unfortunately, it is not currently possible to delete more than one record at once, but this is something
	 * that would be possible to implement if it were to be found necessary.
	 * 
	 * See {@see \api\objects\ApiResource::handle_request() ApiResource::handle_request()} for more information on the general flow of API requests.
	 */
	public function handle_delete($request, &$response) {
		if($request->has_data("_token") && check_perms($request->get_data("_token"), $this->resource_name, $this, "DELETE", $err, $err_additional, $this->has_self, $request)) {
			if($request->has_data($this->identifier)) {
				$id_formatted = $this->needs_quotes($this->identifier) ? "'" . $request->get_data($this->identifier) . "'" : $request->get_data($this->identifier);
				$ret = $this->db->select_first("SELECT * FROM " . $this->table_name . " WHERE " . $this->identifier . " = " . $id_formatted, $row);
				if($ret) {
					$ret1 = $this->db->delete_from("DELETE FROM " . $this->table_name . " WHERE " . $this->identifier . " = " . $id_formatted, $num_rows);
					if($ret1) {
						$response->set_status(OK);
						$response->ok(true);
						$response->add_data($this->identifier, $request->get_data($this->identifier));
					} else {
						$response->set_status(SERVER_ERROR);
						$response->error("Something went wrong while attempting to delete the requested resource of type '" . $this->snake_name . "' with " . $this->identifier . ": '" . $request->get_data($this->identifier) . "'");
					}
				} else {
					$response->err_not_found($this->identifier, $request->get_data($this->identifier), $this->snake_name);
				}
			} else {
				$response->err_missing_field($this->identifier, "DELETE", $this->snake_name);
			}
		} else {
			$response->set_status(UNAUTHORIZED);
			if(!isset($err)) {
				$response->error("No access token was provided!");
			} else {
				switch($err) {
					case "NOTOKEN":
						$response->error("The given access token could not be found");
						$response->add_data("request_token", $request->get_data("_token"));
						break;
					case "EXPIRED":
						$response->error("The provided token was expired! Please acquire a new token");
						$response->add_data("expired", $err_additional);
						break;
					case "NOSCOPE":
						$response->error("The permission scope associated with this action could not be found.");
						$response->add_data("scope", $err_additional);
						break;
					case "NOPERM":
						$response->error("The token you provided does not include the requested action in its scope.");
						$response->add_data("scope", $err_additional);
						break;
					default:
						$response->error("An unknown error took place during permission check!");
				}
			}
		}
	}
	
	/**
	 * Handler for requests using 'OPTIONS' http method. Requests using 'OPTIONS' are rarely explicitly made. This handler is in place, because modern browsers
	 * will often send an OPTIONS request before making an actual request to determine what the endpoint will allow them to send, as well as what they should expect
	 * in return.
	 * 
	 * See {@see \api\objects\ApiResource::handle_request() ApiResource::handle_request()} for more information on the general flow of API requests.
	 */
	public function handle_options($request, &$response) {
		header("Access-Control-Allow-Origin: *"); //Allows requests from outside domains to go through
		header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE"); //These are the only methods that should be allowed
		header("Access-Control-Allow-Headers: Content-Type, Authorization"); //Only allow these headers
		$response->set_status(OK);
		$response->add_data("ok", true);
	}
	
	/**
	 * Handler for requests using any http method that we have not considered. This will just respond with a status code and message saying that this
	 * operation has not yet been implemented.
	 * 
	 * See {@see \api\objects\ApiResource::handle_request() ApiResource::handle_request()} for more information on the general flow of API requests.
	 */
	public function handle_unknown($request, &$response) {
		$response->set_status(NOT_IMPLEMENTED);
		$response->add_data("ok", false);
		$response->add_data("error", "Operation not yet implemented");
	}
	
	/**
	 * This function will take a field and tell you if the value for that field should have quotes around it when used in an SQl statement.
	 * For example, a field that has type varchar requires quotes: user_id = 'asdf', but an int does not: auth_level = 0.
	 *
	 * @param string $field The name of the field you're checking. This string should exist in the $this->fields array.
	 * @return boolean True if the given field needs quotes, false otherwise.
	 */
	public function needs_quotes($field) {
		switch($this->field_types[$field]) {
			case "tinyint":
			case "smallint":
			case "mediumint":
			case "int":
			case "bigint":
			case "decimal":
			case "float":
			case "double":
			case "real":
			case "bit":
			case "boolean":
			case "serial":
				return false;
			default:
				return true;
		}
	}
	
	/**
	 * This function will pull a record from the database and store it inside this resource's {@see \api\objects\ApiResource::field_values ApiResources::field_values} field.
	 * 
	 * @param mixed $identifier_value The value of the {@see \api\objects\ApiResource::identifier ApiResource::identifier} of the record that we wish to retrieve.
	 * @return boolean False if the record with {@see \api\objects\ApiResource::identifier ApiResource::identifier} $identifier_value could not be found, true otherwise.
	 */
	public function read($identifier_value) {
		if($this->needs_quotes($this->identifier)) $identifier_value = "'" . $identifier_value . "'";
		$result = $this->db->select_first("SELECT * FROM " . $this->table_name . " WHERE " . $this->identifier . " = " . $identifier_value . ";", $row);
		if($result) {
			foreach($this->fields as $field) {
				$this->field_values[$field] = $row[$field];
			}
			return true;
		} else {
			return false;
		}
	}

	public function is_operator($word) {
		$operator_arr = array('=', '>', '<', '>=', '<=', '<>', 'OR', 'AND', 'NOT', '(', ')');
		return in_array($word, $operator_arr);
	}

	/**
	 * Constructs a string containing SQL comparisons for a query based on the filter string provided.
	 *
	 * This function separates the string into an array of words. Then, it goes through and finds sets of
	 * words contained within quotes, combining these strings into single elements in the array. Then, it
	 * iterates through each element in the array. The first word encountered will be treated as a field,
	 * and the function will check that this field exists in this resource, returning false if it does not.
	 * The second word will be treated as a comparator (>, <, =, !=, etc) and will be checked to ensure that
	 * it is a valid comparator, and converted into the proper SQl version of that comparator. The third word
	 * will be treated as a value and will be run through {@see \api\objects\ApiResource::convert_php_sql_filter ApiResource::convert_php_sql_filter},
	 * which is the same as {@see \api\objects\ApiResource:convert_php_sql ApiResource::convert_php_sql}, but with less
	 * strict requirements, as the contents of the query string have not already been validated in format, type, etc.
	 *
	 * @param string $string The "filter" string to parse.
	 * @param string $parsed_string A string (passed by reference) that will contain the parsed string.
	 * @param string $err A string containing information about what error occurred (only set if there was an error)
	 * @param string $err_additional A string containing additional information about errors.
	 * @return boolean False if any errors were encountered, true otherwise.
	 */
	public function parse_filter_string($string, &$parsed_string, &$err, &$err_additional) {
		$words = explode(' ', $string); //Make string into array of words, separated by spaces
		
		while(true) {
			$inside_quotes = false;
			$quotes_start = 0;
			$quotes_end = 0;

			foreach($words as $index => $word) {
				if(substr_count($word, '&quot;') == 1) {
					$inside_quotes = true;
					$quotes_start = $index;
					break;
				}
			}

			if(!$inside_quotes) {
				break;
			}
			
			foreach($words as $index => $word) {
				if(substr_count($word, '&quot;') == 1 && $index > $quotes_start) {
					$inside_quotes = false;
					$quotes_end = $index;
					break;
				}
			}

			if($inside_quotes) {
				$err = "BAD_FORMAT";
				return false;
			}

			$new_arr = array_slice($words, 0, $quotes_start);
			$quoted_string_arr = array_slice($words, $quotes_start, $quotes_end - $quotes_start + 1);
			$quoted_string = implode(' ', $quoted_string_arr);
			$new_arr[] = $quoted_string;
			$new_arr = array_merge($new_arr, array_slice($words, $quotes_end + 1));
			$words = $new_arr;
		}

		$last_was_comparator = false;
		$two_ago = "";

		foreach($words as $index => $word) {
			if($word == '') unset($words[$index]); //Ignore empty words (come from double spaces);
			
			switch($word) {
				case "&lt;=":
					$words[$index] = "<=";
					break;
				case "&gt;=":
					$words[$index] = ">=";
					break;
				case "&lt;":
					$words[$index] = "<";
					break;
				case "&gt;":
					$words[$index] = ">";
					break;
				case "||":
					$words[$index] = "OR";
					break;
				case "&amp;&amp;":
					$words[$index] = "AND";
					break;
				case "!=":
					$words[$index] = "<>";
					break;
				case "!":
					$words[$index] = "NOT";
					break;
				case "before":
					$words[$index] = "<";
					break;
				case "after":
					$words[$index] = ">";
					break;
			}

			$words[$index] = str_replace('&quot;', '"', $words[$index]);
			$word = $words[$index];

			if($last_was_comparator) {
				$last_was_comparator = false;
				if($index < 2) {
					$err = "BAD_REQUEST";
					return false;
				}
				if(in_array($words[$index - 2], $this->fields)) {
					$words[$index] = $this->convert_php_sql_filter($word, $this->field_types[$words[$index - 2]]);
					$word = $words[$index];
				} else {
					$err = "UNK_FIELD";
					$err_additional = $words[$index - 2];
					return false;
				}
			} else if(!is_numeric($word) && strpos($word, '"') === false && !$this->is_operator($word) && !in_array($word, $this->fields)) {
				$err = "UNK_FIELD";
				$err_additional = $word;
				return false;
			}

			if($word === "<=" || $word === ">=" || $word === "<" || $word === ">" || $word === "=" || $word === "<>") {
				$last_was_comparator = true;
			}
		}

		$parsed_string = implode(' ', $words);
		return true;
	}

	/**
	 * Constructs a string containing the SQL "WHERE" clause for this request. If any of the fields for this resource
	 * exist, they will be added in the form of '[field_name1] = [field_value1] AND [field_name2] = [field_value2]'.
	 * Additionally, this function will check for the '_filter' field and, if provided, add it to the WHERE clause
	 * (inside parentheses) after parsing it to SQL using {@see \api\objects\ApiResource::parse_filter_string ApiResource::parse_filter_string}.
	 *
	 * @param \api\objects\Request $request The request object for this request
	 * @param string $filters A string passed by reference that will contain the WHERE clause for the query once the function has run
	 * @param string $err A string passed by reference that gives information about errors that potentially occur
	 * @param string $err_additional A string passed by reference that gives additional information about errors.
	 * @return boolean False if any errors were encountered, true otherwise.
	 */
	public function build_filters($request, &$filters, &$err, &$err_additional) {
		$filter_string = "";
		$has_first_filter = false;
		foreach($this->fields as $field) {
			if($request->has_data($field)) {
				if(!$has_first_filter) {
					$filter_string .= " WHERE";
					$has_first_filter = true;
				} else {
					$filter_string .= " AND";
				}
				
				$filter_string .= " " . $field . " = " . $this->convert_php_sql($request->get_data($field), $this->field_types[$field]);
			}
		}
		
		if($request->has_data("_filter")) {
			if(!$has_first_filter) {
				$filter_string .= " WHERE";
				$has_first_filter = true;
			} else {
				$filter_string .= " AND";
			}

			$res = $this->parse_filter_string($request->get_data("_filter"), $parsed_filter, $err_2, $err_additional_2);
			
			if($res) {
				$filter_string .= " (" . $parsed_filter . ")";
			} else {
				$err = $err_2;
				$err_additional = $err_additional_2;
				return false;
			}
		}

		$filters = $filter_string;
		return true;
	}
	
	/**
	 * This function will retreieve multiple records from the database based on the parameters it is given.
	 * 
	 * @param int $limit The maximum number of records to retrieve
	 * @param string $sort_field The field by which to order records retrieved
	 * @param string $sort_direction The direction to order records in. Options are: ASC, DESC
	 * @param string $filters An SQL WHERE clause that will apply filtering to the query
	 * @param Array[] $resources A variable passed by value that will be filled with an array of associative arrays. That is, each entry in the array is an associative array that contains all the fields as keys and their values as values.
	 * @return boolean True if one or more records was read into $resources, false otherwise
	 */
	public function read_multiple($limit, $page, $sort_field, $sort_direction, $filters, &$resources) {
		$resources = $this->db->select("SELECT * FROM " . $this->table_name . $filters . " ORDER BY " . $sort_field . " " . $sort_direction . " LIMIT " . $limit . " OFFSET " . ($page * $limit));
		
		if(!$resources || empty($resources)) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * This function will create a new record for a resource. The values of the fields for the newly created record are determined
	 * from the current values of {@see \api\objects\ApiResource::field_values ApiResource::field_values}. This function assumes that the field_values are set
	 * for every field listed in {@see \api\objects\ApiResource::post_required ApiResource::post_required}. The fields that are listed in
	 * {@see \api\objects\ApiResource::post_optional ApiResource::post_optional} will be included if they are set, but do not need to be set.
	 * Any provided values are run through {@see \api\objects\ApiResource::convert_php_sql ApiResource::convert_php_sql} to ensure they have the
	 * proper form for use in an SQl query.
	 * 
	 * @return boolean False if an error occurred when attempting to create the record, true otherwise.
	 */
	public function create() {
		$query = "INSERT INTO " . $this->table_name . " SET";
		$is_first = true;

		//Add all required fields to query
		foreach($this->post_required as $requirement) {
			if($is_first) {
				$is_first = false;
			} else {
				$query .= ",";
			}
			$field_formatted = $this->convert_php_sql($this->field_values[$requirement], $this->field_types[$requirement]);
			$query .= " " . $requirement . " = " . $field_formatted;
		}

		//Add any optional fields that were provided to query
		foreach($this->post_optional as $option) {
			if(array_key_exists($option, $this->field_values)) {
				if($is_first) {
					$is_first = false;
				} else {
					$query .= ",";
				}
				$field_formatted = $this->convert_php_sql($this->field_values[$option], $this->field_types[$option]);
				$query .= " " . $option . " = " . $field_formatted;
			}
		}

		$result = $this->db->conn->query($query);
		if($this->db->conn->affected_rows <= 0) {
			return false;
		} else {
			return true;
		}
	}
	
	/**
	 * This function will query the database to modify an existing record.
	 * 
	 * @param string $identifier The value of the {@see \api\objects\ApiResource::identifier ApiResource::identifier} for the record which we want to modify
	 * @param string $field_name The name of the field which we want to modify
	 * @param mixed  $field_val  The value that we want to set the field to
	 * @return boolean False if an error occurs when attempting to modify the record, true otherwise
	 */
	public function edit_field($identifier, $field_name, $field_val) {
		$field_formatted = $this->convert_php_sql($field_val, $this->field_types[$field_name]);
		$id_formatted = $this->convert_php_sql($identifier, $this->field_types[$this->identifier]);
		$sql = "UPDATE " . $this->table_name . " SET " . $field_name . " = " . $field_formatted . " WHERE " . $this->identifier . " = " . $id_formatted;
		$ret = $this->db->update($sql, $n_rows);
		return $ret;
	}
	
	/**
	 * Creates an associative array that contains the values of this resource's {@see \api\objects\ApiResource::field_values ApiResource::field_values} indexed by each field's name.
	 * 
	 * This array is usually used in tandem with {@see \api\objects\Response::add_data() Response::add_data()}, as this associative array will translate to an object in JSON
	 * when the API responds.
	 * 
	 * @return mixed[] An associative array that contains the values of this resource's {@see \api\objects\ApiResource::field_values ApiResource::field_values} indexed by each field's name
	 */
	public function get_array() {
		$arr = array();
		foreach($this->fields as $field) {
			$arr[$field] = $this->field_values[$field];
		}
		return $arr;
	}
}
?>