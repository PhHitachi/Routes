<?php

namespace PhHitachi\Routes\Commands;

//use Illuminate\Support\Str;
use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

class ArtisanFacades extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'route:facade';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new facade for route class';

    protected $namespace;
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Facades';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if (parent::handle() === false && ! $this->option('force')) {
            return;
        }

        if ($this->option('aliases')) {
            $this->registerToAliases();
        }

        $this->callSilent('config:cache');
    }

    /**
     * Write the Markdown template for the mailable.
     *
     * @return void
     */
    protected function registerToAliases()
    {
        $this->addToAliases();
        $this->registertoProvider();
    }

    public function getAppConfig()
    {
        return file_get_contents($this->getAppConfigPath());
    }

    public function getProvider()
    {
        return file_get_contents($this->getProviderPath());
    }

    public function getAppConfigPath()
    {
        return config_path('app.php');
    }

    public function getProviderPath()
    {
        return app_path('Providers\RouteServiceProvider.php');
    }

    public function writeFile($path, $content)
    {
        $this->files->put($path, $content);
    }

    public function addToAliases()
    {
        $content = $this->getAppConfig();
        $matches = $this->getMatches('/\'aliases\' => \[/', $content);
        $facade = $this->facade();
        $strlen = $this->length($matches);
        $new_content = $this->repaceNewContent($content, $facade, $strlen);
        
        if (!isset(config('app.aliases')[$this->option('aliases')])) {
            $this->writeFile($this->getAppConfigPath(), $new_content);
        }

    }

    public function registertoProvider()
    {
        $content = $this->getProvider();
        $matches = $this->getMatches('/function register()\(.*\)\s*{/', $content);
        
        if($this->bindExist()){
            return;
        }

        if (count($matches) > 0) {
            $strlen = $this->length($matches);
        }

        if (!isset($strlen)) {
            $matches = $this->getMatches('/public function boot/', $content);
            $str_replace = $this->createRegister();
            $strlen = (($this->length($matches))-25);
        }

        $bind = $str_replace ?? $this->getBind();
        $new_content = $this->repaceNewContent($content, $bind, $strlen);

        $this->writeFile($this->getProviderPath(), $new_content);
    }

    public function createRegister()
    {       
        $function  = PHP_EOL."    public function register()".PHP_EOL."    {";
        $function .= $this->getBind();
        $function .= PHP_EOL."    }\n";
        return $function;
    }

    public function getBind(){
        return PHP_EOL."\t\t\$this->app->bind('{$this->getIdentifier()}', function(){
            return new \\{$this->getRouteClass()}();
        });";
    }

    public function bindHeader()
    {
        return "bind\('{$this->getIdentifier()}', function";
    }

    public function bindExist()
    {
        $matches = $this->getMatches('/'.$this->bindHeader().'/', $this->getProvider());
        return (count($matches) > 0);
    }

    public function getIdentifier()
    {
        if ($this->option('aliases')) {
            return $this->option('aliases');
        }

        return $this->getClassName();
    }

    public function facade()
    {
        $tab = PHP_EOL."        ";
        return "$tab'{$this->option('aliases')}' => {$this->getFacadeClass()}::class,";
    }

    public function getClassName()
    {
        return $this->argument('name');
    }

    public function getRouteClass()
    {
        if ($this->option('route')) {
            return str_replace('\Facades', '', $this->getFacadeClass($this->option('route')));
        }

        return str_replace('\Facades', '', $this->getFacadeClass());
    }

    public function getFacadeClass($name = null)
    {
        if (isset($name)) {
            "{$this->namespace}\\$name";
        }

        return "{$this->namespace}\\{$this->getClassName()}";
    }

    public function getMatches($regxp, $content)
    {
        preg_match($regxp, $content, $matches, PREG_OFFSET_CAPTURE);

        return $matches;
    }

    public function length($matches)
    {
        return strlen($matches[0][0]) + $matches[0][1];
    }

    public function repaceNewContent($conent, $stringToReplace, $strlen)
    {
        return substr_replace($conent, $stringToReplace, $strlen, 0);
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        $class = parent::buildClass($name);
        $class = str_replace('DummyName', $this->getIdentifier(), $class);

        return $class;
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__.'/stubs/routes.facade.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $this->getValadeatedNamespace($rootNamespace);
    }

    public function getValadeatedNamespace($rootNamespace)
    {
        return $this->namespace = "{$rootNamespace}\\Routes\\$this->type";
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the mailable already exists'],

            ['route', 'r', InputOption::VALUE_OPTIONAL, 'defined as class register routes class on service provider'],

            ['aliases', 'a', InputOption::VALUE_OPTIONAL, 'Create a new facade for route class'],
        ];
    }
}
