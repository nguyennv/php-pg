<?php declare(strict_types=1);
/**
 * This file is part of the PHP Privacy project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OpenPGP\Common;

use OpenPGP\Enum\S2kType;
use phpseclib3\Crypt\Random;

/**
 * String-to-key trait
 * 
 * @package  OpenPGP
 * @category Common
 * @author   Nguyen Van Nguyen - nguyennv1981@gmail.com
 */
trait S2KTrait
{
    /**
     * {@inheritdoc}
     */
    public function getType(): S2kType
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function getSalt(): string
    {
        return $this->salt;
    }

    /**
     * {@inheritdoc}
     */
    public function getLength(): int
    {
        return $this->type->packetLength();
    }

    /**
     * Generate random salt string
     * 
     * @return string
     */
    public static function generateSalt(): string 
    {
        return preg_replace_callback(
            '/\*/u',
            fn () => chr(mt_rand(33, 126)),
            str_repeat('*', static::SALT_LENGTH)
        );
    }
}
