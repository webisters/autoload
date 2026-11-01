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

use Framework\Autoload\Debug\AutoloadCollector;
use Framework\Debug\Collector;
use JetBrains\PhpStorm\Pure;
use RuntimeException;

/**
 * Class Autoloader.
 *
 * The Autoloader class allows to set namespace directories to search for files
 * (PSR4) and set the absolute path of classes without namespaces (PSR0).
 *
 * @package autoload
 */
class Autoloader
{
    /**
     * List of classes to file paths.
     *
     * @var array<string,string>
     */
    protected array $classes = [];
    /**
     * List of namespaces to directory paths.
     *
     * @var array<string,array<int,string>>
     */
    protected array $namespaces = [];
    protected AutoloadCollector $debugCollector;
    protected bool $detectConflicts = false;
    /**
     * Registration conflicts seen so far.
     *
     * @var array<int,array<string,string>>
     */
    protected array $conflicts = [];
    /**
     * @var callable|null
     */
    protected $conflictHandler;

    /**
     * Autoloader constructor.
     *
     * @param bool $register Register the Autoloader::loadClass() as autoload
     * implementation
     * @param string $extensions A comma delimited list of file extensions for
     * spl_autoload
     *
     * @see Autoloader::loadClass()
     */
    public function __construct(bool $register = true, string $extensions = '.php')
    {
        if ($register) {
            $this->register($extensions);
        }
    }

    /**
     * Registers the Autoloader::loadClass() as autoload implementation.
     *
     * @param string $extensions A comma delimited list of file extensions for
     * spl_autoload
     *
     * @see Autoloader::loadClass()
     *
     * @return bool True on success or false on failure
     */
    public function register(string $extensions = '.php') : bool
    {
        \spl_autoload_extensions($extensions);
        // @phpstan-ignore-next-line
        return \spl_autoload_register([$this, 'loadClass'], true, false);
    }

    /**
     * Unregisters the Autoloader::loadClass() as autoload implementation.
     *
     * @see Autoloader::loadClass()
     *
     * @return bool True on success or false on failure
     */
    public function unregister() : bool
    {
        return \spl_autoload_unregister([$this, 'loadClass']);
    }

    /**
     * Enable or disable detection of conflicting registrations.
     *
     * Registering a namespace prefix or a class twice is silent by default:
     * the later call simply wins. That is the intended behaviour for
     * overrides, but it also hides an accidental setNamespace() where
     * addNamespace() was meant, which is hard to track down because the
     * symptom is a class loading from the wrong directory.
     *
     * With detection on, each conflict is recorded in getConflicts() and, when
     * a handler is given, passed to it as it happens. Nothing is thrown and no
     * registration is refused, so turning this on cannot change which file a
     * class loads from.
     *
     * Each conflict is an array with the keys "type", one of "namespace",
     * "namespace-directory" or "class", "name", "previous" and "current".
     *
     * @param bool $enabled
     * @param callable|null $handler Receives each conflict as an
     * array<string,string>, for logging
     *
     * @see Autoloader::getConflicts()
     *
     * @return static
     */
    public function withConflictDetection(bool $enabled = true, ?callable $handler = null) : static
    {
        $this->detectConflicts = $enabled;
        $this->conflictHandler = $enabled ? $handler : null;
        if (!$enabled) {
            $this->conflicts = [];
        }
        return $this;
    }

    /**
     * Tell whether conflict detection is enabled.
     *
     * @return bool
     */
    public function isDetectingConflicts() : bool
    {
        return $this->detectConflicts;
    }

    /**
     * Get the registration conflicts recorded so far.
     *
     * @see Autoloader::withConflictDetection()
     *
     * @return array<int,array<string,string>>
     */
    public function getConflicts() : array
    {
        return $this->conflicts;
    }

    /**
     * Discard the recorded conflicts, keeping detection enabled.
     *
     * @return static
     */
    public function clearConflicts() : static
    {
        $this->conflicts = [];
        return $this;
    }

    /**
     * Record one conflict, if detection is enabled.
     *
     * @param string $type One of "namespace", "namespace-directory" or "class"
     * @param string $name The namespace or class name involved
     * @param string $previous What was registered before
     * @param string $current What is replacing or duplicating it
     *
     * @return void
     */
    protected function addConflict(
        string $type,
        string $name,
        string $previous,
        string $current
    ) : void {
        if (!$this->detectConflicts) {
            return;
        }
        $conflict = [
            'type' => $type,
            'name' => $name,
            'previous' => $previous,
            'current' => $current,
        ];
        $this->conflicts[] = $conflict;
        if ($this->conflictHandler !== null) {
            ($this->conflictHandler)($conflict);
        }
    }

