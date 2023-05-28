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
use OpenPGP\Enum\{
    CurveOid,
    DHKeySize,
    KeyAlgorithm,
    RSAKeySize,
};

/**
 * Private key interface
 * 
 * @package   OpenPGP
 * @category  Type
 * @author    Nguyen Van Nguyen - nguyennv1981@gmail.com
 * @copyright Copyright © 2023-present by Nguyen Van Nguyen.
 */
interface PrivateKeyInterface extends KeyInterface
{
    /**
     * Returns true if the key packet is encrypted.
     * 
     * @return bool
     */
    function isEncrypted(): bool;

    /**
     * Returns true if the key packet is decrypted.
     * 
     * @return bool
     */
    function isDecrypted(): bool;

    /**
     * Returns array of key packets that is available for decryption
     * 
     * @param DateTime $time
     * @return array<SecretKeyPacketInterface>
     */
    function getDecryptionKeyPackets(?DateTime $time = null): array;

    /**
     * Lock a private key with the given passphrase.
     * This method does not change the original key.
     * 
     * @param string $passphrase
     * @param array<string> $subkeyPassphrases
     * @return self
     */
    function encrypt(
        string $passphrase,
        array $subkeyPassphrases = []
    ): self;

    /**
     * Unlock a private key with the given passphrase.
     * This method does not change the original key.
     * 
     * @param string $passphrase
     * @param array<string> $subkeyPassphrases
     * @return self
     */
    function decrypt(
        string $passphrase, array $subkeyPassphrases = []
    ): self;

    /**
     * Add userIDs to the key,
     * and returns a clone of the key object with the new userIDs added.
     * 
     * @param array<string> $userIDs
     * @return self
     */
    function addUsers(array $userIDs): self;

    /**
     * Generates a new OpenPGP subkey,
     * and returns a clone of the key object with the new subkey added.
     * 
     * @param string $passphrase
     * @param KeyAlgorithm $keyAlgorithm
     * @param RSAKeySize $rsaKeySize
     * @param DHKeySize $dhKeySize
     * @param CurveOid $curve
     * @param int $keyExpiry
     * @param bool $subkeySign
     * @param DateTime $time
     * @return self
     */
    function addSubkey(
        string $passphrase,
        KeyAlgorithm $keyAlgorithm = KeyAlgorithm::RsaEncryptSign,
        RSAKeySize $rsaKeySize = RSAKeySize::S4096,
        DHKeySize $dhKeySize = DHKeySize::L2048_N224,
        CurveOid $curve = CurveOid::Secp521r1,
        int $keyExpiry = 0,
        bool $subkeySign = false,
        ?DateTime $time = null
    ): self;
}