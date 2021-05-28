<?php 

namespace PhHitachi\Routes;

use Symfony\Component\ClassLoader\ClassMapGenerator;
use Illuminate\Support\Facades\Route;
use SimpleClassFinder\Finder;

Class Router 
{
	public function __construct()
	{
		//$this->finder = new ClassLoader;
	}

	/**
	* Load all routes via class
	*
	* @param $classes | array 
	* @return void
	*/
	public function load(...$classes)
	{
		foreach ($classes as $key => $routes) 
		{
			$this->getValidatorInstance($routes);
		}
	}

	public function all()
	{
		$routes = $this->createMap(app_path('Routes'));
		$this->load(array_keys($routes));
	}

	/**
     * Iterate over all files in the given directory searching for classes.
     *
     * @param \Iterator|string $dir The directory to search in or an iterator
     *
     * @return array A class map array
     */
    protected function createMap($dir)
    {
        if (\is_string($dir)) {
            $dir = new \RecursiveDirectoryIterator($dir);
        }

        $map = [];

        foreach ($dir as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $path = $file->getRealPath() ?: $file->getPathname();

            if ('php' !== pathinfo($path, \PATHINFO_EXTENSION)) {
                continue;
            }

            $classes = $this->findClasses($path);

            if (\PHP_VERSION_ID >= 70000) {
                // PHP 7 memory manager will not release after token_get_all(), see https://bugs.php.net/70098
                gc_mem_caches();
            }

            foreach ($classes as $class) {
                $map[$class] = $path;
            }
        }

        return $map;
    }

    /**
     * Extract the classes in the given file.
     *
     * @param string $path The file to check
     *
     * @return array The found classes
     */
    protected function findClasses($path)
    {
        $contents = file_get_contents($path);
        $tokens = token_get_all($contents);

        $nsTokens = [\T_STRING => true, \T_NS_SEPARATOR => true];
        if (\defined('T_NAME_QUALIFIED')) {
            $nsTokens[T_NAME_QUALIFIED] = true;
        }

        $classes = [];

        $namespace = '';
        for ($i = 0; isset($tokens[$i]); ++$i) {
            $token = $tokens[$i];

            if (!isset($token[1])) {
                continue;
            }

            $class = '';

            switch ($token[0]) {
                case \T_NAMESPACE:
                    $namespace = '';
                    // If there is a namespace, extract it
                    while (isset($tokens[++$i][1])) {
                        if (isset($nsTokens[$tokens[$i][0]])) {
                            $namespace .= $tokens[$i][1];
                        }
                    }
                    $namespace .= '\\';
                    break;
                case \T_CLASS:
                case \T_INTERFACE:
                case \T_TRAIT:
                    // Skip usage of ::class constant
                    $isClassConstant = false;
                    for ($j = $i - 1; $j > 0; --$j) {
                        if (!isset($tokens[$j][1])) {
                            break;
                        }

                        if (\T_DOUBLE_COLON === $tokens[$j][0]) {
                            $isClassConstant = true;
                            break;
                        } elseif (!\in_array($tokens[$j][0], [\T_WHITESPACE, \T_DOC_COMMENT, \T_COMMENT])) {
                            break;
                        }
                    }

                    if ($isClassConstant) {
                        break;
                    }

                    // Find the classname
                    while (isset($tokens[++$i][1])) {
                        $t = $tokens[$i];
                        if (\T_STRING === $t[0]) {
                            $class .= $t[1];
                        } elseif ('' !== $class && \T_WHITESPACE === $t[0]) {
                            break;
                        }
                    }

                    $classes[] = ltrim($namespace.$class, '\\');
                    break;
                default:
                    break;
            }
        }

        return $classes;
    }

	/**
	* Convert class name to new instance
	*
	* @param $class | string 
	* @return mixed
	*/
	protected function getInstance($class)
	{
		return tap(new $class, function ($instance) {
            return $instance;
        });
	}

	/**
	* Get all Instance
	*
	* @param $classes | array 
	* @return mixed
	*/
	protected function getInstances($classes): array
	{
		foreach ($classes as $class) {
			$instance[] = $this->getInstance($class);
		}

		return $instance;
	}

	/**
	* Get validated  
	*
	* @param $routes | string 
	* @return void
	*/
	protected function getValidatorInstance($routes)
	{
		$instances = $this->getInstances($routes);
		
		foreach ($instances as $instance) 
		{
			if (method_exists($instance, 'handle')) 
			{
				$this->handle($instance);
			}
		}
	}

	/**
	* handle instances
	*
	* @param $instance | mixed 
	* @return mixed
	*/
	protected function handle($instance)
	{	
		if ($instance->group) {
			$instance->toGroup();
		}

		if ($instance->domain) {
			$instance->domains();
		}

		if (method_exists($instance, 'fallback')) {
			$instance->fallback($instance);
		}
	}
}