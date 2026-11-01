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
use PHPUnit\Framework\TestCase;

final class AutoloaderTest extends TestCase
{
    protected Autoloader $autoloader;

    public function setUp() : void
    {
        $this->autoloader = new Autoloader();
    }

    public function testNamespaces() : void
    {
        self::assertEmpty($this->autoloader->getNamespaces());
        $this->autoloader->setNamespaces([
            'Foo\Bar\\' => [__DIR__, __DIR__],
            'Tests' => __DIR__,
        ]);
        $dir = __DIR__ . \DIRECTORY_SEPARATOR;
        self::assertSame([$dir], $this->autoloader->getNamespace('Tests'));
        self::assertEmpty($this->autoloader->getNamespace('Testss'));
        self::assertSame([
            'Tests' => [$dir],
            'Foo\Bar' => [$dir, $dir],
        ], $this->autoloader->getNamespaces());
        $this->autoloader->removeNamespaces(['Tests']);
        self::assertSame([
            'Foo\Bar' => [$dir, $dir],
        ], $this->autoloader->getNamespaces());
        $this->autoloader->addNamespaces([
            '\Foo\Bar' => [__DIR__],
        ]);
        self::assertSame([
            'Foo\Bar' => [$dir, $dir, $dir],
        ], $this->autoloader->getNamespaces());
    }

    public function testClasses() : void
    {
        $this->autoloader->setClasses([
            '\\' . __CLASS__ => __FILE__,
            '\Tests\LocatorTest' => __DIR__ . '/LocatorTest.php',
        ]);
        self::assertSame(
            [
                __CLASS__ => __FILE__,
                'Tests\LocatorTest' => __DIR__ . '/LocatorTest.php',
            ],
            $this->autoloader->getClasses()
        );
        $this->autoloader->removeClasses([__CLASS__]);
        self::assertSame(
            [
                'Tests\LocatorTest' => __DIR__ . '/LocatorTest.php',
            ],
            $this->autoloader->getClasses()
        );
    }

    public function testFindClassPath() : void
    {
        self::assertNull($this->autoloader->findClassPath(__CLASS__));
        $this->autoloader->setNamespace(__NAMESPACE__, __DIR__);
        self::assertSame(__FILE__, $this->autoloader->findClassPath(__CLASS__));
        $this->autoloader->setClass(__CLASS__, __FILE__);
        self::assertSame(__FILE__, $this->autoloader->findClassPath(__CLASS__));
    }

    public function testLoadClass() : void
    {
        self::assertFalse($this->autoloader->loadClass('Foo\NamespacedClass'));
        $this->autoloader->setNamespace('Foo', __DIR__ . '/support');
        self::assertTrue($this->autoloader->loadClass('Foo\NamespacedClass'));
    }