    /**
     * Sets one namespace mapping for a directory path.
     *
     * @param string $namespace Namespace name
     * @param array<string>|string $dir Directory path
     *
     * @return static
     */
    public function setNamespace(string $namespace, array | string $dir) : static
    {
        $directories = $this->makeRenderedDirectoryPaths((array) $dir);
        if ($directories) {
            $name = $this->renderRealName($namespace);
            if ($this->detectConflicts
                && isset($this->namespaces[$name])
                && $this->namespaces[$name] !== $directories
            ) {
                $this->addConflict(
                    'namespace',
                    $name,
                    \implode(', ', $this->namespaces[$name]),
                    \implode(', ', $directories)
                );
            }
            $this->namespaces[$name] = $directories;
            $this->sortNamespaces();
        }
        return $this;
    }

    /**
     * @param array<string> $directories
     *
     * @return array<int,string>
     */
    protected function makeRenderedDirectoryPaths(array $directories) : array
    {
        $paths = [];
        foreach ($directories as $directory) {
            $paths[] = $this->renderDirectoryPath($directory);
        }
        return $paths;
    }

    /**
     * Adds directory paths to a namespace.
     *
     * @param string $namespace Namespace name
     * @param array<string>|string $dir Directory path
     *
     * @return static
     */
    public function addNamespace(string $namespace, array | string $dir) : static
    {
        $directories = $this->makeRenderedDirectoryPaths((array) $dir);
        if ($directories) {
            $name = $this->renderRealName($namespace);
            if (isset($this->namespaces[$name])) {
                if ($this->detectConflicts) {
                    foreach ($directories as $directory) {
                        if (\in_array($directory, $this->namespaces[$name], true)) {
                            $this->addConflict(
                                'namespace-directory',
                                $name,
                                \implode(', ', $this->namespaces[$name]),
                                $directory
                            );
                        }
                    }
                }
                $directories = [...$this->namespaces[$name], ...$directories];
            }
            $this->namespaces[$name] = $directories;
            $this->sortNamespaces();
        }
        return $this;
    }

    protected function sortNamespaces() : void
    {
        \krsort($this->namespaces);
    }

    /**
     * Sets namespaces mapping for directory paths.
     *
     * @param array<string,array<string>|string> $namespaces Namespace names
     * as keys and directory paths as values
     *
     * @return static
     */
    public function setNamespaces(array $namespaces) : static
    {
        foreach ($namespaces as $name => $dir) {
            $this->setNamespace($name, $dir);
        }
        $this->sortNamespaces();
        return $this;
    }

    /**
     * Adds directory paths to namespaces.
     *
     * @param array<string,array<string>|string> $namespaces Namespace names
     * as keys and directory paths as values
     *
     * @return static
     */
    public function addNamespaces(array $namespaces) : static
    {
        foreach ($namespaces as $name => $dir) {
            $this->addNamespace($name, $dir);
        }
        return $this;
    }

    /**
     * Gets the directory paths for a given namespace.
     *
     * @param string $name Namespace name
     *
     * @return array<int,string> The namespace directory paths
     */
    #[Pure]
    public function getNamespace(string $name) : array
    {
        return $this->namespaces[$this->renderRealName($name)] ?? [];
    }

    /**
     * Gets all mapped namespaces.
     *
     * @return array<string,array<int,string>>
     */
    #[Pure]
    public function getNamespaces() : array
    {
        return $this->namespaces;
    }

    /**
     * Removes one namespace from the mapping.
     *
     * @param string $name Namespace name
     *
     * @return static
     */
    public function removeNamespace(string $name) : static
    {
        unset($this->namespaces[$this->renderRealName($name)]);
        return $this;
    }

    /**
     * Removes namespaces from the mapping.
     *
     * @param array<string> $names List of namespace names
     *
     * @return static
     */
    public function removeNamespaces(array $names) : static
    {
        foreach ($names as $name) {
            $this->removeNamespace($name);
        }
        return $this;
    }

    /**
     * Sets one class mapping for a file path.
     *
     * @param string $name Fully qualified class name (with namespace)
     * @param string $filepath Class file path
     *
     * @return static
     */
    public function setClass(string $name, string $filepath) : static
    {
        $class = $this->renderRealName($name);
        $path = $this->renderFilePath($filepath);
        if ($this->detectConflicts
            && isset($this->classes[$class])
            && $this->classes[$class] !== $path
        ) {
            $this->addConflict('class', $class, $this->classes[$class], $path);
        }
        $this->classes[$class] = $path;
        return $this;
    }

    /**
     * Sets classes mapping for file paths.
     *
     * Each path is resolved with realpath() and checked with is_file(), which
     * costs one stat per class. When loading a generated classmap in
     * production that cost is the very thing the classmap exists to avoid, so
     * pass $validate as false to assign the paths as given. Only do that for a
     * map produced by Preloader::exportClassMap(), whose paths are already
     * canonical and absolute.
     *
     * @param array<string,string> $classes Associative array with class names
     * as keys and file paths as values
     * @param bool $validate Whether to resolve and check each path
     *
     * @see Preloader::exportClassMap()
     *
     * @return static
     */
    public function setClasses(array $classes, bool $validate = true) : static
    {
        if ($validate) {
            foreach ($classes as $name => $filepath) {
                $this->setClass($name, $filepath);
            }
            return $this;
        }
        foreach ($classes as $name => $filepath) {
            $this->classes[$this->renderRealName($name)] = $filepath;
        }
        return $this;
    }

