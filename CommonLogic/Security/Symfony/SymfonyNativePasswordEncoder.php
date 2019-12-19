<?php
namespace exface\Core\CommonLogic\Security\Symfony;

use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
use Symfony\Component\Security\Core\Encoder\SelfSaltingEncoderInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

/**
* Polyfill for NativePasswordEncoder from Symfony 5
*/
class SymfonyNativePasswordEncoder implements PasswordEncoderInterface, SelfSaltingEncoderInterface
{
    private const MAX_PASSWORD_LENGTH = 4096;
    private $algo;
    private $options;
    /**
     * @param string|null $algo An algorithm supported by password_hash() or null to use the stronger available algorithm
     */
    public function __construct(int $opsLimit = null, int $memLimit = null, int $cost = null, string $algo = null)
    {
        $cost = $cost ?? 13;
        $opsLimit = $opsLimit ?? max(4, \defined('SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE') ? \SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE : 4);
        $memLimit = $memLimit ?? max(64 * 1024 * 1024, \defined('SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE') ? \SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE : 64 * 1024 * 1024);
        if (3 > $opsLimit) {
            throw new \InvalidArgumentException('$opsLimit must be 3 or greater.');
        }
        if (10 * 1024 > $memLimit) {
            throw new \InvalidArgumentException('$memLimit must be 10k or greater.');
        }
        if ($cost < 4 || 31 < $cost) {
            throw new \InvalidArgumentException('$cost must be in the range of 4-31.');
        }
        $this->algo = (string) ($algo ?? (\defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : (\defined('PASSWORD_ARGON2I') ? PASSWORD_ARGON2I : PASSWORD_BCRYPT)));
        $this->options = [
            'cost' => $cost,
            'time_cost' => $opsLimit,
            'memory_cost' => $memLimit >> 10,
            'threads' => 1,
        ];
    }
    /**
     * {@inheritdoc}
     */
    public function encodePassword($raw, $salt): string
    {
        if (\strlen($raw) > self::MAX_PASSWORD_LENGTH || ((string) PASSWORD_BCRYPT === $this->algo && 72 < \strlen($raw))) {
            throw new BadCredentialsException('Invalid password.');
        }
        // Ignore $salt, the auto-generated one is always the best
        return password_hash($raw, $this->algo, $this->options);
    }
    /**
     * {@inheritdoc}
     */
    public function isPasswordValid($encoded, $raw, $salt): bool
    {
        if (\strlen($raw) > self::MAX_PASSWORD_LENGTH) {
            return false;
        }
        if (0 !== strpos($encoded, '$argon')) {
            // BCrypt encodes only the first 72 chars
            return (72 >= \strlen($raw) || 0 !== strpos($encoded, '$2')) && password_verify($raw, $encoded);
        }
        if (\extension_loaded('sodium') && version_compare(\SODIUM_LIBRARY_VERSION, '1.0.14', '>=')) {
            return sodium_crypto_pwhash_str_verify($encoded, $raw);
        }
        if (\extension_loaded('libsodium') && version_compare(phpversion('libsodium'), '1.0.14', '>=')) {
            return \Sodium\crypto_pwhash_str_verify($encoded, $raw);
        }
        return password_verify($raw, $encoded);
    }
    /**
     * {@inheritdoc}
     */
    public function needsRehash(string $encoded): bool
    {
        return password_needs_rehash($encoded, $this->algo, $this->options);
    }
}