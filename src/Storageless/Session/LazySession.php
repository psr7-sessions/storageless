<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

declare(strict_types=1);

namespace PSR7Sessions\Storageless\Session;

use BadMethodCallException;
use Lcobucci\JWT\UnencryptedToken;
use PSR7Sessions\Storageless\Http\SessionMiddleware;
use stdClass;

final class LazySession implements SessionInterface
{
    /** @internal do not access directly: use {@see LazySession::getRealSession} instead */
    private SessionInterface|null $realSession = null;

    /**
     * @var callable
     * @psalm-var callable(): SessionInterface
     */
    private $sessionLoader;

    /**
     * Instantiation via __construct is not allowed, use {@see LazySession::fromContainerBuildingCallback} instead
     *
     * @psalm-param callable(): SessionInterface $sessionLoader
     */
    private function __construct(callable $sessionLoader)
    {
        $this->sessionLoader = $sessionLoader;
    }

    /** @psalm-param callable(): SessionInterface $sessionLoader */
    public static function fromContainerBuildingCallback(callable $sessionLoader): self
    {
        return new self($sessionLoader);
    }

    public static function fromToken(UnencryptedToken|null $token): self
    {
        return self::fromContainerBuildingCallback(
            static function () use ($token): SessionInterface {
                if (! $token) {
                    return DefaultSessionData::newEmptySession();
                }

                try {
                    return DefaultSessionData::fromDecodedTokenData(
                        (object) $token->claims()->get(SessionMiddleware::SESSION_CLAIM, new stdClass()),
                    );
                } catch (BadMethodCallException) {
                    return DefaultSessionData::newEmptySession();
                }
            },
        );
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, $value): void
    {
        $this->getRealSession()->set($key, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, $default = null)
    {
        return $this->getRealSession()->get($key, $default);
    }

    public function remove(string $key): void
    {
        $this->getRealSession()->remove($key);
    }

    public function clear(): void
    {
        $this->getRealSession()->clear();
    }

    public function has(string $key): bool
    {
        return $this->getRealSession()->has($key);
    }

    public function hasChanged(): bool
    {
        return $this->realSession && $this->realSession->hasChanged();
    }

    public function isEmpty(): bool
    {
        return $this->getRealSession()->isEmpty();
    }

    public function jsonSerialize(): object
    {
        return $this->getRealSession()->jsonSerialize();
    }

    /**
     * Get or initialize the session
     */
    private function getRealSession(): SessionInterface
    {
        return $this->realSession ?? $this->realSession = $this->loadSession();
    }

    /**
     * Type-safe wrapper that ensures that the given callback returns the expected type of object, when called
     */
    private function loadSession(): SessionInterface
    {
        return ($this->sessionLoader)();
    }
}
