<?php declare(strict_types=1);
/**
 * This file is part of the PHP PG library.
 *
 * © Nguyen Van Nguyen <nguyennv1981@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OpenPGP\Type;

use OpenPGP\Enum\PacketTag;

/**
 * Packet list interface
 * 
 * @package   OpenPGP
 * @category  Type
 * @author    Nguyen Van Nguyen - nguyennv1981@gmail.com
 * @copyright Copyright © 2023-present by Nguyen Van Nguyen.
 */
interface PacketListInterface extends \IteratorAggregate, \Countable
{
    /**
     * Serializes packets to bytes
     * 
     * @return string
     */
    function encode(): string;

    /**
     * Filter packets by tag
     * 
     * @param PacketTag $tag
     * @return self
     */
    function filterByTag(PacketTag $tag): self;

    /**
     * Get array packets
     * 
     * @return array
     */
    function toArray(): array;

    /**
     * Return current array packet
     * 
     * @return PacketInterface
     */
    function current(): PacketInterface;

    /**
     * Gets packet for an offset
     * 
     * @return PacketInterface
     */
    function offsetGet($key): PacketInterface;
}