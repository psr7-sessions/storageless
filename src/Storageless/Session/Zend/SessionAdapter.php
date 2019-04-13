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

namespace PSR7Sessions\Storageless\Session\Zend;

use Lcobucci\Clock\Clock;
use Lcobucci\Clock\SystemClock;
use PSR7Sessions\Storageless\Session\SessionInterface;
use Zend\Expressive\Session\SessionInterface as ZendSessionInterface;

final class SessionAdapter implements ZendSessionInterface
{
    private const SESSION_REGENERATED_NAME = '_regenerated';

    /** @var SessionInterface */
    private $session;

    /** @var Clock */
    private $clock;

    public function __construct(SessionInterface $session, ?Clock $clock = null)
    {
        $this->session = $session;
        $this->clock   = $clock ?? new SystemClock();
    }

    /**
     * @return array
     */
    public function toArray() : array
    {
        return (array) $this->session->jsonSerialize();
    }

    /** {@inheritDoc} */
    public function get(string $name, $default = null)
    {
        return $this->session->get($name, $default);
    }

    public function has(string $name) : bool
    {
        return $this->session->has($name);
    }

    /** {@inheritDoc} */
    public function set(string $name, $value) : void
    {
        $this->session->set($name, $value);
    }

    public function unset(string $name) : void
    {
        $this->session->remove($name);
    }

    public function clear() : void
    {
        $this->session->clear();
    }

    public function hasChanged() : bool
    {
        return $this->session->hasChanged();
    }

    public function regenerate() : ZendSessionInterface
    {
        $this->session->set(self::SESSION_REGENERATED_NAME, $this->timestamp());

        return $this;
    }

    public function isRegenerated() : bool
    {
        return $this->session->has(self::SESSION_REGENERATED_NAME);
    }

    private function timestamp() : int
    {
        return $this->clock->now()->getTimestamp();
    }
}
