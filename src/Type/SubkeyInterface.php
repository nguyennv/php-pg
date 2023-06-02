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
use OpenPGP\Enum\KeyAlgorithm;

/**
 * Subkey interface
 * 
 * @package   OpenPGP
 * @category  Type
 * @author    Nguyen Van Nguyen - nguyennv1981@gmail.com
 * @copyright Copyright © 2023-present by Nguyen Van Nguyen.
 */
interface SubkeyInterface
{
    /**
     * Get key packet
     *
     * @return SubkeyPacketInterface
     */
    function getKeyPacket(): SubkeyPacketInterface;

    /**
     * Get the expiration time of the subkey or null if subkey does not expire.
     * 
     * @return DateTime
     */
    function getExpirationTime(): ?DateTime;

    /**
     * Get creation time
     * 
     * @return DateTime
     */
    function getCreationTime(): DateTime;

    /**
     * Get key algorithm
     * 
     * @return KeyAlgorithm
     */
    function getKeyAlgorithm(): KeyAlgorithm;

    /**
     * Get fingerprint
     * 
     * @param bool $toHex
     * @return string
     */
    function getFingerprint(bool $toHex = false): string;

    /**
     * Get key ID
     * 
     * @param bool $toHex
     * @return string
     */
    function getKeyID(bool $toHex = false): string;

    /**
     * Get key strength
     * 
     * @return int
     */
    function getKeyStrength(): int;

    /**
     * Return subkey is signing or verification key
     * 
     * @return bool
     */
    function isSigningKey(): bool;

    /**
     * Return subkey is encryption or decryption key
     * 
     * @return bool
     */
    function isEncryptionKey(): bool;

    /**
     * Check if a binding signature of a subkey is revoked
     * 
     * @param KeyInterface $verifyKey
     * @param SignaturePacketInterface $certificate
     * @param DateTime $time
     * @return bool
     */
    function isRevoked(
        ?KeyInterface $verifyKey = null,
        ?SignaturePacketInterface $certificate = null,
        ?DateTime $time = null
    ): bool;

    /**
     * Verify subkey.
     * Checks for revocation signatures, expiration time and valid binding signature.
     * 
     * @param DateTime $time
     * @return bool
     */
    function verify(?DateTime $time = null): bool;

    /**
     * Revoke the subkey
     * 
     * @param PrivateKeyInterface $signKey
     * @param string $revocationReason
     * @param DateTime $time
     * @return self
     */
    function revokeBy(
        PrivateKeyInterface $signKey,
        string $revocationReason = '',
        ?DateTime $time = null
    ): self;
}
