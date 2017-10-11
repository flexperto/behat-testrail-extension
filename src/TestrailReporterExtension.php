<?php

namespace flexperto\BehatTestrailReporter;


use Behat\Testwork\ServiceContainer\Extension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class TestrailReporterExtension implements Extension
{

    /**
     * @inheritdoc
     */
    public function process(ContainerBuilder $container) {
    }

    /**
     * Returns the extension config key.
     *
     * @return string
     */
    public function getConfigKey() {
        return "testrail_reporter";
    }

    /**
     * @inheritdoc
     */
    public function initialize(ExtensionManager $extensionManager) {
    }

    /**
     * @inheritdoc
     */
    public function configure(ArrayNodeDefinition $builder) {
        $builder->children()->scalarNode("baseUrl")->defaultValue("https://flexperto.testrail.net/index.php?/api/v2");
        $builder->children()->scalarNode("username")->defaultValue("dmytro.grablov@flexperto.com");
        $builder->children()->scalarNode("apiKey")->defaultValue("f8Urqd4H1qRBYl.pc4Yw-EvfSjDpQ1ktzsDrituTR");
        $builder->children()->scalarNode("runId")->defaultValue("16");
        $builder->children()->scalarNode("testidPrefix")->defaultValue("test_rail_");
        $builder->children()->scalarNode("loop_break")->defaultValue("false");
        $builder->children()->arrayNode('customFields')->defaultValue(['custom_environment' => '1']);
    }

    /**
     * @inheritdoc
     */
    public function load(ContainerBuilder $container, array $config) {
        $definition = new Definition("testrail\\TestrailReporter");
        $definition->addArgument($config['baseUrl']);
        $definition->addArgument($config['username']);
        $definition->addArgument($config['apiKey']);
        $definition->addArgument($config['runId']);
        $definition->addArgument($config['testidPrefix']);
        $definition->addArgument($config['customFields']);

        $container->setDefinition("testrail.reporter", $definition);
    }

}