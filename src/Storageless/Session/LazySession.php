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

final class LazySession implements SessionInterface
{
    /**
     * @internal do not access directly: use {@see LazySession::getRealSession} instead
     *
     * @var SessionInterface|null
     */
    private $realSession;

    /**
     * @var callable
     */
    private $sessionLoader;

    /**
     * Instantiation via __construct is not allowed, use {@see LazySession::fromContainerBuildingCallback} instead
     */
    private function __construct()
    {
    }

    /**
     * @param callable $sessionLoader
     *
     * @return self
     */
    public static function fromContainerBuildingCallback(callable $sessionLoader) : self
    {
        $instance = new self();

        $instance->sessionLoader = $sessionLoader;

        return $instance;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, $value) : void
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

    /**
     * {@inheritDoc}
     */
    public function remove(string $key) : void
    {
        $this->getRealSession()->remove($key);
    }

    /**
     * {@inheritDoc}
     */
    public function clear() : void
    {
        $this->getRealSession()->clear();
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key) : bool
    {
        return $this->getRealSession()->has($key);
    }

    /**
     * {@inheritDoc}
     */
    public function hasChanged() : bool
    {
        return $this->realSession && $this->realSession->hasChanged();
    }

    /**
     * {@inheritDoc}
     */
    public function isEmpty() : bool
    {
        return $this->getRealSession()->isEmpty();
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize()
    {
        return $this->getRealSession()->jsonSerialize();
    }

    /**
     * Get or initialize the session
     *
     * @return SessionInterface
     */
    private function getRealSession() : SessionInterface
    {
        return $this->realSession ?? $this->realSession = $this->loadSession();
    }

    /**
     * Type-safe wrapper that ensures that the given callback returns the expected type of object, when called
     *
     * @return SessionInterface
     */
    private function loadSession() : SessionInterface
    {
        $sessionLoader = $this->sessionLoader;

        return $sessionLoader();
    }
}
