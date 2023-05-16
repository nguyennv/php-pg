<?php declare(strict_types=1);
/**
 * This file is part of the PHP PG library.
 *
 * © Nguyen Van Nguyen <nguyennv1981@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OpenPGP\Packet\Signature;

use OpenPGP\Enum\SignatureSubpacketType;
use OpenPGP\Packet\SignatureSubpacket;

/**
 * KeyExpirationTime sub-packet class
 * Giving time after creation at which the key expires.
 * 
 * @package   OpenPGP
 * @category  Packet
 * @author    Nguyen Van Nguyen - nguyennv1981@gmail.com
 * @copyright Copyright © 2023-present by Nguyen Van Nguyen.
 */
class KeyExpirationTime extends SignatureSubpacket
{
    /**
     * Constructor
     *
     * @param string $data
     * @param bool $critical
     * @param bool $isLong
     * @return self
     */
    public function __construct(
        string $data,
        bool $critical = false,
        bool $isLong = false
    )
    {
        parent::__construct(
            SignatureSubpacketType::KeyExpirationTime->value,
            $data,
            $critical,
            $isLong
        );
    }

    /**
     * From time
     *
     * @param int $seconds
     * @param bool $critical
     * @return self
     */
    public static function fromTime(
        int $seconds, bool $critical = false
    ): self
    {
        return new self(pack('N', $seconds), $critical);
    }

    /**
     * Gets expiration time
     * 
     * @return int
     */
    public function getExpirationTime(): int
    {
        $unpacked = unpack('N', substr($this->getData(), 0, 4));
        return reset($unpacked);
    }
}
