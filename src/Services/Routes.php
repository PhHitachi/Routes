<?php 	

namespace PhHitachi\Routes\Services;

use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Route as Router;

Class Routes //extends Router
{
	/**
	* alias of 'as' in group methods
	*
	* @var string
	*/
	public $name;

	/**
	* set $prefix to the group
	*
	* @var string
	*/
	public $prefix;

	/**
	* defined as parameter key on resource
	*
	* @var string
	*/
	public $parameter;

	/**
	* defined middlewares on group 
	*
	* @var array
	*/
	public $middleware = [];

	/**
	* define if resource
	*
	* @var bool
	*/
	public $resource = true;

	/**
	* define route class as group
	*
	* @var bool
	*/
	public $group = true;

	/**
	* defined $domain for group routes
	*
	* @var array
	*/
	public $domain;

	/**
	* get all url of action
	*
	* @var bool
	*/
	protected $uri;

	/**
	* get single action
	*
	* @var bool
	*/
	protected $action;

	/**
	* set $except to disallowed on route
	*
	* @var array
	*/
	protected $except;

	/**
	* set $only to add only allowed action
	*
	* @var array
	*/
	protected $only;

	/**
	* get All defined actions
	*
	* @var array
	*/
	protected $actions = [];

	/**
	* defined methods
	*
	* @var array
	*/
	protected $methods = [];

	/**
	* defined $separator for names
	*
	* @var string
	*/
	protected $separator = '.';

	/**
	* defined $settings for configuration of the class
	*
	* @var string
	*/
	protected $settings;

	/**
	* All of the verbs supported by the router.
	*
	* @var array
	*/
	protected $verbs = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

	/**
	* initials run
	*
	* @return void
	*/
	public function init()
	{
		$this->settings = $this->settings();

		$this->setMethods();
	}

	/**
	* Set disallowed actions
	*
	* @param  array $actions
	* @return void
	*/
	protected function except(...$actions)
    {
        $this->except = $this->filterAction($actions);
    }

    /**
	* Set only allowed actions
	*
	* @param  array $actions
	* @return void
	*/
    protected function only(...$actions)
    {
		$this->only = $this->filterAction($actions);
    }

    /**
	* Handle routes
	*
	* @return void 
	*/
	protected function handle()
	{
		$this->run();
	}

	/**
	* Get handled routes
	*
	* @method handle | toGroup
	*
	* @return void
	*/
	public function routes()
	{
		if ($this->group) {
			return $this->toGroup();
		}

		return $this->handle();
	}

	/**
	* Get validated routes
	*
	* @return void
	*/
	protected function run()
	{	
		//run intials 
		$this->init();

		//set middlewares
		$this->middlewares = method_exists($this, 'middlewares') ? 
		$this->middlewares() : [];

		//create new routes 
		$this->createRoutes();
	}

	/**
	* Create new routes
	*
	* @return void
	*/
	protected function createRoutes()
	{
		if (is_array($this->getAllMethods())) 
		{
			foreach ($this->getAllMethods() as $action => $methods) {
				$this->addRoutes($methods, $action);
			}
		}
	}

	/**
	* Add all routes
	*
	* @param  array|string  $method 
	* @param  string 		$action
	* @return void
	*/
	protected function addRoutes($method, $action)
	{
		$this->action = $action;
		$this->names = $this->names();
		$this->uri = $this->getUri(); 
		$this->controller = $this->getController();

		//make new route if controller and uri is already set
		if (!is_null($this->controller) && $this->uri) {
			$this->makeRoute($method);
		}
	}

	/**
	* Make new route
	*
	* @param  array|string $method
	* @return void
	*/
	protected function makeRoute($method)
	{
		if (!$this->validateAction()) {
			return;
		}
		
		if (is_array($method)) {
			$route = $this->toMatch($method);
		}

		if (!isset($route)) {
			$route = $this->toRoute($method);
		}

		$this->addRouteOptions($route);
	}

	/**
	* Validate Allowed actions
	*
	* @return bool
	*/
	protected function validateAction(): bool
	{
		if ($this->except) {
			return !in_array($this->action, $this->except);
		}

		if (is_array($this->getMethods())) {
			return in_array($this->action, $this->getMethods());
		}
	}

	/**
	* Filter actions
	*
	* @param  array $actions
	* @return array
	*/
	protected function filterAction($actions): array
	{
		if (is_array($actions[0])) {
			return array_unique($actions[0]);
		}

		return array_unique($actions);
	}

	/**
	* Add Route
	*
	* @param  string $method
	* @return Illuminate\Routing\Route
	*/
	protected function toRoute($method): Router
	{
		return Route::{$method}($this->uri, $this->getAction());
	}

	/**
	* Add all Methods on action to Match methods
	*
	* @param  array $method
	* @return Illuminate\Routing\Route
	*/
	protected function toMatch($methods): Router
	{
		return Route::match($methods, $this->uri, $this->getAction());
	}

	/**
	* Add all options to route
	*
	* @param Illuminate\Routing\Route $route
	* @return void
	*/
	protected function addRouteOptions($route)
	{
		if (isset($this->middlewares[$this->action])) {
			$route->middleware($this->middlewares[$this->action]);
		} 

		if(!isset($this->names[$this->action])){
			return;
		}

		if (isset($this->name) && $this->group) {
			$route->name($this->getSeparator() . $this->names[$this->action]);
		}

		if (!$this->group) {
			$route->name($this->names[$this->action]);
		}

		if (method_exists($this, 'collection')) {
			$this->collection($route);
		}
	}

	/**
	* Run route class as group
	*
	* @return void
	*/
	public function toGroup()
	{
		// /dd($this->addGroupRouteOptions());
		Route::group(
			$this->addGroupRouteOptions(),
			function () {
		    	$this->handle();
			}
		);
	}

	/**
	* Add domains to the group route
	*
	* @return void
	*/
	public function domains()
	{
		Route::domains(
			$this->getDomainOptions(),
			function() {
			    $this->handle();
			}
		);
	}

	/**
	* get all route options and merge with domain
	*
	* @return array
	*/
	public function getDomainOptions(): array
	{
		return array_merge($this->addGroupRouteOptions(), [
			'domain' => $this->domain,
		]);
	}

	/**
	* Add group options
	*
	* @return array
	*/
	protected function addGroupRouteOptions(): array
	{
		$options = [];

		if (isset($this->middleware)) {
			$options['middleware'] = $this->middleware;
		} 

		if(isset($this->name)){
			$options['as'] = $this->name;
		}

		if(isset($this->namespace)){
			$options['namespace'] = $this->namespace;
		}

		if(isset($this->prefix)){
			$options['prefix'] = $this->prefix;
		}

		return $options;
	}

	/**
	* Get Url to specific action
	*
	* @return string | throw
	*/
	protected function getUri(): string
	{	
		$uri = $this->getAllUri();

		if(in_array($this->action, array_keys($uri))){
			return $uri[$this->action];
		}

		throw new \Exception("Error: the '{$this->action}' action doesn't have URL, Please check the map & methods function.");
	}

	/**
	* get all uri
	*
	* @return array
	*/
	public function getAllUri(): array
	{
		if (method_exists($this, 'map')) {
		 	return array_merge($this->defaultUri(), $this->map());
		}

		return $this->defaultUri();
	}

	/**
	* defined default resource uri
	*
	* @return array
	*/
	public function defaultUri()
	{
		if ($this->resource) {
			return [
				'index' => '/',
				'create' => '/create',
				'store' => '/',
				'show' => $this->getParameter(),
				'update' => $this->getParameter(),
				'destroy' => $this->getParameter(),
				'edit' => "{$this->getParameter()}/edit",
			];
		}

		return [];
	}

	/**
	* set methods for validation
	*
	* @return array
	*/
	protected function setMethods(): array
	{
		$default = [];

		if (!empty($this->except) && !empty($this->only)) {
			throw new \Exception('Error: you can\'t declare except and only method at the same time.');
		}

		if (!empty($this->getAllMethods())) {
			$default = array_keys($this->getAllMethods());
		}

		$this->methods = $this->only ?? $this->except ?? $default;

		return $this->methods;
	}

	/**
	* set all methods
	*
	* @return array
	*/
	protected function getMethods(): array
	{
		return $this->methods;
	}

	public function getAllMethods()
	{
		if (method_exists($this, 'methods')) {
			return array_merge($this->defaultMethods(), $this->methods());
		}

		return $this->defaultMethods();
	}

	/**
	* set resource methods
	*
	* @return array
	*/
	protected function defaultMethods()
	{
		if ($this->resource) {
			return [
				'index' => 'GET',
				'create' => 'GET',
				'store' => 'POST',
				'show' => 'GET',
				'update' => ['PUT', 'PATCH'],
				'destroy' => 'DELETE',
				'edit' => 'GET',
			];
		}

		return [];
	}

	/**
	* get contoller with action
	*
	* @return array
	*/
	public function getAction(): array
	{
		if (method_exists($this, 'mapping') && !empty($this->mapping())) {
			return $this->getMapping();
		}

		return [$this->controller, $this->action];
	}

	public function getMapping()
	{
		foreach ($this->mapping() as $classes => $actions) 
		{
			foreach ($actions as $action) 
			{
				if ($this->action === $action) {
					return [$classes, $action];
				}
			}
		}
	}

	/**
	* add route names
	*
	* @return array
	*/
	public function names(): Array
	{
		return [
			$this->action => $this->getNames($this->action),
		];
	}

	/**
	* Get parameter
	*
	* @return string
	*/
	protected function getParameter(): String
	{
		return $this->parameter ? '{'. strtolower($this->parameter).'}' : '{id}';
	}

	/**
	* Get names only on resource mode
	*
	* @param  string $key
	* @return string|null
	*/
	protected function getNames($key)
	{
		if (!$this->resource) {
			return;
		}

		return $this->getName($key);
	}

	/**
	* Get name and add prefix to the name with seperator
	*
	* @param string   $key
	* @return string
	*/
	protected function getName($key): string
	{
		return $key;
	}

	/**
	* Get validated controller
	*
	* @return string
	*/
	protected function getController()
	{
		return $this->getDefaultController();
	}

	/**
	* Get default controller
	*
	* @return string
	*/
	protected function getDefaultController()
	{
		if (method_exists($this, 'defaultController')) {
			return $this->defaultController();
		}
	}

	/**
	* Get Separator
	*
	* @return string
	*/
	protected function getSeparator(): string
	{	
		return $this->settings['separator'];
	}

	/**
	* Define Settings
	*
	* @return array
	*/
	public function settings(): array
	{
		return [
			'separator' => $this->separator,
			//'resource' => $this->resource,
		];
	}
}