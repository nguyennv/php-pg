<?php declare(strict_types=1);
/**
 * This file is part of the PHP Privacy project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OpenPGP\Packet;

use DateTimeInterface;
use OpenPGP\Common\{
    Argon2S2K,
    Config,
    GenericS2K,
    Helper,
};
use OpenPGP\Enum\{
    AeadAlgorithm,
    CurveOid,
    EdDSACurve,
    HashAlgorithm,
    KeyAlgorithm,
    MontgomeryCurve,
    PacketTag,
    RSAKeySize,
    S2kType,
    S2kUsage,
    SymmetricAlgorithm,
};
use OpenPGP\Type\{
    ECKeyMaterialInterface,
    KeyMaterialInterface,
    PublicKeyPacketInterface,
    S2KInterface,
    SecretKeyPacketInterface,
    SubkeyPacketInterface,
};
use phpseclib3\Crypt\Random;

/**
 * Implementation a possibly encrypted private key (Tag 5).
 *
 * See RFC 9580, section 5.5.3.
 *
 * @package  OpenPGP
 * @category Packet
 * @author   Nguyen Van Nguyen - nguyennv1981@gmail.com
 */
class SecretKey extends AbstractPacket implements SecretKeyPacketInterface
{
    const HASH_ALGO = 'sha1';
    const ZERO_CHAR = "\x00";

