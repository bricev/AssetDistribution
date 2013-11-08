<?php

/*
 * This file is part of AssetDistribution.
 *
 * (c) Brice Vercoustre <brcvrcstr@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Libcast\AssetDistribution\Provider;

use Libcast\AssetDistribution\Provider\Credentials\CredentialsInterface;
use Libcast\AssetDistribution\Provider\Credentials\Credentials;
use Libcast\AssetDistribution\Provider\Manager\ManagerInterface;
use Libcast\AssetDistribution\Provider\Manager\Manager;

abstract class AbstractProvider implements \Serializable
{
    /**
     *
     * @var string Identifier
     */
    protected $id;

    /**
     *
     * @var string Provider name
     */
    protected $name;

    /**
     *
     * @var array List of credentials parameters
     */
    protected $parameters = array();

    /**
     *
     * @var array List of credentials settings
     */
    protected $settings = array();

    /**
     *
     * @var array Fields map
     */
    protected $fields_map = array();

    /**
     *
     * @var \Libcast\AssetDistribution\Provider\Credentials\CredentialsInterface
     */
    protected $credentials;

    /**
     *
     * @var \Libcast\AssetDistribution\Provider\Manager\ManagerInterface
     */
    protected $manager;

    /**
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Load a provider
     *
     * If the provider is authorized to be managed, then its object can be
     * transformed into a string (serialized) so it may be persisted for later use.
     *
     * Providers can be added to a ProviderCollection object for mass export.
     *
     * Parameters are serialized when providers are transformed into stings or when
     * added to a collection. Settings are not.
     *
     * @param mixed           $settings   List of settings or path to `.ini` config file
     * @param mixed           $parameters List of parameters or path to `.ini` config file
     * @param LoggerInterface $logger     Psr logger
     */
    public function __construct($settings = null, $parameters = null, \Psr\Log\LoggerInterface $logger = null)
    {
        $this->configure();

        // add settings
        if (is_array($settings)) {
            $this->setSettings($settings);
        } elseif (is_string($settings)) {
            $this->loadConfiguration($settings, 'settings');
        }

        // add parameters
        if (is_array($parameters)) {
            $this->setParameters($parameters);
        } elseif (is_string($parameters)) {
            $this->loadConfiguration($parameters, 'parameters');
        }

        if ($logger) {
            $this->setLogger($logger);
        }
    }

    /**
     *
     * @param string $id Identifier
     * @return \Libcast\AssetDistribution\Provider\ProviderInterface
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string Identifier
     */
    public function getId()
    {
        if (!$this->id) {
            // generate a uniq identifier
            $this->setId(uniqid());
        }

        return $this->id;
    }

    /**
     *
     * @param string $name
     * @return \Libcast\AssetDistribution\Provider\ProviderInterface
     */
    protected function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     *
     * @return string Name of the provider
     */
    public function getName()
    {
        if (!$this->name) {
            throw new \Exception('Provider must be named.');
        }

        return $this->name;
    }

    /**
     *
     * @param string $name
     * @param string $value
     * @return \Libcast\AssetDistribution\Provider\ProviderInterface
     */
    public function setSetting($name, $value)
    {
        $this->settings[$name] = $value;

        return $this;
    }

    /**
     *
     * @param array $settings
     * @return \Libcast\AssetDistribution\Provider\ProviderInterface
     */
    public function setSettings($settings)
    {
        $this->settings = array_merge($this->settings, $settings);

        return $this;
    }

    /**
     *
     * @param string $name
     * @return string The value of setting named $name
     */
    public function getSetting($name)
    {
        if (!isset($this->settings[$name]))
        {
            throw new \Exception("Setting '$name' does not exists.");
        }

        return $this->settings[$name];
    }

    /**
     *
     * @return array List of settings
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     *
     * @return bool True if param $name exists
     */
    public function hasSetting($name)
    {
        return isset($this->settings[$name]);
    }

    /**
     *
     * @param string $name
     * @param string $value
     * @return \Libcast\AssetDistribution\Provider\ProviderInterface
     */
    public function setParameter($name, $value)
    {
        $this->parameters[$name] = $value;

        return $this;
    }

    /**
     *
     * @param array $parameters
     * @return \Libcast\AssetDistribution\Provider\ProviderInterface
     */
    public function setParameters($parameters)
    {
        $this->parameters = array_merge($this->parameters, $parameters);

        return $this;
    }

    /**
     *
     * @param string $name
     * @return string The value of parameter named $name
     */
    public function getParameter($name)
    {
        if (!isset($this->parameters[$name]))
        {
            throw new \Exception("Parameter '$name' does not exists.");
        }

        return $this->parameters[$name];
    }

    /**
     *
     * @return array List of parameters
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     *
     * @return bool True if param $name exists
     */
    public function hasParameter($name)
    {
        return isset($this->parameters[$name]);
    }

    /**
     * Set parameters or settings from a configuration file (.ini)
     *
     * @param string $file Configuration file
     * @param string $type parameters|settings
     * @return \Libcast\AssetDistribution\Provider\ProviderInterface
     * @throws \Exception
     */
    public function loadConfiguration($file, $type = 'parameters')
    {
        if (!file_exists($file) || !is_readable($file)) {
            throw new \Exception("File '$file' is not readable.");
        }

        if (!$config = parse_ini_file($file, true)) {
            throw new \Exception("Impossible to read configuration file.");
        }

        if (!isset($config[$name = $this->getName()]) || empty($config[$name])) {
            throw new \Exception("There is no configuration for provider '$name'.");
        }

        $this->log('Loading config file', array($file, $type, $name, $config[$name]));

        switch ($type) {
            case 'parameters':
                $this->setParameters($config[$name]);
                break;

            case 'settings':
                $this->setSettings($config[$name]);
                break;

            default:
                throw new \Exception("Congif type '$type' does not exists.");
        }

        return $this;
    }

    /**
     * Add common/provider fields association map
     *
     * @param  array $map Array with common => provider fields association
     * @return \Libcast\AssetDistribution\Provider\ProviderInterface
     */
    protected function setFieldNamesMap(array $map)
    {
        $this->fields_map = array_merge($this->fields_map, $map);

        return $this;
    }

    /**
     * Get a common field name based on it's provider name
     *
     * @param  string $name Field's provider name
     * @return string       Common field name if exists, or $name value otherwise
     */
    public function getCommonFieldName($name)
    {
        $key = array_search($name, $this->fields_map);

        return $key ? $key : $name;
    }

    /**
     * Get a provider field name based on it's common name
     *
     * @param  string $name Field's common name
     * @return string       Provider field name if exists, or $name value otherwise
     */
    public function getProviderFieldName($name)
    {
        return isset($this->fields_map[$name]) ? $this->fields_map[$name] : $name;
    }

    /**
     * Associate credentials with the provider
     *
     * @param CredentialsInterface $credentials Credentials
     * @return \Libcast\AssetDistribution\Provider\ProviderInterface
     */
    public function setCredentials(CredentialsInterface $credentials)
    {
        $this->credentials = $credentials;

        return $this;
    }

    /**
     * Get the provider credentials
     *
     * @return \Libcast\AssetDistribution\Provider\Credentials\CredentialsInterface
     */
    public function getCredentials()
    {
        if (!$this->credentials) {
            $this->setCredentials($credentials = Credentials::load($this));

            $this->log('Affect new credentials');
        }

        return $this->credentials;
    }

    /**
     * Set a manager for the provider
     *
     * @param ManagerInterface $manager Manager
     * @return \Libcast\AssetDistribution\Provider\ProviderInterface
     */
    public function setManager(ManagerInterface $manager)
    {
        $this->manager = $manager;

        return $this;
    }

    /**
     * Get the provider manager
     *
     * @return \Libcast\AssetDistribution\Provider\Credentials\CredentialsInterface
     */
    public function getManager()
    {
        if (!$this->manager) {
            $this->setManager($manager = Manager::load($this));

            $this->log('Affect new manager', $manager);
        }

        return $this->manager;
    }

    /**
     *
     * @param LoggerInterface $logger
     * @return \Libcast\AssetDistribution\Provider\ProviderInterface
     */
    public function setLogger(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Credentials proxy method
     */
    public function authenticate()
    {
        return $this->getCredentials()->authenticate();
    }

    /**
     * Credentials proxy method
     */
    public function revoke()
    {
        return $this->getCredentials()->revoke();
    }

    /**
     * \Serializable::serialize
     */
    public function serialize()
    {
        return serialize(array(
            'id'         => $this->getId(),
            'name'       => $this->getName(),
            'parameters' => $this->getParameters(),
        ));
    }

    /**
     * \Serializable::unserialize
     *
     * @param string $data Serialized data
     */
    public function unserialize($data)
    {
        $unserialized = unserialize($data);

        $this->setId($unserialized['id']);
        $this->setName($unserialized['name']);
        $this->setParameters($unserialized['parameters']);
        $this->configure();
    }

    /**
     * Logger proxy method.
     *
     * @param string $message
     * @param mixed  $context
     * @param mixed  $level
     */
    public function log($message, $context = array(), $level = 'debug')
    {
        if (!is_array($context)) {
            $context = (array) $context;
        }

        if ($logger = $this->logger) {
            $logger->$level($message, $context);
        }
    }

    public function __toString() {
        return $this->isAuthorized() ? $this->serialize() : $this->getName();
    }
}
