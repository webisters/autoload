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

use JetBrains\PhpStorm\Pure;

/**
 * Class Locator.
 *
 * The Locator class has methods for finding files and the class FQN using an
 * Autoloader instance.
 *
 * @package autoload
 */
class Locator
{
    protected Autoloader $autoloader;
    protected bool $classNameCache = false;
    /**
     * Parsed class names, keyed by file path and modification time.
     *
     * @var array<string,string|null>
     */
    protected array $classNames = [];

    /**
     * Locator constructor.
     *
     * @param Autoloader $autoloader
     */
    public function __construct(Autoloader $autoloader)
    {
        $this->autoloader = $autoloader;
    }

    /**
     * Gets the first Qualified Class Name in a given filename.
     *
     * The "class name" can be the name of a class, an interface, a trait or an
     * enum. Anonymous classes are skipped, since they have no name to return,
     * and so is the ::class constant, which is not a declaration.
     *
     * Results are tokenized on every call unless the in-memory cache is
     * enabled with withClassNameCache().
     *
     * @param string $filename
     *
     * @see Locator::withClassNameCache()
     * @see https://www.php.net/manual/en/language.namespaces.rules.php
     * @see https://www.php.net/manual/en/language.oop5.interfaces.php
     *
     * @return string|null The class name or null if not found
     */
    public function getClassName(string $filename) : ?string
    {
        if (!\is_file($filename)) {
            return null;
        }
        if (!$this->classNameCache) {
            return $this->parseClassName($filename);
        }
        $key = $filename . ':' . (string) \filemtime($filename);
        if (!\array_key_exists($key, $this->classNames)) {
            $this->classNames[$key] = $this->parseClassName($filename);
        }
        return $this->classNames[$key];
    }

    /**
     * Enable or disable the in-memory class name cache.
     *
     * The cache is off by default. Turn it on for tools that call
     * getClassName() over many files, such as the Preloader or a classmap
     * generator, where the same file is tokenized more than once. Entries are
     * keyed by file path and modification time, so editing a file invalidates
     * its entry on the next call.
     *
     * @param bool $enabled
     *
     * @return static
     */
    public function withClassNameCache(bool $enabled = true) : static
    {
        $this->classNameCache = $enabled;
        if (!$enabled) {
            $this->classNames = [];
        }
        return $this;
    }

    /**
     * Tell whether the in-memory class name cache is enabled.
     *
     * @return bool
     */
    public function isCachingClassNames() : bool
    {
        return $this->classNameCache;
    }

    /**
     * Discard every cached class name, keeping the cache enabled.
     *
     * @return static
     */
    public function clearClassNameCache() : static
    {
        $this->classNames = [];
        return $this;
    }

    /**
     * Tokenizes a file and returns the first declared name.
     *
     * @param string $filename
     *
     * @return string|null The class name or null if not found
     */
    protected function parseClassName(string $filename) : ?string
    {
        $tokens = \token_get_all((string) \file_get_contents($filename));
        $last = \count($tokens);
        $namespace = '';
        foreach ($tokens as $current => $token) {
            if (!\is_array($token)) {
                continue;
            }
            if ($token[0] === \T_NAMESPACE) {
                for ($next = $current + 1; $next < $last; $next++) {
                    if ($tokens[$next][0] === \T_STRING
                        || $tokens[$next][0] === \T_NAME_QUALIFIED
                    ) {
                        $namespace .= '\\' . $tokens[$next][1];
                    } elseif ($tokens[$next] === '{' || $tokens[$next] === ';') {
                        break;
                    }
                }
                continue;
            }
            if (!\in_array($token[0], [
                \T_CLASS,
                \T_ENUM,
                \T_INTERFACE,
                \T_TRAIT,
            ], true)) {
                continue;
            }
            // The ::class constant also yields a T_CLASS token.
            if ($this->getPreviousTokenId($tokens, $current) === \T_DOUBLE_COLON) {
                continue;
            }
            // An anonymous class is followed by '{', '(' or extends/implements
            // rather than by its name, so there is nothing to return for it.
            $name = $this->getDeclarationName($tokens, $current, $last);
            if ($name === null) {
                continue;
            }
            return \ltrim($namespace . '\\' . $name, '\\');
        }
        return null;
    }

    /**
     * Gets the id of the meaningful token before a given position, skipping
     * whitespace and comments.
     *
     * @param array<int,array<int,int|string>|string> $tokens
     * @param int $current
     *
     * @return int|null The token id or null if there is none or it has no id
     */
    protected function getPreviousTokenId(array $tokens, int $current) : ?int
    {
        for ($previous = $current - 1; $previous >= 0; $previous--) {
            $token = $tokens[$previous];
            if (!\is_array($token)) {
                return null;
            }
            if (\in_array($token[0], [
                \T_WHITESPACE,
                \T_COMMENT,
                \T_DOC_COMMENT,
            ], true)) {
                continue;
            }
            return (int) $token[0];
        }
        return null;
    }