    public function testPathIsNotFile() : void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Path is not a file: foo');
        $this->autoloader->setClass('Foo', 'foo');
    }

    public function testPathIsNotDirectory() : void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Path is not a directory: foo');
        $this->autoloader->setNamespace('Foo', 'foo');
    }

    public function testNewClass() : void
    {
        $this->autoloader->setNamespace('Foo\NamespacedClass', __DIR__ . '/support');
        self::assertInstanceOf(
            \Foo\NamespacedClass::class,
            new \Foo\NamespacedClass()
        );
    }

    public function testUnregister() : void
    {
        self::assertTrue($this->autoloader->unregister());
    }

    public function testConflictDetectionIsOffByDefault() : void
    {
        self::assertFalse($this->autoloader->isDetectingConflicts());
        $this->autoloader->setNamespace('Dup', __DIR__);
        $this->autoloader->setNamespace('Dup', __DIR__ . '/support');
        self::assertCount(0, $this->autoloader->getConflicts());
    }

    public function testWithConflictDetectionTogglesTheFlag() : void
    {
        self::assertTrue(
            $this->autoloader->withConflictDetection()->isDetectingConflicts()
        );
        self::assertFalse(
            $this->autoloader->withConflictDetection(false)->isDetectingConflicts()
        );
    }

    public function testSetNamespaceTwiceIsReportedAsAConflict() : void
    {
        $this->autoloader->withConflictDetection();
        $this->autoloader->setNamespace('Dup', __DIR__);
        self::assertCount(0, $this->autoloader->getConflicts());
        $this->autoloader->setNamespace('Dup', __DIR__ . '/support');
        $conflicts = $this->autoloader->getConflicts();
        self::assertCount(1, $conflicts);
        self::assertSame('namespace', $conflicts[0]['type']);
        self::assertSame('Dup', $conflicts[0]['name']);
        self::assertStringContainsString('tests', $conflicts[0]['previous']);
        self::assertStringContainsString('support', $conflicts[0]['current']);
        // The later registration still wins; detection changes nothing.
        self::assertSame(
            [\realpath(__DIR__ . '/support') . \DIRECTORY_SEPARATOR],
            $this->autoloader->getNamespace('Dup')
        );
    }

    public function testSetNamespaceWithTheSameDirectoryIsNotAConflict() : void
    {
        $this->autoloader->withConflictDetection();
        $this->autoloader->setNamespace('Same', __DIR__);
        $this->autoloader->setNamespace('Same', __DIR__);
        self::assertCount(0, $this->autoloader->getConflicts());
    }

    public function testAddNamespaceWithADuplicateDirectoryIsReported() : void
    {
        $this->autoloader->withConflictDetection();
        $this->autoloader->addNamespace('Dup', __DIR__);
        $this->autoloader->addNamespace('Dup', __DIR__ . '/support');
        self::assertCount(0, $this->autoloader->getConflicts());
        $this->autoloader->addNamespace('Dup', __DIR__);
        $conflicts = $this->autoloader->getConflicts();
        self::assertCount(1, $conflicts);
        self::assertSame('namespace-directory', $conflicts[0]['type']);
        self::assertSame('Dup', $conflicts[0]['name']);
    }

    public function testSetClassTwiceIsReportedAsAConflict() : void
    {
        $this->autoloader->withConflictDetection();
        $this->autoloader->setClass('Dup', __DIR__ . '/support/OneClass.php');
        self::assertCount(0, $this->autoloader->getConflicts());
        $this->autoloader->setClass('Dup', __DIR__ . '/support/OneInterface.php');
        $conflicts = $this->autoloader->getConflicts();
        self::assertCount(1, $conflicts);
        self::assertSame('class', $conflicts[0]['type']);
        self::assertSame('Dup', $conflicts[0]['name']);
        self::assertStringContainsString('OneClass.php', $conflicts[0]['previous']);
        self::assertStringContainsString('OneInterface.php', $conflicts[0]['current']);
    }

    public function testSetClassWithTheSameFileIsNotAConflict() : void
    {
        $this->autoloader->withConflictDetection();
        $this->autoloader->setClass('Same', __DIR__ . '/support/OneClass.php');
        $this->autoloader->setClass('Same', __DIR__ . '/support/OneClass.php');
        self::assertCount(0, $this->autoloader->getConflicts());
    }

    public function testConflictHandlerIsCalledAsConflictsHappen() : void
    {
        $seen = [];
        $this->autoloader->withConflictDetection(
            true,
            static function (array $conflict) use (&$seen) : void {
                $seen[] = $conflict['type'] . ':' . $conflict['name'];
            }
        );
        $this->autoloader->setNamespace('Dup', __DIR__);
        $this->autoloader->setNamespace('Dup', __DIR__ . '/support');
        $this->autoloader->setClass('Dup', __DIR__ . '/support/OneClass.php');
        $this->autoloader->setClass('Dup', __DIR__ . '/support/OneTrait.php');
        self::assertSame(['namespace:Dup', 'class:Dup'], $seen);
    }

    public function testClearConflictsKeepsDetectionOn() : void
    {
        $this->autoloader->withConflictDetection();
        $this->autoloader->setNamespace('Dup', __DIR__);
        $this->autoloader->setNamespace('Dup', __DIR__ . '/support');
        self::assertCount(1, $this->autoloader->getConflicts());
        $this->autoloader->clearConflicts();
        self::assertCount(0, $this->autoloader->getConflicts());
        self::assertTrue($this->autoloader->isDetectingConflicts());
    }

    public function testDisablingDetectionDiscardsRecordedConflicts() : void
    {
        $this->autoloader->withConflictDetection();
        $this->autoloader->setNamespace('Dup', __DIR__);
        $this->autoloader->setNamespace('Dup', __DIR__ . '/support');
        self::assertCount(1, $this->autoloader->getConflicts());
        $this->autoloader->withConflictDetection(false);
        self::assertCount(0, $this->autoloader->getConflicts());
    }

    public function testSetClassesValidatesPathsByDefault() : void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Path is not a file');
        $this->autoloader->setClasses(['Missing' => '/no/such/file.php']);
    }

    public function testSetClassesCanSkipValidationForAGeneratedMap() : void
    {
        $this->autoloader->setClasses(['Missing' => '/no/such/file.php'], false);
        self::assertSame(
            '/no/such/file.php',
            $this->autoloader->getClass('Missing')
        );
    }

    public function testUnvalidatedSetClassesStillTrimsClassNames() : void
    {
        $this->autoloader->setClasses(['\Lead\Slash' => '/some/file.php'], false);
        self::assertSame('/some/file.php', $this->autoloader->getClass('Lead\Slash'));
    }
}
