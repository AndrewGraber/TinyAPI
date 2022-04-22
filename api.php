<?php
/*
//Error Reporting Remove comments when Debugging
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE); // */
require_once("objects/Database.php");
require_once("objects/ApiResource.php");
require_once("objects/Email.php");
require_once("objects/Request.php");
require_once("objects/Response.php");

if(isset($_GET['resource'])) {
    $db = new Database();
    if($_GET['resource'] == 'email') {
        $resource = new Email($db, $_GET['resource']);
    } else {
        $resource = new ApiResource($db, $_GET['resource'], $err);
        if($err) {
            $response = new Response();
            $response->set_status(NOT_FOUND);
            $response->error("Resource not found!");
            $response->respond();
            exit();
        }
    }
    $resource->handle_request(new Request());
} else {
    $response = new Response();
    $response->set_status(BAD_REQUEST);
    $response->error("No Resource Provided!");
    $response->respond();
}
?>