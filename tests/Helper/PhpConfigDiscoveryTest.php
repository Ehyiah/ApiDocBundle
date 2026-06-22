<?php

namespace Ehyiah\ApiDocBundle\Tests\Helper;

use Ehyiah\ApiDocBundle\EhyiahApiDocBundle;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests the PSR-4 namespace resolution logic used to auto-discover
 * PHP config classes in the source_path as DI services.
 *
 * @coversNothing
 */
final class PhpConfigDiscoveryTest extends TestCase
{
    private function callResolveNamespace(string $path): ?string
    {
        $bundle = new EhyiahApiDocBundle();
        $method = new ReflectionMethod(EhyiahApiDocBundle::class, 'resolveNamespace');
        $method->setAccessible(true);

        return $method->invoke($bundle, $path);
    }

    public function testResolveNamesaceForTestsAppSwagger(): void
    {
        $absolutePath = realpath(__DIR__ . '/../../tests/App/Swagger');
        $namespace = $this->callResolveNamespace($absolutePath);

        // PSR-4: Ehyiah\ApiDocBundle\Tests\ → tests/
        // tests/App/Swagger → Ehyiah\ApiDocBundle\Tests\App\Swagger
        $this->assertSame('Ehyiah\ApiDocBundle\Tests\App\Swagger', $namespace);
    }

    public function testResolveNamespaceForSrcRoot(): void
    {
        $absolutePath = realpath(__DIR__ . '/../../src');
        $namespace = $this->callResolveNamespace($absolutePath);

        // PSR-4: Ehyiah\ApiDocBundle\ → src/
        // src → Ehyiah\ApiDocBundle (empty relative path)
        $this->assertSame('Ehyiah\ApiDocBundle', $namespace);
    }

    public function testResolveNamespaceForNonExistentPath(): void
    {
        $namespace = $this->callResolveNamespace('/tmp/nonexistent_swagger_dir');

        // Not under any PSR-4 prefix → null
        $this->assertNull($namespace);
    }

    public function testResolveNamespaceForVendorPath(): void
    {
        $absolutePath = realpath(__DIR__ . '/../../vendor/symfony/framework-bundle');
        $namespace = $this->callResolveNamespace($absolutePath);

        // vendor paths are under PSR-4 prefixes like Symfony\Bundle\FrameworkBundle\ → vendor/symfony/framework-bundle/
        $this->assertNotNull($namespace);
        $this->assertStringStartsWith('Symfony\Bundle\FrameworkBundle', $namespace);
    }
}
