<?php
require_once("ApiResource.php");
require_once("util/PHPMailerAutoload.php");

class Email extends ApiResource {
	protected $resource_name = "Email";
	protected $identifier = "";
	protected $identifier_needs_quotes = false;
	protected $default_list_amt = 0;
	protected $max_list = 0;
	protected $fields = array();
	protected $fields_need_quotes = array();
	protected $field_types = array();
	protected $field_sizes = array();
	protected $defining_fields = array();
	protected $post_required = array();
	protected $post_optional = array();
	protected $patch_options = array();
	protected $has_self = false;

	function __construct($db) {
		$this->db = $db;
	}
	
	function handle_get($request, &$response) {
		$response->set_status(NOT_ALLOWED);
		$response->add_data("ok", false);
		$response->add_data("error", "Email only accepts POST requests!");
	}
	
	function handle_post($request, &$response) {
		if($request->has_data("sender") && $request->has_data("recipient") && $request->has_data("subject") && $request->has_data("body") && $request->has_data("alt_body")) {
			$sender = $request->get_data("sender");
			$recipient = $request->get_data("recipient");
			$subject = $request->get_data("subject");
			$body = $request->get_data("body");
			$altbody = $request->get_data("alt_body");
			
			$email = new PHPMailer();
			$email->setFrom("$sender");
			$email->addReplyTo("itaplabs@purdue.edu", "ITaP Admin");
			$email->addAddress("$recipient");
			if($request->has_data("recipient_2")) {
				$email->addAddress($request->get_data("recipient_2"));
			}
			if($request->has_data("cc")) {
				$email->addCC($request->get_data("cc"));
			}
			$email->Subject = "$subject";
			$email->Body = $body;
			$email->AltBody = $altbody;
			$email->isHTML(true);
			
			if(!$email->send()) {
				$response->set_status(SERVER_ERROR);
				$response->error("Something went wrong when attempting to send email!");
				$response->add_data("error_message", $email->ErrorInfo);
			} else {
				$response->set_status(OK);
				$response->ok(true);
			}
		} else {
			$response->set_status(BAD_REQUEST);
			$response->error("Something was missing! Requires sender, recipient, subject, body, and alt_body.");
		}
	}
	
	function handle_patch($request, &$response) {
		$response->set_status(NOT_ALLOWED);
		$response->add_data("ok", false);
		$response->add_data("error", "Email only accepts POST requests!");
	}
	
	function handle_delete($request, &$response) {
		$response->set_status(NOT_ALLOWED);
		$response->add_data("ok", false);
		$response->add_data("error", "Email only accepts POST requests!");
	}
}
?>