    /**
     * Constructor
     *
     * @param PublicKey $publicKey
     * @param S2kUsage $s2kUsage
     * @param string $keyData
     * @param KeyMaterialInterface $keyMaterial
     * @param SymmetricAlgorithm $symmetric
     * @param S2KInterface $s2k
     * @param string $iv
     * @return self
     */
    public function __construct(
        private readonly PublicKey $publicKey,
        private readonly string $keyData = '',
        private readonly ?KeyMaterialInterface $keyMaterial = null,
        private readonly S2kUsage $s2kUsage = S2kUsage::None,
        private readonly SymmetricAlgorithm $symmetric = SymmetricAlgorithm::Plaintext,
        private readonly ?S2KInterface $s2k = null,
        private readonly ?AeadAlgorithm $aead = null,
        private readonly string $iv = '',
    )
    {
        parent::__construct(
            $this instanceof SubkeyPacketInterface ?
            PacketTag::SecretSubkey : PacketTag::SecretKey
        );

        if ($publicKey->getVersion() === PublicKey::VERSION_6 &&
           ($s2kUsage === S2kUsage::MalleableCfb))
        {
            throw new \InvalidArgumentException(
                "S2k usage {$s2kUsage->name} cannot be used with v{$publicKey->getVersion()} key packet.",
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function fromBytes(string $bytes): self
    {
        $publicKey = PublicKey::fromBytes($bytes);
        $offset = strlen($publicKey->toBytes());
        $isV6 = $publicKey->getVersion() === PublicKey::VERSION_6;
        $s2kUsage = S2kUsage::from(ord($bytes[$offset++]));

        // Only for a version 6 packet where the secret key material encrypted
        if ($isV6 && $s2kUsage !== S2kUsage::None) {
            $offset++;
        }

        $s2k = null;
        $aead = null;
        switch ($s2kUsage) {
            case S2kUsage::MalleableCfb:
            case S2kUsage::Cfb:
            case S2kUsage::AeadProtect:
                // one-octet symmetric encryption algorithm.
                $symmetric = SymmetricAlgorithm::from(
                    ord($bytes[$offset++])
                );

                // If s2k usage octet was 253, a one-octet AEAD algorithm.
                if ($s2kUsage === S2kUsage::AeadProtect) {
                    $aead = AeadAlgorithm::from(ord($bytes[$offset++]));
                }

                // Only for a version 6 packet, and if string-to-key usage
                // octet was 253 or 254, an one-octet count of the following field.
                if ($isV6 && ($s2kUsage === S2kUsage::AeadProtect || $s2kUsage === S2kUsage::Cfb)) {
                    $offset++;
                }

                $s2kType = S2kType::from(ord($bytes[$offset]));
                $s2k = ($s2kType === S2kType::Argon2) ?
                    Argon2S2K::fromBytes(substr($bytes, $offset)) : 
                    GenericS2K::fromBytes(substr($bytes, $offset));
                $offset += $s2kType->dataLength();
                break;
            default:
                $symmetric = SymmetricAlgorithm::Plaintext;
                break;
        }

        $iv = '';
        if ($aead instanceof AeadAlgorithm) {
            $iv = substr($bytes, $offset, $aead->ivLength());
        }
        else {
            $iv = substr($bytes, $offset, $symmetric->blockSize());
        }
        $offset += strlen($iv);

        $keyMaterial = null;
        $keyData = substr($bytes, $offset);
        if ($s2kUsage === S2kUsage::None) {
            if (!$isV6) {
                $checksum = substr($keyData, strlen($keyData) - 2);
                $keyData = substr($keyData, 0, strlen($keyData) - 2);
                if (strcmp(Helper::computeChecksum($keyData), $checksum) !== 0) {
                    throw new \RuntimeException('Key checksum mismatch!');
                }
            }
            $keyMaterial = self::readKeyMaterial($keyData, $publicKey);
        }

        return new self(
            $publicKey,
            $keyData,
            $keyMaterial,
            $s2kUsage,
            $symmetric,
            $s2k,
            $aead,
            $iv,
        );
    }

    /**
     * Generate secret key packet
     *
     * @param KeyAlgorithm $keyAlgorithm
     * @param RSAKeySize $rsaKeySize
     * @param CurveOid $curveOid
     * @param DateTimeInterface $time
     * @return self
     */
    public static function generate(
        KeyAlgorithm $keyAlgorithm = KeyAlgorithm::RsaEncryptSign,
        RSAKeySize $rsaKeySize = RSAKeySize::Normal,
        CurveOid $curveOid = CurveOid::Secp521r1,
        ?DateTimeInterface $time = null,
    ): self
    {
        $keyMaterial = match($keyAlgorithm) {
            KeyAlgorithm::RsaEncryptSign,
            KeyAlgorithm::RsaEncrypt,
            KeyAlgorithm::RsaSign
                => Key\RSASecretKeyMaterial::generate($rsaKeySize),
            KeyAlgorithm::Ecdh
                => Key\ECDHSecretKeyMaterial::generate($curveOid),
            KeyAlgorithm::EcDsa
                => Key\ECDSASecretKeyMaterial::generate($curveOid),
            KeyAlgorithm::EdDsaLegacy
                => Key\EdDSALegacySecretKeyMaterial::generate(),
            KeyAlgorithm::X25519
                => Key\MontgomerySecretKeyMaterial::generate(
                    MontgomeryCurve::Curve25519
                ),
            KeyAlgorithm::X448
                => Key\MontgomerySecretKeyMaterial::generate(
                    MontgomeryCurve::Curve448
                ),
            KeyAlgorithm::Ed25519
                => Key\EdDSASecretKeyMaterial::generate(EdDSACurve::Ed25519),
            KeyAlgorithm::Ed448
                => Key\EdDSASecretKeyMaterial::generate(EdDSACurve::Ed448),
            default => throw new \RuntimeException(
                "Key algorithm {$keyAlgorithm->name} is unsupported."
            ),
        };
        switch ($keyAlgorithm) {
            case KeyAlgorithm::X25519:
            case KeyAlgorithm::X448:
            case KeyAlgorithm::Ed25519:
            case KeyAlgorithm::Ed448:
                $version = PublicKey::VERSION_6;
                break;
            default:
                $version = Config::useV6Key() ?
                    PublicKey::VERSION_6 : PublicKey::VERSION_4;
                break;
        }
        return new self(
            new PublicKey(
                $version,
                $time ?? new \DateTime(),
                $keyAlgorithm,
                $keyMaterial->getPublicMaterial(),
            ),
            $keyMaterial->toBytes(),
            $keyMaterial,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function toBytes(): string
    {
        $isV6 = $this->getVersion() === PublicKey::VERSION_6;
        if ($this->isEncrypted()) {
            $optBytes = implode([
                chr($this->symmetric->value),
                !empty($this->aead) ? chr($this->aead->value) : '',
                $isV6 ? chr($this->s2k->getLength()) : '',
                $this->s2k->toBytes(),
                $this->iv,
            ]);
            return implode([
                $this->publicKey->toBytes(),
                chr($this->s2kUsage->value),
                $isV6 ? chr(strlen($optBytes)) : '',
                $optBytes,
                $this->keyData,
            ]);
        }
        else {
            return implode([
                $this->publicKey->toBytes(),
                chr(S2kUsage::None->value),
                $this->keyData,
                $isV6 ? '' : Helper::computeChecksum($this->keyData),
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVersion(): int
    {
        return $this->publicKey->getVersion();
    }

    /**
     * {@inheritdoc}
     */
    public function getCreationTime(): DateTimeInterface
    {
        return $this->publicKey->getCreationTime();
    }

    /**
     * {@inheritdoc}
     */
    public function getKeyAlgorithm(): KeyAlgorithm
    {
        return $this->publicKey->getKeyAlgorithm();
    }

    /**
     * {@inheritdoc}
     */
    public function isSigningKey(): bool
    {
        return $this->publicKey->isSigningKey();
    }

    /**
     * {@inheritdoc}
     */
    public function isEncryptionKey(): bool
    {
        return $this->publicKey->isEncryptionKey();
    }

    /**
     * {@inheritdoc}
     */
    public function getKeyMaterial(): ?KeyMaterialInterface
    {
        return $this->keyMaterial;
    }

    /**
     * {@inheritdoc}
     */
    public function getECKeyMaterial(): ?ECKeyMaterialInterface
    {
        return $this->keyMaterial instanceof ECKeyMaterialInterface ?
            $this->keyMaterial : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getFingerprint(bool $toHex = false): string
    {
        return $this->publicKey->getFingerprint($toHex);
    }

    /**
     * {@inheritdoc}
     */
    public function getKeyID(bool $toHex = false): string
    {
        return $this->publicKey->getKeyID($toHex);
    }

    /**
     * {@inheritdoc}
     */
    public function getKeyStrength(): int
    {
        return $this->publicKey->getKeyStrength();
    }

    /**
     * {@inheritdoc}
     */
    public function isSubkey(): bool
    {
        return $this instanceof SubkeyPacketInterface;
    }

    /**
     * {@inheritdoc}
     */
    public function getPreferredHash(
        ?HashAlgorithm $preferredHash = null
    ): HashAlgorithm
    {
        return $this->publicKey->getPreferredHash($preferredHash);
    }

    /**
     * {@inheritdoc}
     */
    public function getSignBytes(): string
    {
        return $this->publicKey->getSignBytes();
    }

    /**
     * {@inheritdoc}
     */
    public function getPublicKey(): PublicKeyPacketInterface
    {
        return $this->publicKey;
    }

    /**
     * {@inheritdoc}
     */
    public function isEncrypted(): bool
    {
        return ($this->s2k instanceof S2KInterface) &&
               ($this->s2kUsage !== S2kUsage::None);
    }

    /**
     * {@inheritdoc}
     */
    public function isDecrypted(): bool
    {
        return $this->keyMaterial instanceof KeyMaterialInterface;
    }

    /**
     * {@inheritdoc}
     */
    public function encrypt(
        string $passphrase,
        SymmetricAlgorithm $symmetric = SymmetricAlgorithm::Aes128,
        ?AeadAlgorithm $aead = null,
    ): self
    {
        if ($this->isDecrypted()) {
            $this->getLogger()->debug(
                'Encrypt secret key material with passphrase.'
            );
            Helper::assertSymmetric($symmetric);

            $aeadProtect = $aead instanceof AeadAlgorithm;
            if ($aeadProtect && $this->getVersion() !== PublicKey::VERSION_6) {
                throw new \InvalidArgumentException(
                    "Using AEAD with version {$this->getVersion()} of the key packet is not allowed."
                );
            }

            $s2k = $aeadProtect && Argon2S2K::argon2Supported() ?
                Helper::stringToKey(S2kType::Argon2) :
                Helper::stringToKey(S2kType::Iterated);

            $iv = $aeadProtect ?
                Random::string($aead->ivLength()) :
                Random::string($symmetric->blockSize());

            $packetTag = chr(0xc0 | $this->getTag()->value);
            $kek = self::produceEncryptionKey(
                $passphrase,
                $symmetric,
                $s2k,
                $aead,
                $packetTag,
            );
            $clearText = $this->keyMaterial?->toBytes() ?? '';

            if ($aeadProtect) {
                $cipher = $aead->cipherEngine($kek, $symmetric);
                $encrypted = $cipher->encrypt(
                    $clearText,
                    $iv,
                    implode([
                        $packetTag,
                        $this->publicKey->toBytes(),
                    ]),
                );
            }
            else {
                $cipher = $symmetric->cipherEngine(S2kUsage::Cfb->name);
                $cipher->setIV($iv);
                $cipher->setKey($kek);

                $encrypted = $cipher->encrypt(implode([
                    $clearText,
                    hash(self::HASH_ALGO, $clearText, true),
                ]));
            }
            return new self(
                $this->publicKey,
                $encrypted,
                $this->keyMaterial,
                $aeadProtect ? S2kUsage::AeadProtect : S2kUsage::Cfb,
                $symmetric,
                $s2k,
                $aead,
                $iv,
            );
        }
        else {
            return $this;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function decrypt(string $passphrase): self
    {
        if ($this->isDecrypted() || !$this->isEncrypted()) {
            return $this;
        }
        else {
            $this->getLogger()->debug(
                'Decrypt secret key material with passphrase.'
            );

            $clearText = '';
            $packetTag = chr(0xc0 | $this->getTag()->value);
            $kek = self::produceEncryptionKey(
                $passphrase,
                $this->symmetric,
                $this->s2k,
                $this->aead,
                $packetTag,
            );

            if ($this->aead instanceof AeadAlgorithm) {
                $cipher = $this->aead->cipherEngine($kek, $this->symmetric);
                $clearText = $cipher->decrypt(
                    $this->keyData,
                    $this->iv,
                    implode([
                        $packetTag,
                        $this->publicKey->toBytes(),
                    ]),
                );
            }
            else {
                $cipher = $this->symmetric->cipherEngine(S2kUsage::Cfb->name);
                $cipher->setIV($this->iv);
                $cipher->setKey($kek);
                $decrypted = $cipher->decrypt($this->keyData);
                $length = strlen($decrypted) - HashAlgorithm::Sha1->digestSize();
                $clearText = substr($decrypted, 0, $length);
                $hashText = substr($decrypted, $length);
                $hashed = hash(self::HASH_ALGO, $clearText, true);
                if (strcmp($hashed, $hashText) !== 0) {
                    throw new \RuntimeException(
                        'Incorrect key passphrase.'
                    );
                }
            }

            $keyMaterial = self::readKeyMaterial(
                $clearText, $this->publicKey
            );

            return new self(
                $this->publicKey,
                $this->keyData,
                $keyMaterial,
                $this->s2kUsage,
                $this->symmetric,
                $this->s2k,
                $this->aead,
                $this->iv,
            );
        }
    }

    /**
     * Get S2k usage
     *
     * @return S2kUsage
     */
    public function getS2kUsage(): S2kUsage
    {
        return $this->s2kUsage;
    }

    /**
     * Get symmetric algorithm
     *
     * @return SymmetricAlgorithm
     */
    public function getSymmetric(): SymmetricAlgorithm
    {
        return $this->symmetric;
    }

    /**
     * Get string 2 key
     *
     * @return S2KInterface
     */
    public function getS2K(): ?S2KInterface
    {
        return $this->s2k;
    }

    /**
     * {@inheritdoc}
     */
    public function getAead(): ?AeadAlgorithm
    {
        return $this->aead;
    }

    /**
     * Get initialization vector
     *
     * @return string
     */
    public function getIV(): string
    {
        return $this->iv;
    }

    /**
     * Get key data
     *
     * @return string
     */
    public function getKeyData(): string
    {
        return $this->keyData;
    }

    /**
     * Derive encryption key
     *
     * @param string $passphrase
     * @param SymmetricAlgorithm $symmetric
     * @param S2KInterface $s2k
     * @param AeadAlgorithm $aead
     * @param string $packetTag
     * @return string
     */
    private static function produceEncryptionKey(
        string $passphrase,
        SymmetricAlgorithm $symmetric,
        ?S2KInterface $s2k = null,
        ?AeadAlgorithm $aead = null,
        string $packetTag = '',
    ): string
    {
        if ($s2k?->getType() === S2kType::Argon2 && empty($aead)) {
            throw new \InvalidArgumentException(
                'Using Argon2 S2K without AEAD is not allowed.'
            );
        }
        $derivedKey = $s2k?->produceKey(
            $passphrase, $symmetric->keySizeInByte()
        ) ?? str_repeat(self::ZERO_CHAR, $symmetric->keySizeInByte());
        if ($aead instanceof AeadAlgorithm) {
            return hash_hkdf(
                Config::HKDF_ALGO,
                $derivedKey,
                $symmetric->keySizeInByte(),
                implode([
                    $packetTag,
                    chr(PublicKey::VERSION_6),
                    chr($symmetric->value),
                    chr($aead->value),
                ]),
            );
        }
        return $derivedKey;
    }

    private static function readKeyMaterial(
        string $bytes, PublicKey $publicKey
    ): KeyMaterialInterface
    {
        $keyMaterial = match($publicKey->getKeyAlgorithm()) {
            KeyAlgorithm::RsaEncryptSign,
            KeyAlgorithm::RsaEncrypt,
            KeyAlgorithm::RsaSign
                => Key\RSASecretKeyMaterial::fromBytes(
                    $bytes, $publicKey->getKeyMaterial()
                ),
            KeyAlgorithm::ElGamal => Key\ElGamalSecretKeyMaterial::fromBytes(
                $bytes, $publicKey->getKeyMaterial()
            ),
            KeyAlgorithm::Dsa => Key\DSASecretKeyMaterial::fromBytes(
                $bytes, $publicKey->getKeyMaterial()
            ),
            KeyAlgorithm::Ecdh => Key\ECDHSecretKeyMaterial::fromBytes(
                $bytes, $publicKey->getKeyMaterial()
            ),
            KeyAlgorithm::EcDsa => Key\ECDSASecretKeyMaterial::fromBytes(
                $bytes, $publicKey->getKeyMaterial()
            ),
            KeyAlgorithm::EdDsaLegacy => Key\EdDSALegacySecretKeyMaterial::fromBytes(
                $bytes, $publicKey->getKeyMaterial()
            ),
            KeyAlgorithm::X25519
                => Key\MontgomerySecretKeyMaterial::fromBytes(
                    $bytes, $publicKey->getKeyMaterial(), MontgomeryCurve::Curve25519
                ),
            KeyAlgorithm::X448
                => Key\MontgomerySecretKeyMaterial::fromBytes(
                    $bytes, $publicKey->getKeyMaterial(), MontgomeryCurve::Curve448
                ),
            KeyAlgorithm::Ed25519
                => Key\EdDSASecretKeyMaterial::fromBytes(
                    $bytes, $publicKey->getKeyMaterial(), EdDSACurve::Ed25519
                ),
            KeyAlgorithm::Ed448
                => Key\EdDSASecretKeyMaterial::fromBytes(
                    $bytes, $publicKey->getKeyMaterial(), EdDSACurve::Ed448
                ),
            default => throw new \RuntimeException(
                "Key algorithm {$publicKey->getKeyAlgorithm()->name} is unsupported.",
            ),
        };
        if (!$keyMaterial->isValid()) {
            throw new \RuntimeException(
                'The key material is not consistent.'
            );
        }
        return $keyMaterial;
    }
}
