<?php declare(strict_types=1);
/**
 * This file is part of the PHP PG library.
 *
 * © Nguyen Van Nguyen <nguyennv1981@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OpenPGP\Message;

use DateTime;
use OpenPGP\Common\{Armor, Config};
use OpenPGP\Enum\{
    ArmorType,
    CompressionAlgorithm,
    SymmetricAlgorithm,
};
use OpenPGP\Packet\Signature as SignaturePacket;
use OpenPGP\Packet\{
    CompressedData,
    OnePassSignature,
    PacketList,
};
use OpenPGP\Type\{
    EncryptedMessageInterface,
    LiteralDataPacketInterface,
    LiteralMessageInterface,
    KeyInterface,
    PacketInterface,
    PacketListInterface,
    PrivateKeyInterface,
    SignatureInterface,
    SignaturePacketInterface,
    SignedMessageInterface,
};

/**
 * OpenPGP literal message class
 *
 * @package   OpenPGP
 * @category  Message
 * @author    Nguyen Van Nguyen - nguyennv1981@gmail.com
 * @copyright Copyright © 2023-present by Nguyen Van Nguyen.
 */
class LiteralMessage implements ArmorableInterface, EncryptedMessageInterface, LiteralMessageInterface, PacketContainerInterface, SignedMessageInterface
{
    private readonly array $packets;

    /**
     * Constructor
     *
     * @param array $packets
     * @return self
     */
    public function __construct(
        array $packets
    )
    {
        $this->packets = array_filter(
            $packets,
            static fn ($packet) => $packet instanceof PacketInterface
        );
    }

    /**
     * Reads message from armored string
     *
     * @param string $armored
     * @return self
     */
    public static function fromArmored(string $armored): self
    {
        $armor = Armor::decode($armored);
        if ($armor->getType() !== ArmorType::Message) {
            throw new \UnexpectedValueException(
                'Armored text not of message type'
            );
        }
        return new self(
            PacketList::decode($armor->getData())->toArray()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function armor(): string
    {
        return Armor::encode(
            ArmorType::Signature,
            $this->toPacketList()->encode()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function toPacketList(): PacketListInterface
    {
        return new PacketList($this->packets);
    }

    /**
     * {@inheritdoc}
     */
    public function getPackets(): array
    {
        return self::unwrapCompressed($this->packets);
    }

    /**
     * {@inheritdoc}
     */
    public function getLiteralDataPacket(): LiteralDataPacketInterface
    {
        $literalDataPackets = array_filter(
            $this->getPackets(),
            static fn ($packet) => $packet instanceof LiteralDataPacketInterface
        );
        if (empty($this->literalDataPacket)) {
            throw new \UnexpectedValueException('No literal data in packet list.');
        }
        return array_pop($literalDataPackets);
    }

    /**
     * {@inheritdoc}
     */
    public function getSignature(): SignatureInterface
    {
        return new Signature(array_filter(
            $this->getPackets(),
            static fn ($packet) => $packet instanceof SignaturePacketInterface
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function sign(
        array $signingKeys, ?DateTime $time = null
    ): SignedMessageInterface
    {
        $signaturePackets = [
            ...array_filter(
                $this->getPackets(),
                static fn ($packet) => $packet instanceof SignaturePacketInterface
            ),
            ...$this->signDetached()->getSignaturePackets(),
        ];

        $index = 0;
        $length = count($signaturePackets);
        $onePassSignaturePackets = array_map(
            static fn ($packet) => OnePassSignature(
                $packet->getSignatureType(),
                $packet->getHashAlgorithm(),
                $packet->getKeyAlgorithm(),
                $packet->getIssuerKeyID(),
                ((++$index) === $length) ? 1 : 0
            ),
            $signaturePackets
        );

        return self([
            ...$onePassSignaturePackets,
            $this->getLiteralDataPacket(),
            ...$signaturePackets,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function signDetached(
        array $signingKeys, ?DateTime $time = null
    ): SignatureInterface
    {
        $signingKeys = array_filter(
            $signingKeys, static fn ($key) => $key instanceof PrivateKeyInterface
        );
        if (empty($signingKeys)) {
            throw new \InvalidArgumentException('No signing keys provided');
        }
        return new Signature(array_map(
            fn ($key) => SignaturePacket::createLiteralData(
                $key->getSigningKeyPacket(),
                $this->getLiteralDataPacket(),
                $time
            ),
            $signingKeys
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function verify(
        array $verificationKeys, ?DateTime $time = null
    ): array
    {
        return $this->getSignature()->verify(
            $verificationKeys, $this->getLiteralDataPacket(), $time
        );
    }

    /**
     * {@inheritdoc}
     */
    public function verifyDetached(
        array $verificationKeys,
        SignatureInterface $signature,
        ?DateTime $time = null
    ): array
    {
        return $signature->verify(
            $verificationKeys, $this->getLiteralDataPacket(), $time
        );
    }

    /**
     * {@inheritdoc}
     */
    public function encrypt(
        array $encryptionKeys,
        array $passwords = [],
        SymmetricAlgorithm $sessionKeySymmetric = SymmetricAlgorithm::Aes128,
        SymmetricAlgorithm $encryptionKeySymmetric = SymmetricAlgorithm::Aes128
    ): EncryptedMessageInterface
    {
        $encryptionKeys = array_filter(
            $encryptionKeys, static fn ($key) => $key instanceof KeyInterface
        );
        if (empty($encryptionKeys) && empty($passwords)) {
            throw new \InvalidArgumentException(
                'No encryption keys or passwords provided'
            );
        }

        $skeskPackets = [];

        return self([]);
    }

    /**
     * {@inheritdoc}
     */
    function decrypt(
        array $decryptionKeys,
        array $passwords = [],
        bool $allowUnauthenticatedMessages = false
    ): self
    {
        $decryptionKeys = array_filter(
            $decryptionKeys, static fn ($key) => $key instanceof PrivateKeyInterface
        );
        if (empty($decryptionKeys) && empty($passwords)) {
            throw new \InvalidArgumentException(
                'No decryption keys or passwords provided'
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function compress(
        CompressionAlgorithm $algorithm = CompressionAlgorithm::Uncompressed
    ): self
    {
        if ($algorithm !== CompressionAlgorithm::Uncompressed) {
            return new self([
                CompressedData::fromPackets($this->getPackets(), $algorithm)
            ]);
        }
        return $this;
    }

    private static function unwrapCompressed(array $packets): array
    {
        $compressedPackets = array_filter(
            $packets,
            static fn ($packet) => $packet instanceof CompressedData
        );
        return array_pop($compressedPackets)?->getPacketList()->toArray() ?? $packets;
    }
}
