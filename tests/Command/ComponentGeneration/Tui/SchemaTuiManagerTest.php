<?php

namespace Ehyiah\ApiDocBundle\Tests\Command\ComponentGeneration\Tui;

use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Schema\SchemaTuiManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;

/**
 * @coversNothing
 */
class SchemaTuiManagerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apidoc_schema_mgr_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testGetDefaultDumpLocationReturnsPath(): void
    {
        $manager = $this->createManager();

        $this->assertSame('/Swagger/', $manager->getDefaultDumpLocation());
    }

    public function testGetAllClassesFindsClassesInScanDirectories(): void
    {
        $fqcn = $this->createSchemaFixtureClass('GenUser');

        $manager = $this->createManager(['src']);
        $classes = $manager->getAllClasses();

        $this->assertContains($fqcn, $classes);
    }

    public function testGetAllClassesIgnoresFilesWithoutLoadableClass(): void
    {
        $dir = $this->tmpDir . '/src/Empty';
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/NotAClass.php', "<?php\n// no class declared here\n");

        $fqcn = $this->createSchemaFixtureClass('OnlyUser');

        $manager = $this->createManager(['src']);
        $classes = $manager->getAllClasses();

        $this->assertSame([$fqcn], $classes);
    }

    public function testGetAllClassesReturnsEmptyWhenNoDirectoryExists(): void
    {
        $manager = $this->createManager(['does/not/exist']);

        $this->assertSame([], $manager->getAllClasses());
    }

    public function testGetClassPropertiesCombinesPropertyInfoAndPublicProperties(): void
    {
        $className = 'PropsUser' . uniqid();
        $dir = $this->tmpDir . '/src/Fixture';
        mkdir($dir, 0755, true);
        $code = "<?php\nnamespace SchemaFixtures;\nclass {$className}\n{\n    public string \$name = '';\n    public ?string \$nickname = null;\n    private int \$age = 0;\n    protected bool \$active = false;\n}\n";
        file_put_contents($dir . '/' . $className . '.php', $code);
        require_once $dir . '/' . $className . '.php';
        $fqcn = 'SchemaFixtures\\' . $className;

        $propertyInfo = $this->createMock(PropertyInfoExtractorInterface::class);
        $propertyInfo->method('getProperties')->willReturn(['email']);

        $manager = new SchemaTuiManager(
            $this->createKernel(),
            $this->createParameterBag([]),
            $propertyInfo,
        );

        $properties = $manager->getClassProperties($fqcn);

        $this->assertSame(['email', 'name', 'nickname'], $properties);
    }

    public function testGetAvailableSchemasOnlyReadsSchemasDirectory(): void
    {
        $schemasDir = $this->tmpDir . '/Swagger/schemas';
        mkdir($schemasDir, 0755, true);
        touch($schemasDir . '/User.yaml');
        file_put_contents($schemasDir . '/Pet.php', '<?php');
        mkdir(dirname($schemasDir) . '/headers', 0755, true);
        touch(dirname($schemasDir) . '/headers/X-Auth.yaml');

        $manager = $this->createManager([], ['src/Entity']);

        $schemas = $manager->getAvailableSchemas();

        $this->assertEqualsCanonicalizing(['User', 'Pet'], $schemas);
    }

    /**
     * @param array<int, string> $scanDirectories
     */
    private function createManager(array $scanDirectories = []): SchemaTuiManager
    {
        return new SchemaTuiManager(
            $this->createKernel(),
            $this->createParameterBag($scanDirectories),
            $this->createMock(PropertyInfoExtractorInterface::class),
        );
    }

    private function createKernel(): KernelInterface
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($this->tmpDir);

        return $kernel;
    }

    /**
     * @param array<int, string> $scanDirectories
     */
    private function createParameterBag(array $scanDirectories): ParameterBagInterface
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnMap([
            ['ehyiah_api_doc.source_path', '/Swagger'],
            ['ehyiah_api_doc.dump_path', '/Swagger/dump'],
            ['ehyiah_api_doc.scan_directories', $scanDirectories],
        ]);

        return $parameterBag;
    }

    private function createSchemaFixtureClass(string $className): string
    {
        $dir = $this->tmpDir . '/src/Fixture';
        mkdir($dir, 0755, true);
        $namespace = 'SchemaFixtures_' . uniqid();
        $code = "<?php\nnamespace {$namespace};\nclass {$className}\n{\n    public string \$name = '';\n}\n";
        file_put_contents($dir . '/' . $className . '.php', $code);
        require_once $dir . '/' . $className . '.php';

        return $namespace . '\\' . $className;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
