<?php

namespace uCMS;

use Exception;
use ArrayAccess;


/**
 * Exception thrown when something goes wrong with the configuration.
 */
class ConfigException extends Exception {}


/**
 * A class wrapper representing read-only structured config.
 */
class Config implements ArrayAccess
{
    /**
     * Load a configuration from a Yaml file.
     * @param string $fileName Path to the file to be parsed.
     * @return Config
     */
    static public function loadYaml(string $fileName): Config
    {
        if (!function_exists('yaml_parse_file')) {
            throw new Exception("Function 'yaml_parse_file' not found. PHP requires Yaml extension for uCMS\Config::loadYaml to work.");
        }

        if (!file_exists($fileName) || !is_file($fileName) || !is_readable($fileName)) {
            throw new ConfigException("File '$fileName' is not a regular file or we do not have read permissions.");
        }

        $yaml = yaml_parse_file($fileName);
        if ($yaml === false) {
            throw new ConfigException("File '$fileName' cannot be parsed as yaml file correctly.");
        }
        
        return new Config($yaml);
    }


    /**
     * Internal holder for the raw parsed data.
     */
    private $data = null;

    /**
     * Config cannot be constructed ad-hoc, but only by static load functions.
     */
    private function __construct($data = [])
    {
        $this->data = $data;
    }


    /**
     * Safe way to get raw parsed value (typically scalar from the structure leaf).
     * @param string|null $key Structure item identifier. If missing, the whole structure in config is returned.
     * @param $default Default value returned in case the $key item is not present in the structure.
     * @return any Raw value from the parsed config or default if missing.
     */
    public function value(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->data;
        }

        if (!is_array($this->data) || !array_key_exists($key, $this->data)) {
            return $default;
        }

        return $this->data[$key];
    }


    /*
     * PHP Accessor Methods
     */
    
    public function __isset($name)
    {
        if (!is_array($this->data)) {
            throw new ConfigException("Trying to access '$name' key on a scalar value.");
        }
        return array_key_exists($name, $this->data);
    }

    public function __get($name)
    {
        if (!is_array($this->data)) {
            throw new ConfigException("Trying to access '$name' key on a scalar value.");
        }
        return new Config(array_key_exists($name, $this->data) ? $this->data[$name] : []);
    }

    public function __set($name, $value)
    {
        throw new ConfigException("Config is read-only.");
    }

    public function __unset($name)
    {
        throw new ConfigException("Config is read-only.");
    }


    public function offsetExists($offset)
    {
        if (!is_array($this->data)) {
            throw new ConfigException("Trying to access '$name' key on a scalar value.");
        }
        return array_key_exists($name, $this->data);
    }

    public function offsetGet($offset)
    {
        if (!is_array($this->data)) {
            throw new ConfigException("Trying to access '$name' key on a scalar value.");
        }
        return new Config(array_key_exists($name, $this->data) ? $this->data[$name] : []);
    }

    public function offsetSet($offset, $value)
    {
        throw new ConfigException("Config is read-only.");
    }

    public function offsetUnset($offset)
    {
        throw new ConfigException("Config is read-only.");
    }
}