    /**
     * Gets the name declared after a class, interface, trait or enum keyword.
     *
     * @param array<int,array<int,int|string>|string> $tokens
     * @param int $current Position of the keyword
     * @param int $last Total number of tokens
     *
     * @return string|null The declared name or null when the declaration is
     * anonymous or malformed
     */
    protected function getDeclarationName(array $tokens, int $current, int $last) : ?string
    {
        for ($next = $current + 1; $next < $last; $next++) {
            $token = $tokens[$next];
            if (!\is_array($token)) {
                return null;
            }
            if (\in_array($token[0], [
                \T_WHITESPACE,
                \T_COMMENT,
                \T_DOC_COMMENT,
            ], true)) {
                continue;
            }
            return $token[0] === \T_STRING ? (string) $token[1] : null;
        }
        return null;
    }

    /**
     * Get the first filepath found in all namespaces.
     *
     * @param string $file The file name without extension
     * @param string $extension The file extension
     *
     * @return string|null The filepath or null if not found
     */
    public function getNamespacedFilepath(string $file, string $extension = '.php') : ?string
    {
        if ($extension) {
            $file = $this->ensureExtension($file, $extension);
        }
        $file = \strtr(\ltrim($file, '/'), ['\\' => '/']);
        $segments = \explode('/', $file);
        $count = \count($segments) - 1;
        $file = $segments[$count];
        unset($segments[$count]);
        $namespaces = $this->autoloader->getNamespaces();
        $namespace = '';
        while ($segments) {
            $namespace .= $namespace === ''
                ? \array_shift($segments)
                : '\\' . \array_shift($segments);
            if (!isset($namespaces[$namespace])) {
                continue;
            }
            foreach ($namespaces[$namespace] as $directory) {
                $filepath = \rtrim(
                    $directory . \implode(\DIRECTORY_SEPARATOR, $segments),
                    \DIRECTORY_SEPARATOR
                ) . \DIRECTORY_SEPARATOR . $file;
                if (\is_file($filepath)) {
                    return $filepath;
                }
            }
            break;
        }
        return \is_file($file) ? $file : null;
    }

    #[Pure]
    protected function ensureExtension(string $filename, string $extension) : string
    {
        if (!\str_ends_with($filename, $extension)) {
            $filename .= $extension;
        }
        return $filename;
    }

    /**
     * Find namesake files inside namespaced directories.
     *
     * @param string $filename The file name
     * @param string $extension The file extension
     *
     * @return array<int,string> An array of filenames found
     */
    #[Pure]
    public function findFiles(string $filename, string $extension = '.php') : array
    {
        if ($extension) {
            $filename = $this->ensureExtension($filename, $extension);
        }
        $files = [];
        foreach ($this->autoloader->getNamespaces() as $directories) {
            foreach ($directories as $directory) {
                if (\is_file($directory .= $filename)) {
                    $files[] = $directory;
                }
            }
        }
        return $files;
    }

    /**
     * Get a list of all files inside namespaced sub directories.
     *
     * @param string $subDirectory Sub directory path
     *
     * @return array<int,string>
     */
    public function getFiles(string $subDirectory) : array
    {
        $namespacedFiles = [];
        foreach ($this->autoloader->getNamespaces() as $directories) {
            foreach ($directories as $directory) {
                $files = $this->listFiles($directory . $subDirectory);
                if ($files) {
                    $namespacedFiles[] = $files;
                }
            }
        }
        return $namespacedFiles ? \array_merge(...$namespacedFiles) : [];
    }

    /**
     * Get a list of all files inside a directory.
     *
     * @param string $directory Absolute directory path
     *
     * @return array<int,string>|null Returns an array of filenames or null
     * if the directory can not be resolved
     */
    public function listFiles(string $directory) : ?array
    {
        $directory = \realpath($directory);
        if ($directory === false) {
            return null;
        }
        $directory .= \DIRECTORY_SEPARATOR;
        $files = [];
        foreach ((array) \scandir($directory, 0) as $filename) {
            if ($filename === '.' || $filename === '..') {
                continue;
            }
            $filename = $directory . $filename;
            if (\is_file($filename)) {
                $files[] = $filename;
                continue;
            }
            foreach ($this->listFiles($filename) as $subDirectory) {
                $files[] = $subDirectory;
            }
        }
        return $files;
    }
}
