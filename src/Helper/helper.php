<?php 

if (!function_exists('route_path')){
	function route_path()
	{
		return app_path('Routes');
	}
}