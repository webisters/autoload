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
use Framework\Autoload\Locator;
use PHPUnit\Framework\TestCase;

final class LocatorTest extends TestCase
{
    protected Autoloader $autoloader;
    protected Locator $locator;

    /**
     * @var array<int,string>
     */
    private array $snippets = [];

    public function setUp() : void
    {
        $this->autoloader = new Autoloader();
        $this->locator = new Locator($this->autoloader);
    }

    /**
     * @return array<int,string>
     */
    protected function getFiles() : array
    {
        return [
            __DIR__ . '/AutoloaderTest.php',
            __DIR__ . '/Debug/AutoloadCollectionTest.php',
            __DIR__ . '/Debug/AutoloadCollectorTest.php',
            __FILE__,
            __DIR__ . '/PreloaderTest.php',
            __DIR__ . '/preload/MissingDependencyOne.php',
            __DIR__ . '/preload/MissingDependencyTwo.php',
            __DIR__ . '/preload/PlainClass.php',
            __DIR__ . '/support/NamespacedClass.php',
            __DIR__ . '/support/NoClass.php',
            __DIR__ . '/support/OneClass.php',
            __DIR__ . '/support/OneEnum.php',
            __DIR__ . '/support/OneInterface.php',
            __DIR__ . '/support/OneTrait.php',
            __DIR__ . '/support/ReturnObject.php',
            __DIR__ . '/support/foo.txt',
        ];
    }

    public function testListFiles() : void
    {
        self::assertNull($this->locator->listFiles(__DIR__ . '/unknown'));
        $list = $this->getFiles();
        self::assertSame($list, $this->locator->listFiles(__DIR__));
        self::assertSame($list, $this->locator->listFiles(__DIR__ . '/../tests'));
    }

    public function testGetClass() : void
    {
        self::assertSame(__CLASS__, $this->locator->getClassName(__FILE__));
        self::assertNull($this->locator->getClassName(__DIR__ . '/unknown'));
        self::assertSame(
            'Foo\NamespacedClass',
            $this->locator->getClassName(__DIR__ . '/support/NamespacedClass.php')
        );
        self::assertNull($this->locator->getClassName(__DIR__ . '/support/NoClass.php'));
        self::assertNull($this->locator->getClassName(__DIR__ . '/support/ReturnObject.php'));
        self::assertSame(
            'OneClass',
            $this->locator->getClassName(__DIR__ . '/support/OneClass.php')
        );
        self::assertSame(
            'OneInterface',
            $this->locator->getClassName(__DIR__ . '/support/OneInterface.php')
        );
        self::assertSame(
            'Foo\Bar\Baz\OneTrait',
            $this->locator->getClassName(__DIR__ . '/support/OneTrait.php')
        );
        self::assertSame(
            'Foo\Bar\Baz\OneEnum',
            $this->locator->getClassName(__DIR__ . '/support/OneEnum.php')
        );
    }

    /**
     * Writes a PHP snippet to a temporary file and returns its path.
     *
     * Snippets are generated instead of committed as fixtures so that an
     * intentionally unparseable one never reaches php-cs-fixer, and so that
     * the expected listing in getFiles() stays stable.
     *
     * @param string $code
     *
     * @return string
     */
    private function snippet(string $code) : string
    {
        $dir = \sys_get_temp_dir() . '/webisters-locator-' . \getmypid();
        if (!\is_dir($dir)) {
            \mkdir($dir, 0777, true);
        }
        $file = $dir . '/' . \md5($code) . '.php';
        \file_put_contents($file, $code);
        $this->snippets[] = $file;
        return $file;
    }

    protected function tearDown() : void
    {
        foreach ($this->snippets as $file) {
            if (\is_file($file)) {
                \unlink($file);
            }
        }
        $this->snippets = [];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('classNameProvider')]
    public function testGetClassNameEdgeCases(string $code, ?string $expected) : void
    {
        self::assertSame(
            $expected,
            $this->locator->getClassName($this->snippet($code))
        );
    }

