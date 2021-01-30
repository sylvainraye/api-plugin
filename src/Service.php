<?php declare(strict_types=1);

namespace Kiboko\Plugin\API;

use Jane\Component\OpenApi2\JaneOpenApi;
use Jane\Component\OpenApiCommon\Registry\Registry;
use Jane\Component\OpenApiCommon\Registry\Schema;
use Kiboko\Contract\Configurator;
use Kiboko\Plugin\API;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception as Symfony;
use Symfony\Component\Config\Definition\Processor;

final class Service implements Configurator\FactoryInterface
{
    private Processor $processor;
    private ConfigurationInterface $configuration;

    public function __construct()
    {
        $this->processor = new Processor();
        $this->configuration = new Configuration();
    }

    public function configuration(): ConfigurationInterface
    {
        return $this->configuration;
    }

    /**
     * @throws Configurator\ConfigurationExceptionInterface
     */
    public function normalize(array $config): array
    {
        try {
            return $this->processor->processConfiguration($this->configuration, $config);
        } catch (Symfony\InvalidTypeException|Symfony\InvalidConfigurationException $exception) {
            throw new Configurator\InvalidConfigurationException($exception->getMessage(), 0, $exception);
        }
    }

    public function validate(array $config): bool
    {
        try {
            $this->processor->processConfiguration($this->configuration, $config);

            return true;
        } catch (Symfony\InvalidTypeException|Symfony\InvalidConfigurationException $exception) {
            return false;
        }
    }

    /**
     * @throws Configurator\ConfigurationExceptionInterface
     */
    public function compile(array $config): Configurator\RepositoryInterface
    {
        foreach ($config as $definition) {
            $schema = new Schema(
                $config['openapi']['path'],
                $config['openapi']['namespace'],
                '/src/'
            );

            $registry = new Registry();
            $registry->setWhitelistedPaths([]);
            $registry->setThrowUnexpectedStatusCode(true);
            $registry->setCustomQueryResolver([]);

            $registry->addSchema($schema);
            $jane = JaneOpenApi::build([
                'reference' => true,
                'strict' => false,
                'skip-null-values' => false,
                'endpoint-generator' => null,
            ]);

            $jane->generate($registry);
        }

        $capacity = new API\Builder\OpenAPI($registry);

        $capacity->withNamespace($config['openapi']['namespace']);
        $capacity->withEndpoint($config['extractor']['endpoint']);

        return new API\Service\Repository\OpenAPI(
            $capacity,
            $registry,
        );
    }
}
