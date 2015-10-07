<?php

namespace StoragelessSession\Http;

use Lcobucci\JWT\Token;

final class SetCookieSerializer
{
    /**
     * @var string
     */
    private $cookieName;

    /**
     * @var bool
     */
    private $secure;

    /**
     * @var bool
     */
    private $httpOnly;

    /**
     * @var string
     */
    private $domain;

    /**
     * @var string
     */
    private $path;

    /**
     * SetCookieSerializer constructor.
     *
     * @param string $cookieName
     * @param bool   $secure
     * @param bool   $httpOnly
     * @param string $domain
     * @param string $path
     */
    public function __construct(
        string $cookieName,
        bool $secure,
        bool $httpOnly,
        string $domain,
        string $path
    ) {
        $this->cookieName = $cookieName;
        $this->secure = $secure;
        $this->httpOnly = $httpOnly;
        $this->domain = $domain;
        $this->path = $path;
    }

    /**
     * @param Token $token
     *
     * @return string
     */
    public function __invoke(Token $token) : string
    {
        return implode(
            '; ',
            array_filter([
                urlencode($this->cookieName) . '=' . $token,
                $this->secure ? 'Secure' : '',
                $this->httpOnly ? 'HttpOnly' : '',
                $this->getExpiration($token),
                $this->domain ? 'Domain=' . urlencode($this->domain) : '',
                $this->path ? 'Path=' . urlencode($this->domain) : '',
            ])
        );
    }

    /**
     * @param Token $token
     *
     * @return string
     */
    private function getExpiration(Token $token) : string
    {
        $claims = $token->getClaims();

        if (! (isset($claims['exp']) && is_int($claims['exp']))) {
            return '';
        }

        return 'Expires=' . (new \DateTime('@' . $claims['exp']))->format(\DateTime::W3C);
    }
}