    public function testGetClassNameOnAMissingFile() : void
    {
        self::assertNull($this->locator->getClassName(__DIR__ . '/does-not-exist.php'));
    }

    public function testGetClassNameOnADirectory() : void
    {
        self::assertNull($this->locator->getClassName(__DIR__));
    }

    public function testClassNameCacheIsOffByDefault() : void
    {
        self::assertFalse($this->locator->isCachingClassNames());
    }

    public function testWithClassNameCacheTogglesTheFlag() : void
    {
        self::assertTrue($this->locator->withClassNameCache()->isCachingClassNames());
        self::assertFalse($this->locator->withClassNameCache(false)->isCachingClassNames());
    }

    public function testCachedLookupsMatchUncachedOnes() : void
    {
        $file = $this->snippet("<?php\nnamespace Probe;\nclass Cached {}\n");
        $uncached = $this->locator->getClassName($file);
        $this->locator->withClassNameCache();
        self::assertSame($uncached, $this->locator->getClassName($file));
        // Second call comes from the cache.
        self::assertSame($uncached, $this->locator->getClassName($file));
    }

    public function testCacheRemembersAMissingDeclarationToo() : void
    {
        $file = $this->snippet("<?php\n\\var_dump('nothing here');\n");
        $this->locator->withClassNameCache();
        self::assertNull($this->locator->getClassName($file));
        self::assertNull($this->locator->getClassName($file));
    }

    public function testCacheIsInvalidatedWhenTheFileChanges() : void
    {
        $file = $this->snippet("<?php\nnamespace Probe;\nclass Before {}\n");
        $this->locator->withClassNameCache();
        self::assertSame('Probe\Before', $this->locator->getClassName($file));
        \file_put_contents($file, "<?php\nnamespace Probe;\nclass After {}\n");
        // Force a distinct mtime, which has one-second resolution.
        \touch($file, \time() + 10);
        \clearstatcache(true, $file);
        self::assertSame('Probe\After', $this->locator->getClassName($file));
    }

    public function testStaleResultIsReturnedWhenTheCacheIsNotInvalidated() : void
    {
        $file = $this->snippet("<?php\nnamespace Probe;\nclass Pinned {}\n");
        $this->locator->withClassNameCache();
        self::assertSame('Probe\Pinned', $this->locator->getClassName($file));
        $mtime = (int) \filemtime($file);
        \file_put_contents($file, "<?php\nnamespace Probe;\nclass Changed {}\n");
        // Same mtime, so the entry stays valid by design.
        \touch($file, $mtime);
        \clearstatcache(true, $file);
        self::assertSame('Probe\Pinned', $this->locator->getClassName($file));
        // Clearing the cache picks the new declaration up.
        self::assertSame(
            'Probe\Changed',
            $this->locator->clearClassNameCache()->getClassName($file)
        );
    }

    public function testDisablingTheCacheDiscardsIt() : void
    {
        $file = $this->snippet("<?php\nnamespace Probe;\nclass Dropped {}\n");
        $this->locator->withClassNameCache();
        self::assertSame('Probe\Dropped', $this->locator->getClassName($file));
        $mtime = (int) \filemtime($file);
        \file_put_contents($file, "<?php\nnamespace Probe;\nclass Replaced {}\n");
        \touch($file, $mtime);
        \clearstatcache(true, $file);
        self::assertSame(
            'Probe\Replaced',
            $this->locator->withClassNameCache(false)->getClassName($file)
        );
    }

    public function testGetFiles() : void
    {
        $this->autoloader->setNamespace('Autoload', __DIR__ . '/..');
        self::assertSame(
            $this->getFiles(),
            $this->locator->getFiles('tests')
        );
    }

    public function testFindFiles() : void
    {
        $this->autoloader->setNamespace('Tests', __DIR__);
        self::assertSame(
            [
                __DIR__ . '/LocatorTest.php',
            ],
            $this->locator->findFiles('LocatorTest')
        );
        self::assertSame(
            [
                __DIR__ . '/LocatorTest.php',
            ],
            $this->locator->findFiles('LocatorTest', '.php')
        );
        self::assertEmpty($this->locator->findFiles('LocatorFoo'));
        self::assertEmpty($this->locator->findFiles('LocatorFoo', '.php'));
        self::assertEmpty($this->locator->findFiles('LocatorTest', '.py'));
    }

