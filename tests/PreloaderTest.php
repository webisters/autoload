<?php
/*
 * This file is part of Webisters Autoload Library.
 *
 * (c) Hafiz Muhammad Moaz <thewebisters@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Tests\Autoload;

use Framework\Autoload\Autoloader;
use Framework\Autoload\Preloader;
use PHPUnit\Framework\TestCase;
use Tests\Autoload\Preload\PlainClass;

final class PreloaderTest extends TestCase
{
    public function testEmptyListFiles() : void
    {
        $preloader = new Preloader(packagesDir: __DIR__);
        self::assertEmpty($preloader->getAutoloader()->getClasses());
        self::assertEmpty($preloader->listPackagesFiles());
        self::assertEmpty($preloader->listFiles());
        self::assertEmpty($preloader->getAutoloader()->getClasses());
    }

    public function testListFiles() : void
    {
        $autoloader = new Autoloader();
        $autoloader->setNamespace(
            'Foo\Bar\Baz',
            __DIR__ . '/support'
        )->setClass(\OneInterface::class, __DIR__ . '/support/OneInterface.php');
        $preloader = new Preloader($autoloader, __DIR__ . '/../vendor/webisters');
        $packageFiles = $preloader->listPackagesFiles();
        $files = $preloader->listFiles();
        self::assertContains(
            \realpath(__DIR__ . '/../vendor/webisters/debug/src/Debugger.php'),
            $packageFiles
        );
        self::assertContains(
            \realpath(__DIR__ . '/../vendor/webisters/debug/src/Debugger.php'),
            $files
        );
        self::assertNotContains(
            \realpath(__DIR__ . '/../vendor/webisters/coding-standard/src/Config.php'),
            $files
        );
        self::assertNotContains(
            __DIR__ . '/support/OneClass.php',
            $files
        );
        self::assertContains(
            __DIR__ . '/support/OneEnum.php',
            $files
        );
        self::assertContains(
            __DIR__ . '/support/OneInterface.php',
            $files
        );
        $classes = $preloader->getAutoloader()->getClasses();
        self::assertArrayHasKey(
            \Framework\Debug\Debugger::class,
            $classes
        );
        self::assertArrayNotHasKey(
            \Framework\CodingStandard\Config::class,
            $classes
        );
        self::assertArrayHasKey(
            \Foo\Bar\Baz\OneEnum::class,
            $classes
        );
        self::assertArrayHasKey(
            \Foo\Bar\Baz\OneTrait::class,
            $classes
        );
        self::assertArrayNotHasKey(
            \OneClass::class,
            $classes
        );
        self::assertArrayHasKey(
            \OneInterface::class,
            $classes
        );
        self::assertContains(
            $classes[\Framework\Debug\Debugger::class],
            $packageFiles
        );
        self::assertContains(
            $classes[\Framework\Debug\Debugger::class],
            $files
        );
    }

    public function testPackagesDir() : void
    {
        $dir = __DIR__ . '/../vendor/webisters';
        $preloader = new Preloader(packagesDir: null);
        $preloader->setPackagesDir($dir);
        self::assertSame(
            \realpath($dir) . \DIRECTORY_SEPARATOR,
            $preloader->getPackagesDir()
        );
        self::assertEmpty($preloader->listFiles());
        self::assertNotEmpty($preloader->withPackages()->listFiles());
        $classes = $preloader->getAutoloader()->getClasses();
        self::assertContains(
            $classes[\Framework\Debug\Debugger::class],
            $preloader->listFiles()
        );
        self::assertArrayNotHasKey(
            \Framework\CodingStandard\Config::class,
            $classes
        );
        $preloader->withDevPackages()->listFiles();
        $classes = $preloader->getAutoloader()->getClasses();
        self::assertArrayHasKey(
            \Framework\CodingStandard\Config::class,
            $classes
        );
        self::assertContains(
            $classes[\Framework\CodingStandard\Config::class],
            $preloader->withDevPackages()->listFiles()
        );
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid packages dir: /foo/bar');
        $preloader->setPackagesDir('/foo/bar');
    }

    public function testLoad() : void
    {
        $preloader = new Preloader(packagesDir: __DIR__ . '/support');
        self::assertEmpty($preloader->load());
        $preloader->getAutoloader()->setClass(\OneClass::class, __DIR__ . '/support/OneClass.php');
        self::assertContains(
            __DIR__ . '/support/OneClass.php',
            $preloader->load()
        );
    }

    /**
     * @param string $name
     *
     * @return array{0:Preloader,1:string} The preloader and the fixture path
     */
    private function preloadFixture(string $name) : array
    {
        $file = __DIR__ . '/Preload/' . $name . '.php';
        $autoloader = new Autoloader();
        $autoloader->setClass('Tests\Autoload\Preload\\' . $name, $file);
        $autoloader->setClass(PlainClass::class, __DIR__ . '/Preload/PlainClass.php');
        $preloader = new Preloader($autoloader, null);
        return [$preloader, (string) \realpath($file)];
    }

    public function testLoadSkipsFileWithMissingOptionalDependency() : void
    {
        [$preloader, $broken] = $this->preloadFixture('MissingDependencyOne');
        $loaded = $preloader->load();
        self::assertNotContains($broken, $loaded);
        self::assertContains(
            \realpath(__DIR__ . '/Preload/PlainClass.php'),
            $loaded
        );
        $failed = $preloader->getFailedFiles();
        self::assertArrayHasKey($broken, $failed);
        self::assertStringContainsString('MissingInterfaceOne', $failed[$broken]);
    }

    public function testLoadRethrowsWhenNotTolerant() : void
    {
        [$preloader] = $this->preloadFixture('MissingDependencyTwo');
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('MissingInterfaceTwo');
        $preloader->load(false);
    }

    public function testFailedFilesIsEmptyBeforeAndAfterACleanLoad() : void
    {
        $preloader = new Preloader(packagesDir: __DIR__ . '/support');
        self::assertSame([], $preloader->getFailedFiles());
        $preloader->load();
        self::assertSame([], $preloader->getFailedFiles());
    }

    public function testDefaultPackagesDirResolvesTwoLevelsAboveSrc() : void
    {
        $preloader = new Preloader();
        $packagesDir = $preloader->getPackagesDir();
        self::assertSame(
            \realpath(__DIR__ . '/../../') . \DIRECTORY_SEPARATOR,
            $packagesDir
        );
        // The resolved directory must be the one holding the sibling packages,
        // i.e. vendor/webisters on a Composer install and libraries on a
        // monorepo checkout. In both cases this package is a child of it.
        self::assertDirectoryExists($packagesDir);
        self::assertDirectoryExists(
            $packagesDir . \basename(\dirname(__DIR__))
        );
        self::assertFileExists(
            $packagesDir . \basename(\dirname(__DIR__))
            . \DIRECTORY_SEPARATOR . 'composer.json'
        );
    }

    public function testExcludesDefaultsToEmpty() : void
    {
        self::assertSame([], (new Preloader(packagesDir: null))->getExcludes());
    }

    public function testSetExcludesReplacesAndAddExcludesAppends() : void
    {
        $preloader = new Preloader(packagesDir: null);
        self::assertSame(
            ['*/a.php'],
            $preloader->setExcludes(['*/a.php'])->getExcludes()
        );
        self::assertSame(
            ['*/a.php', '*/b.php', '*/c.php'],
            $preloader->addExcludes('*/b.php', '*/c.php')->getExcludes()
        );
        self::assertSame(
            ['*/d.php'],
            $preloader->setExcludes(['*/d.php'])->getExcludes()
        );
    }

    public function testExcludedPackagesFilesAreNotListedOrRegistered() : void
    {
        $dir = __DIR__ . '/../vendor/webisters';
        $debugger = \realpath($dir . '/debug/src/Debugger.php');
        $preloader = new Preloader(packagesDir: $dir);
        self::assertContains($debugger, $preloader->listPackagesFiles());
        $excluding = new Preloader(packagesDir: $dir);
        $excluding->addExcludes('*/debug/src/Debugger.php');
        self::assertNotContains($debugger, $excluding->listPackagesFiles());
        self::assertArrayNotHasKey(
            \Framework\Debug\Debugger::class,
            $excluding->getAutoloader()->getClasses()
        );
    }

    public function testExcludeKeepsAMappedClassOutOfThePreload() : void
    {
        $file = __DIR__ . '/Preload/PlainClass.php';
        $autoloader = new Autoloader();
        $autoloader->setClass(PlainClass::class, $file);
        $preloader = new Preloader($autoloader, null);
        $preloader->addExcludes('*/Preload/PlainClass.php');
        self::assertNotContains(\realpath($file), $preloader->listFiles());
        self::assertSame([], $preloader->load());
    }

    public function testExcludeAppliesToNamespaceScans() : void
    {
        $autoloader = new Autoloader();
        $autoloader->setNamespace('Foo\Bar\Baz', __DIR__ . '/support');
        $preloader = new Preloader($autoloader, null);
        $preloader->addExcludes('*/support/OneEnum.php');
        $files = $preloader->listFiles();
        self::assertNotContains(__DIR__ . '/support/OneEnum.php', $files);
        self::assertContains(__DIR__ . '/support/OneTrait.php', $files);
    }

    public function testGetClassMapMapsNamesToAbsolutePaths() : void
    {
        $autoloader = new Autoloader();
        $autoloader->setNamespace('Foo\Bar\Baz', __DIR__ . '/support');
        $preloader = new Preloader($autoloader, null);
        $classMap = $preloader->getClassMap();
        self::assertArrayHasKey('Foo\Bar\Baz\OneEnum', $classMap);
        self::assertArrayHasKey('Foo\Bar\Baz\OneTrait', $classMap);
        self::assertSame(
            \realpath(__DIR__ . '/support/OneEnum.php'),
            $classMap['Foo\Bar\Baz\OneEnum']
        );
        foreach ($classMap as $file) {
            self::assertFileExists($file);
        }
    }

    public function testGetClassMapIsSortedByClassName() : void
    {
        $autoloader = new Autoloader();
        $autoloader->setNamespace('Foo\Bar\Baz', __DIR__ . '/support');
        $classMap = (new Preloader($autoloader, null))->getClassMap();
        $sorted = \array_keys($classMap);
        \sort($sorted);
        self::assertSame($sorted, \array_keys($classMap));
    }

    public function testGetClassMapHonoursExcludes() : void
    {
        $autoloader = new Autoloader();
        $autoloader->setNamespace('Foo\Bar\Baz', __DIR__ . '/support');
        $preloader = new Preloader($autoloader, null);
        $preloader->addExcludes('*/support/OneEnum.php');
        $classMap = $preloader->getClassMap();
        self::assertArrayNotHasKey('Foo\Bar\Baz\OneEnum', $classMap);
        self::assertArrayHasKey('Foo\Bar\Baz\OneTrait', $classMap);
    }

    public function testExportedClassMapIsValidPhpThatLoadsBack() : void
    {
        $autoloader = new Autoloader();
        $autoloader->setNamespace('Foo\Bar\Baz', __DIR__ . '/support');
        $preloader = new Preloader($autoloader, null);
        $file = \sys_get_temp_dir() . '/webisters-classmap-' . \getmypid() . '.php';
        $count = $preloader->exportClassMap($file);
        try {
            self::assertFileExists($file);
            self::assertSame($count, \count($preloader->getClassMap()));
            $loaded = require $file;
            self::assertSame($preloader->getClassMap(), $loaded);
            // The map feeds an autoloader back without touching the filesystem.
            $fresh = new Autoloader(false);
            $fresh->setClasses($loaded, false);
            self::assertSame(
                \realpath(__DIR__ . '/support/OneTrait.php'),
                $fresh->findClassPath('Foo\Bar\Baz\OneTrait')
            );
        } finally {
            if (\is_file($file)) {
                \unlink($file);
            }
        }
    }

    public function testExportClassMapThrowsWhenTheDirectoryIsMissing() : void
    {
        $preloader = new Preloader(packagesDir: null);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Classmap directory not found');
        $preloader->exportClassMap('/no/such/directory/classmap.php');
    }

    public function testAllDeclarations() : void
    {
        self::assertContains(
            __CLASS__,
            Preloader::getAllDeclarations()
        );
        self::assertContains(
            Preloader::class,
            Preloader::getAllDeclarations()
        );
    }

    public function testDeclarations() : void
    {
        self::assertNotContains(
            __CLASS__,
            Preloader::getDeclarations()
        );
        self::assertContains(
            Preloader::class,
            Preloader::getAllDeclarations()
        );
    }

    public function testIncludedFiles() : void
    {
        self::assertContains(
            __FILE__,
            Preloader::getIncludedFiles()
        );
    }
}
