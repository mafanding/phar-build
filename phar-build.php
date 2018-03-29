<?php
PharBuild::create()->make();

class PharBuild
{

    const CONFIG_FILE = "build.json";

    const PHAR_EXTENSION = ".phar";

    protected $config = null;

    protected static $instance = null;

    protected function __construct()
    {
        $this->loadConfig();
    }

    protected function __clone(){}

    public static function create()
    {
        if (is_null(self::$instance)) {
            self::$instance = new static;
        }
        return self::$instance;
    }

    public function make()
    {
        try {
            $phar = new Phar($this->getPackageName(), 0, $this->getPackageName());
            $excludes = $this->getExcludes();
            $phar->startBuffering();
            foreach ($this->getIncludes() as $file) {
                if (is_dir($file)) {
                    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($file));
                    foreach ($iterator as $v) {
                        if ($v->getBaseName() !== "." && $v->getBaseName() !== "..") {
                            $phar->addFromString($v->getPathName(), file_get_contents($v->getPathName()));
                        }
                    }
                } else {
                    $phar->addFromString($file, file_get_contents($file));
                }
            }
            $stub = str_replace([
                "%package_name%",
                "%entry_file%",
            ], [
                $this->getPackageName(),
                $this->getEntryFile(),
            ],$this->getStub());
            $phar->setStub($stub);
            $phar->stopBuffering();
        } catch (Exception $e) {
            printf("%s\n", $this->composeExceptionString($e));
        }
    }

    protected function loadConfig()
    {
        if (!file_exists(self::CONFIG_FILE)) {
            throw new Exception("Config file doesn't exists");
        }
        if (!is_readable(self::CONFIG_FILE)) {
            throw new Exception("Config file couldn't readable");
        }
        $this->config = json_decode(file_get_contents(self::CONFIG_FILE));
        if (is_null($this->config) && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Parsing json error[" . strtolower(json_last_error_msg()) . "]");
        }
    }

    protected function composeExceptionString(Exception $e)
    {
        $exceptionString = "## %file%(%line%): %message%\n%trace%";
        $exceptionString = str_replace([
            "%file%",
            "%line%",
            "%message%",
            "%trace%",
        ], [
            $e->getFile(),
            $e->getLine(),
            $e->getMessage(),
            $e->getTraceAsString(),
        ], $exceptionString);
        return $exceptionString;
    }

    protected function getPackageName()
    {
        $dirName = basename(getcwd());
        if (is_null($this->config)) {
            return dirName . self::PHAR_EXTENSION;
        }
        return ($this->config->package_name ?? $dirName) . self::PHAR_EXTENSION;
    }

    protected function getIncludes()
    {
        $dirName = getcwd();
        if (is_null($this->config)) {
            return [$dirName];
        }
        return $this->config->includes ?? [$dirName];
    }

    protected function getExcludes()
    {
        if (is_null($this->config)) {
            return [];
        }
        return $this->config->excludes ?? [];
    }

    protected function getEntryFile()
    {
        if (is_null($this->config)) {
            return "main.php";
        }
        return $this->config->entry_file ?? "main.php";
    }

    protected function getStub()
    {
        return <<<EOF
#! /usr/bin/env php
<?php
Phar::mapPhar("%package_name%");
require "phar://%package_name%/%entry_file%";

__HALT_COMPILER();
EOF;
    }

}
