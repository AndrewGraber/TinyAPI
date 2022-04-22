<?php
interface ApiResourceInterface {
	public function handle_request($request);
	public function handle_get($request, &$response);
	public function handle_post($request, &$response);
	public function handle_put($request, &$response);
	public function handle_delete($request, &$response);
	public function handle_options($request, &$response);
	public function handle_unknown($request, &$response);
}
?>