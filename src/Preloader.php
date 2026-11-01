<?php declare(strict_types=1);
/*
 * This file is part of Webisters Autoload Library.
 *
 * (c) Hafiz Muhammad Moaz <thewebisters@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Framework\Autoload;

use Error;
use InvalidArgumentException;

require_once __DIR__ . '/Autoloader.php';
require_once __DIR__ . '/Locator.php';

/**
 * Class Preloader.
 *
 * @see https://www.php.net/manual/en/opcache.preloading.php
 *
 * @package autoload
 */
class Preloader
{
    /**
     * The main 'webisters' packages directory.
     *
     * @see Preloader::__construct() for how the default is resolved
     *
     * @var string
     */
    protected string $packagesDir;
    protected bool $loadPackages = true;
    protected bool $loadDevPackages = false;
    /**
     * The Autoloader instance necessary to autoload required classes.
     *
     * @var Autoloader
     */
    protected Autoloader $autoloader;
    /**
     * The Locator instance used to list files.
     *
     * @var Locator
     */
    protected Locator $locator;
    /**
     * Files that could not be loaded, as file paths to error messages.
     *
     * @var array<string,string>
     */
    protected array $failedFiles = [];
    /**
     * Glob patterns of files to keep out of the preload.
     *
     * @var array<int,string>
     */
    protected array $excludes = [];

    /**
     * Preloader constructor.
     *
     * The default $packagesDir is the directory two levels above src, which is
     * where the sibling 'webisters' packages sit in both supported layouts:
     *
     * - Composer install: vendor/webisters/autoload/src resolves to
     *   vendor/webisters
     * - Monorepo checkout: libraries/autoload/src resolves to libraries
     *
     * Pass an explicit path for any other layout, or null to disable packages
     * loading entirely.
     *
     * @param Autoloader|null $autoloader A custom Autoloader instance or null
     * to auto initialize a new
     * @param string|null $packagesDir The main 'webisters' packages directory or
     * null to disable packages loading
     */
    public function __construct(
        ?Autoloader $autoloader = null,
        ?string $packagesDir = __DIR__ . '/../../'
    ) {
        $this->loadPackages = $packagesDir !== null;
        if ($this->loadPackages) {
            $this->setPackagesDir($packagesDir);
        }
        $this->autoloader = $autoloader ?? new Autoloader();
        $this->locator = new Locator($this->autoloader);
    }

    public function getAutoloader() : Autoloader
    {
        return $this->autoloader;
    }

    public function getLocator() : Locator
    {
        return $this->locator;
    }

    public function setPackagesDir(string $packagesDir) : static
    {
        $realpath = \realpath($packagesDir);
        if (!$realpath || !\is_dir($packagesDir)) {
            throw new InvalidArgumentException('Invalid packages dir: ' . $packagesDir);
        }
        $this->packagesDir = $realpath . \DIRECTORY_SEPARATOR;
        return $this;
    }

    public function getPackagesDir() : string
    {
        return $this->packagesDir;
    }

    public function withPackages() : static
    {
        $this->loadPackages = true;
        return $this;
    }

    public function withDevPackages() : static
    {
        $this->loadDevPackages = true;
        return $this;
    }

    /**
     * Set the glob patterns of files to keep out of the preload, replacing any
     * patterns set before.
     *
     * Patterns are matched against the absolute file path with fnmatch(), where
     * "*" also matches directory separators. Use this for files that must not
     * be required ahead of time, typically because they have side effects.
     *
     * @param array<int,string> $patterns Glob patterns
     *
     * @see Preloader::isExcluded()
     *
     * @return static
     */
    public function setExcludes(array $patterns) : static
    {
        $this->excludes = \array_values($patterns);
        return $this;
    }

    /**
     * Add glob patterns of files to keep out of the preload.
     *
     * @param string ...$patterns Glob patterns
     *
     * @see Preloader::setExcludes()
     *
     * @return static
     */
    public function addExcludes(string ...$patterns) : static
    {
        foreach ($patterns as $pattern) {
            $this->excludes[] = $pattern;
        }
        return $this;
    }

    /**
     * Get the glob patterns of files kept out of the preload.
     *
     * @return array<int,string>
     */
    public function getExcludes() : array
    {
        return $this->excludes;
    }

