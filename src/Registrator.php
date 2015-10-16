<?php

namespace Lulco\NettePhinx;

use Nette\Neon\Neon;
use Nette\Utils\Finder;
use Phinx\Config\Config;
use Symfony\Component\Console\Application;

class Registrator
{
    /** @var string absolute path to Nette config.*.neon files */
    private $configsDir;

    /** @var string absolute path to migrations */
    private $migrationsDir;
    
    /** @var string database table name for migrations */
    private $migrationTable;
    
    /** @var string default environment for migrations */
    private $defaultEnvironment;
    
    /** @var array list of phinx commands with aliases */
    private $commands = [
        '\Phinx\Console\Command\Create' => 'create',
        '\Phinx\Console\Command\Migrate' => 'migrate',
        '\Phinx\Console\Command\Rollback' => 'rollback',
        '\Phinx\Console\Command\Status' => 'status',
    ];

    /**
     * @param Application $application
     * @param string $configsDir    absolute path to Nette config.*.neon files
     * @param string $migrationsDir     absolute path to migrations
     * @param string $migrationTable    database table name for migrations
     * @param string $defaultEnvironment    default environment for migrations
     */
    public function __construct(
        Application $application,
        $configsDir,
        $migrationsDir,
        $migrationTable = 'phinxlog',
        $defaultEnvironment = 'local'
    ) {
        $this->configsDir = $configsDir;
        $this->migrationsDir = $migrationsDir;
        $this->migrationTable = $migrationTable;
        $this->defaultEnvironment = $defaultEnvironment;
        
        $this->init($application);
        
    }
    
    private function init(Application $application)
    {
        $config = new Config($this->buildConfig());

        // Register all commands
        foreach ($this->commands as $class => $commandName) {
            $command = new $class;
            $command->setName($commandName);
            $command->setConfig($config);
            $application->add($command);
        }
    }

    /**
     * Build phinx config from config.*.neon files
     * @return array
     */
    private function buildConfig()
    {
        $defaultConfigData = [
            'paths' => [
                'migrations' => $this->migrationsDir,
            ],
            'environments' => [
                'default_migration_table' => $this->migrationTable,
                'default_database' => $this->defaultEnvironment,
            ],
        ];

        $configData = $this->parseFiles($defaultConfigData);
        return $configData;
    }
    
    private function parseFiles($configData)
    {
        foreach (Finder::findFiles('config.*.neon')->in($this->configsDir) as $configFile) {
            $neon = Neon::decode(file_get_contents($configFile->getRealPath()));

            if (!$neon) {
                continue;
            }
            $environment = substr($configFile->getBaseName(), 7, -5);
            if (!isset($neon['parameters']['database']['default'])) {
                continue;
            }
            $dbData = $neon['parameters']['database']['default'];
            $configData['environments'][$environment] = [
                'adapter' => $dbData['adapter'],
                'host' => $dbData['host'],
                'name' => $dbData['dbname'],
                'user' => $dbData['user'],
                'pass' => $dbData['password'],
                'charset' => isset($dbData['charset']) ? $dbData['charset'] : 'utf8',
            ];
            if (isset($dbData['port'])) {
                $configData['environments'][$environment]['port'] = $dbData['port'];
            }
        }
        return $configData;
    }
}
