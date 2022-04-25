<?php
/**
 * A class representing a connection to the database. Contains a couple of functions that don't really add functionality but
 * make life easier and make code more readable.
 *
 * @package api\objects
 * @license All Rights Reserved
 * @copyright Andrew Graber
 * @author Andrew Graber <graber15@purdue.edu>
 */
class Database {
	private $hostname;
	private $db_name;
	private $username;
	private $password;
	/** @var object $conn A mysqli object that is a connection to the database */
	public $conn;
	
	/**
	 * Creates a new Database object and creates its connection to the mysql database
	 */
	public function __construct() {
		$data = json_decode(file_get_contents("/var/www/data/db_credentials.json"), true);

		$this->hostname = $data['hostname'];
		$this->db_name = $data['db_name'];
		$this->username = $data['username'];
		$this->password = $data['password'];

		$this->conn = new mysqli($this->hostname, $this->username, $this->password, $this->db_name);
		//Check connection
		if($this->conn->connect_error) {
			die("Connection failed: " . $this->conn->connect_error);
		}
		$this->conn->query("INSERT INTO ConnectionLog (user_id, conn_from, thread_id) VALUES ('unknown', '" . $_SERVER['PHP_SELF'] . "', '" . $this->conn->thread_id . "');");
	}
	
	/**
	 * Ensures connection to database is closed when object goes out of scope
	 */
	public function __destruct() {
		$this->conn->close();
	}
	
	/**
	 * Simplifies the process of running a select statement through the database
	 * 
	 * Queries the database with the given statement and returns an array of the results
	 *
	 * @param string $sql A SELECT SQL Statement
	 * @return array[] An array with each entry being an associative array representing a row returned from the query
	 */
	public function select($sql) {
		$response = $this->conn->query($sql);
		$result = array();
		while($row = $response->fetch_assoc()) {
			array_push($result, $row);
		}
		return $result;
	}
	
	/**
	 * Simplifies the process of getting a single row through a select statement
	 *
	 * Queries the database with the given statement and returns an associative array that is the first row returned from the query
	 *
	 * @param string $sql A SELECT SQL Statement
	 * @param array $res A variable passed by reference that the response object will be placed into
	 * @return bool True if a row was found, false if not.
	 */
	public function select_first($sql, &$res) {
		$response = $this->conn->query($sql);
		if($response && $response->num_rows > 0) {
			$res = $response->fetch_assoc();
			return true;
		}
		return false;
	}
	
	/**
	 * Simplifies the process of updating information in the database.
	 *
	 * Function is the same as $this->delete_from. The two functions are provided to give better context for what is happening
	 * behind the scenes.
	 *
	 * @param string $sql An UPDATE SET SQL Statement
	 * @param int $num_rows The number of rows changed by the query
	 * @return bool True if at least one row was changed, false otherwise
	 */
	public function update($sql, &$num_rows) {
		$response = $this->conn->query($sql);
		$num_rows = $this->conn->affected_rows;
		return $this->conn->affected_rows > -1;
	}
	
	/**
	 * Simplifies the process of deleting rows from the database.
	 *
	 * Name is delete_from instead of delete, because 'delete' is a reserved word in PHP
	 *
	 * @param string $sql A DELETE FROM SQL Statement
	 * @param int $num_rows The number of rows deleted by the query
	 * @return bool True if at least one row was deleted. False otherwise
	 */
	public function delete_from($sql, &$num_rows) {
		$response = $this->conn->query($sql);
		$num_rows = $this->conn->affected_rows;
		return $this->conn->affected_rows > 0;
	}
}
?>