<?php


namespace Hakam\MultiTenancyBundle\DependencyInjection;


use Hakam\MultiTenancyBundle\Doctrine\DBAL\TenantConnection;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
class HakamMultiTenancyExtension extends Extension implements  PrependExtensionInterface
{
    /**
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        $configuration = $this->getConfiguration($configs, $container);

        $configs = $this->processConfiguration($configuration, $configs);

        $definition = $container->getDefinition('hakam_db_config.service');
        $definition->setArgument(1, $configs['tenant_database_className']);
        $definition->setArgument(2, $configs['tenant_database_identifier']);

        $this->prepend($container);
    }

    public function prepend(ContainerBuilder $container)
    {
        $configs = $container->getExtensionConfig($this->getAlias());
        $dbSwitcherConfig = $this->processConfiguration(new Configuration(), $configs);

        if (count($dbSwitcherConfig)  === 5) {
            $bundles = $container->getParameter('kernel.bundles');
            $tenantConnectionConfig = [
                'connections' => [
                    'tenant' => [
                        'driver' => $dbSwitcherConfig['tenant_connection']['driver'],
                        'host' => $dbSwitcherConfig['tenant_connection']['host'],
                        'charset' => $dbSwitcherConfig['tenant_connection']['charset'],
                        'dbname' => $dbSwitcherConfig['tenant_connection']['dbname'],
                        'server_version' => $dbSwitcherConfig['tenant_connection']['server_version'],
                        'user' => $dbSwitcherConfig['tenant_connection']['user'],
                        'password' => $dbSwitcherConfig['tenant_connection']['password'],
                        'wrapper_class' => TenantConnection::class
                    ]
                ]
            ];

            $mappings = [];
            foreach ($dbSwitcherConfig['tenant_entity_manager']['mappings'] as $key => $mapping)
            {
                $mappings[sprintf('HakamMultiTenancyBundle%s', $key)] = [
                    'type' => $mapping['type'],
                    'dir' => $mapping['dir'],
                    'prefix' => $mapping['prefix']?? null,
                    'alias' => $mapping['alias']?? null,
                    'is_bundle' => $mapping['is_bundle']?? true,
                ];
            }
            $tenantEntityManagerConfig = [
                'entity_managers' => [
                    'tenant' => [
                        'connection' => 'tenant',
                        'mappings' => $mappings
                    ]
                ]
            ];

            $tenantDoctrineMigrationPath =
                [
                    $dbSwitcherConfig['tenant_migration']['tenant_migration_namespace'] => $dbSwitcherConfig['tenant_migration']['tenant_migration_path']
                ];

            if (!isset($bundles['doctrine'])) {
                $container->prependExtensionConfig('doctrine', ['dbal' => $tenantConnectionConfig, 'orm' => $tenantEntityManagerConfig]);
            } else {
                throw new InvalidConfigurationException('You need to enable Doctrine Bundle to be able to use db switch bundle');
            }

            if (!isset($bundles['doctrine_migrations'])) {
                //    $container->prependExtensionConfig('doctrine_migrations', ['migrations_paths' => $tenantDoctrineMigrationPath]);
                $container->setParameter('tenant_doctrine_migration', ['migrations_paths' => $tenantDoctrineMigrationPath]);
            } else {
                throw new InvalidConfigurationException('You need to enable Doctrine Migration Bundle to be able to use MultiTenancy Bundle');
            }
        }
    }
}