    /**
     * Tell whether a file matches any of the exclude patterns.
     *
     * @param string $file Absolute file path
     *
     * @see Preloader::setExcludes()
     *
     * @return bool
     */
    protected function isExcluded(string $file) : bool
    {
        foreach ($this->excludes as $pattern) {
            if (\fnmatch($pattern, $file)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param bool $setClasses
     *
     * @return array<int,string>
     */
    public function listPackagesFiles(bool $setClasses = true) : array
    {
        $result = [];
        foreach ($this->getLocator()->listFiles($this->getPackagesDir()) as $file) {
            if (!\str_ends_with($file, '.php') || $this->isExcluded($file)) {
                continue;
            }
            $className = $this->getLocator()->getClassName($file);
            if (!$className
                || ($className !== 'Webisters' && !\str_starts_with($className, 'Framework\\'))
            ) {
                continue;
            }
            if (!$this->loadDevPackages && $this->isDevelopmentClass($className)) {
                continue;
            }
            if ($setClasses) {
                $this->getAutoloader()->setClass($className, $file);
            }
            $result[] = $file;
        }
        \sort($result);
        return \array_unique($result);
    }

    protected function isDevelopmentClass(string $className) : bool
    {
        return \str_starts_with($className, 'Framework\CodingStandard\\')
            || \str_starts_with($className, 'Framework\Testing\\');
    }

    /**
     * @param bool $setClasses
     *
     * @return array<int,string>
     */
    public function listFiles(bool $setClasses = true) : array
    {
        $result = [];
        foreach ($this->getAutoloader()->getClasses() as $file) {
            if ($this->isExcluded($file)) {
                continue;
            }
            $result[] = $file;
        }
        foreach ($this->getAutoloader()->getNamespaces() as $namespace => $directories) {
            foreach ($directories as $directory) {
                $files = $this->getLocator()->listFiles($directory);
                foreach ($files as $file) {
                    if (!\str_ends_with($file, '.php') || $this->isExcluded($file)) {
                        continue;
                    }
                    $className = $this->getLocator()->getClassName($file);
                    if (!$className
                        || !\str_starts_with($className, $namespace . '\\')
                    ) {
                        continue;
                    }
                    if ($setClasses) {
                        $this->getAutoloader()->setClass($className, $file);
                    }
                    $result[] = $file;
                }
            }
        }
        if ($this->loadPackages) {
            $result = [...$result, ...$this->listPackagesFiles($setClasses)];
        }
        \sort($result);
        return \array_unique($result);
    }

    /**
     * Load files to be seen by the PHP OPcache Preloading when the engine starts.
     *
     * A class may reference a type that comes from an optional dependency, i.e.
     * a package listed only under "suggest" or "require-dev". Requiring such a
     * file raises a catchable Error, which would otherwise abort the whole
     * preload. By default those files are skipped and recorded in
     * Preloader::getFailedFiles() so the caller can report them.
     *
     * @param bool $tolerant Whether to skip files that raise an Error instead
     * of letting it abort the preload
     *
     * @see Preloader::getFailedFiles()
     *
     * @throws Error if $tolerant is false and a file cannot be loaded
     *
     * @return array<int,string> The loaded files
     */
    public function load(bool $tolerant = true) : array
    {
        $this->failedFiles = [];
        $loadedFiles = [];
        foreach ($this->listFiles() as $file) {
            try {
                (static function () use ($file) : void {
                    require_once $file;
                })();
            } catch (Error $error) {
                if (!$tolerant) {
                    throw $error;
                }
                $this->failedFiles[$file] = $error->getMessage();
                continue;
            }
            $loadedFiles[] = $file;
        }
        return $loadedFiles;
    }

    /**
     * Get the files that the last call to Preloader::load() could not load.
     *
     * @see Preloader::load()
     *
     * @return array<string,string> File paths as keys and error messages as values
     */
    public function getFailedFiles() : array
    {
        return $this->failedFiles;
    }

    /**
     * Get a list of all declared classes, interfaces and traits.
     *
     * @return array<int,string>
     */
    public static function getAllDeclarations() : array
    {
        $declarations = [
            ...\get_declared_classes(),
            ...\get_declared_interfaces(),
            ...\get_declared_traits(),
        ];
        \sort($declarations);
        return $declarations;
    }

    /**
     * Get a list of Framework declarations.
     *
     * @return array<int,string>
     */
    public static function getDeclarations() : array
    {
        $result = [];
        foreach (static::getAllDeclarations() as $declaration) {
            if ($declaration === 'Webisters' || \str_starts_with($declaration, 'Framework\\')) {
                $result[] = $declaration;
            }
        }
        return $result;
    }

    /**
     * Get a list of all included/required files.
     *
     * @return array<int,string>
     */
    public static function getIncludedFiles() : array
    {
        return \get_included_files();
    }
}