    public function testNamespacedFilepath() : void
    {
        $this->autoloader->setNamespace('Tests\Foo', __DIR__);
        self::assertSame(
            __FILE__,
            $this->locator->getNamespacedFilepath('Tests/Foo/LocatorTest')
        );
        $this->autoloader->setNamespace('Tests', __DIR__);
        self::assertSame(
            __FILE__,
            $this->locator->getNamespacedFilepath('Tests/LocatorTest')
        );
        self::assertNull(
            $this->locator->getNamespacedFilepath('Tests/Foo')
        );
        self::assertNull(
            $this->locator->getNamespacedFilepath('LocatorTest')
        );
    }

    /**
     * @return array<string,array<int,string|null>>
     */
    public static function classNameProvider() : array
    {
        return [
            'plain class' => [
                "<?php\nnamespace Probe;\nclass Plain {}\n",
                'Probe\Plain',
            ],
            'no namespace' => [
                "<?php\nclass Globalish {}\n",
                'Globalish',
            ],
            'braced namespace' => [
                "<?php\nnamespace Probe\\Braced { class Inside {} }\n",
                'Probe\Braced\Inside',
            ],
            'first of multiple declarations wins' => [
                "<?php\nnamespace Probe;\nclass First {}\nclass Second {}\n",
                'Probe\First',
            ],
            'interface' => [
                "<?php\nnamespace Probe;\ninterface Contract extends \\Countable {}\n",
                'Probe\Contract',
            ],
            'trait' => [
                "<?php\nnamespace Probe;\ntrait Helper {}\n",
                'Probe\Helper',
            ],
            'backed enum' => [
                "<?php\nnamespace Probe;\nenum Suit: string { case H = 'h'; }\n",
                'Probe\Suit',
            ],
            'readonly final class' => [
                "<?php\nnamespace Probe;\nfinal readonly class Locked {}\n",
                'Probe\Locked',
            ],
            'class with attribute' => [
                "<?php\nnamespace Probe;\n#[\\Attribute]\nclass Marked {}\n",
                'Probe\Marked',
            ],
            'doc comment between keyword and name' => [
                "<?php\nnamespace Probe;\nclass /* c */ Commented {}\n",
                'Probe\Commented',
            ],
            // Regressions for issue #20.
            'class constant before a declaration' => [
                "<?php\nnamespace Probe;\nconst X = \\Other\\Thing::class;\nfinal class AfterConst {}\n",
                'Probe\AfterConst',
            ],
            'class constant inside a method body' => [
                "<?php\nnamespace Probe;\nclass Real { public function f() { return \\Other\\T::class; } }\n",
                'Probe\Real',
            ],
            'anonymous class only' => [
                "<?php\nnamespace Probe;\n\$o = new class { public int \$n = 1; };\n",
                null,
            ],
            'anonymous class with constructor args' => [
                "<?php\nnamespace Probe;\n\$o = new class(1) {};\n",
                null,
            ],
            'anonymous class extending' => [
                "<?php\nnamespace Probe;\n\$o = new class extends \\stdClass {};\n",
                null,
            ],
            'named class after an anonymous one' => [
                "<?php\nnamespace Probe;\n\$o = new class {};\nclass AfterAnon {}\n",
                'Probe\AfterAnon',
            ],
            'top level return before a declaration' => [
                "<?php\nnamespace Probe;\nif (!\\function_exists('x')) { return; }\nclass AfterReturn {}\n",
                'Probe\AfterReturn',
            ],
            'returned anonymous class' => [
                "<?php\nreturn new class() extends \\stdClass {};\n",
                null,
            ],
            'no declaration at all' => [
                "<?php\n\\var_dump('Test!');\n",
                null,
            ],
            'unparseable file' => [
                "<?php\nclass { broken syntax here\n",
                null,
            ],
        ];
    }
}
