<?php declare(strict_types=1);
/*
 * This file is part of Webisters Autoload Library.
 *
 * (c) Hafiz Muhammad Moaz <thewebisters@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Tests\Autoload\Preload;

/**
 * A class with no optional dependency, used to prove a tolerant preload keeps
 * loading the remaining files.
 *
 * @package autoload
 */
class PlainClass
{
}