    /**
     * Gets a class file path.
     *
     * @param string $name Fully qualified class name (with namespace)
     *
     * @return string|null The file path or null if class is not mapped
     */
    #[Pure]
    public function getClass(string $name) : ?string
    {
        return $this->classes[$this->renderRealName($name)] ?? null;
    }

    /**
     * Gets all mapped classes.
     *
     * @return array<string,string> An array of class names as keys and
     * file paths as values
     */
    #[Pure]
    public function getClasses() : array
    {
        return $this->classes;
    }

    /**
     * Removes one class from the mapping.
     *
     * @param string $name Fully qualified class name (with namespace)
     *
     * @return static
     */
    public function removeClass(string $name) : static
    {
        unset($this->classes[$this->renderRealName($name)]);
        return $this;
    }

    /**
     * Removes classes from the mapping.
     *
     * @param array<int,string> $names List of class names
     *
     * @return static
     */
    public function removeClasses(array $names) : static
    {
        foreach ($names as $name) {
            $this->removeClass($name);
        }
        return $this;
    }

    /**
     * Finds the file path of a class searching in the class mapping and
     * resolving namespaces.
     *
     * @param string $class Fully qualified class name (with namespace)
     *
     * @return string|null The class file path or null if not found
     */
    #[Pure]
    public function findClassPath(string $class) : ?string
    {
        $path = $this->getClass($class);
        if ($path) {
            return $path;
        }
        foreach ($this->getNamespaces() as $namespace => $paths) {
            $namespace .= '\\';
            if (\str_starts_with($class, $namespace)) {
                foreach ($paths as $path) {
                    $path .= \strtr(
                        \substr($class, \strlen($namespace)),
                        ['\\' => \DIRECTORY_SEPARATOR]
                    );
                    $path .= '.php';
                    if (\is_file($path)) {
                        return $path;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Loads a class file.
     *
     * @param string $class Fully qualified class name (with namespace)
     *
     * @return bool TRUE if the file is loaded, otherwise FALSE
     */
    public function loadClass(string $class) : bool
    {
        if (isset($this->debugCollector)) {
            return $this->loadDebug($class);
        }
        return $this->loadClassFile($class);
    }

    protected function loadClassFile(string $class) : bool
    {
        $class = $this->findClassPath($class);
        if ($class) {
            // Require $class in an isolated scope - no access to $this
            (static function () use ($class) : void {
                require $class;
            })();
            return true;
        }
        return false;
    }

    protected function loadDebug(string $class) : bool
    {
        $start = \microtime(true);
        $loaded = $this->loadClassFile($class);
        $end = \microtime(true);
        $this->debugCollector->addData([
            'start' => $start,
            'end' => $end,
            'class' => $class,
            'file' => $this->findClassPath($class),
            'loaded' => $loaded,
        ]);
        return $loaded;
    }

    public function setDebugCollector(?AutoloadCollector $debugCollector = null, string $name = 'default') : static
    {
        if ($debugCollector) {
            $this->debugCollector = $debugCollector;
            return $this;
        }
        $data = [];
        foreach ([Collector::class, AutoloadCollector::class] as $class) {
            $start = \microtime(true);
            $loaded = $this->loadClassFile($class);
            $end = \microtime(true);
            $data[] = [
                'start' => $start,
                'end' => $end,
                'class' => $class,
                'file' => $this->findClassPath($class),
                'loaded' => $loaded,
            ];
        }
        $this->debugCollector = new AutoloadCollector($name);
        $this->debugCollector->setAutoloader($this);
        foreach ($data as $item) {
            $this->debugCollector->addData($item);
        }
        return $this;
    }

    public function getDebugCollector() : ?AutoloadCollector
    {
        return $this->debugCollector ?? null;
    }

    /**
     * Renders a class or namespace name without lateral slashes.
     *
     * @param string $name Class or namespace name
     *
     * @return string
     */
    #[Pure]
    protected function renderRealName(string $name) : string
    {
        return \trim($name, '\\');
    }

    /**
     * Renders the canonicalized absolute pathname for a file path.
     *
     * @param string $path File path
     *
     * @return string
     */
    protected function renderFilePath(string $path) : string
    {
        $real = \realpath($path);
        if ($real === false || !\is_file($real)) {
            throw new RuntimeException("Path is not a file: {$path}");
        }
        return $real;
    }

    /**
     * Gets the canonicalized absolute pathname for a directory path.
     *
     * Adds a trailing slash.
     *
     * @param string $path
     *
     * @return string
     */
    protected function renderDirectoryPath(string $path) : string
    {
        $real = \realpath($path);
        if ($real === false || !\is_dir($real)) {
            throw new RuntimeException("Path is not a directory: {$path}");
        }
        return $real . \DIRECTORY_SEPARATOR;
    }
}
