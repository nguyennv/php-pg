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

use DateTime;

/**
 * Signature interface
 * 
 * @package   OpenPGP
 * @category  Type
 * @author    Nguyen Van Nguyen - nguyennv1981@gmail.com
 * @copyright Copyright © 2023-present by Nguyen Van Nguyen.
 */
interface SignatureInterface
{
    /**
     * Returns signature packets
     *
     * @return array
     */
    function getSignaturePackets(): array;

    /**
     * Returns signing key IDs
     *
     * @return array
     */
    function getSigningKeyIDs(): array;

    /**
     * Verify signature with literal data
     * Return verification array
     *
     * @param array $verificationKeys
     * @param LiteralDataPacketInterface $literalData
     * @param DateTime $time
     * @return array
     */
    function verify(
        array $verificationKeys,
        LiteralDataPacketInterface $literalData,
        ?DateTime $time = null
    ): array;

    /**
     * Verify signature with cleartext
     * Return verification array
     *
     * @param array $verificationKeys
     * @param CleartextMessagenterface $cleartext
     * @param DateTime $time
     * @return array
     */
    function verifyCleartext(
        array $verificationKeys,
        CleartextMessagenterface $cleartext,
        ?DateTime $time = null
    ): array;
}
