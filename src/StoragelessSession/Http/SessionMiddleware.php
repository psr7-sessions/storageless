<?php
namespace StoragelessSession\Http;

use DateTime;
use Zend\Stratigility\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Builder;
use StoragelessSession\Session\Data;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\ValidationData;

final class SessionMiddleware implements MiddlewareInterface
{
    const SESSION_CLAIM     = 'session-data';
    const SESSION_ATTRIBUTE = 'session';

    /**
     * @var string
     */
    private $cookieName = 'yadda';

    /**
     * @var Signer
     */
    private $signer;

    /**
     * @var string
     */
    private $signatureKey;

    /**
     * @var string
     */
    private $verificationKey;

    /**
     * @var int
     */
    private $expirationTime = 14600;

    /**
     * @var Parser
     */
    private $tokenParser;

    /**
     * @param Signer $signer
     * @param string $signatureKey
     * @param string $verificationKey
     * @param Parser $tokenParser
     */
    public function __construct(
        Signer $signer,
        $signatureKey,
        $verificationKey,
        Parser $tokenParser
    ) {
        $this->signer = $signer;
        $this->signatureKey = $signatureKey;
        $this->verificationKey = $verificationKey;
        $this->tokenParser = $tokenParser;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \InvalidArgumentException
     * @throws \OutOfBoundsException
     * @throws \BadMethodCallException
     */
    public function __invoke(Request $request, Response $response, callable $out = null)
    {
        list($request, $sessionContainer) = $this->injectSession($request, $this->parseToken($request));
        $response = $out === null ? $response : $out($request, $response);

        return $this->appendToken($sessionContainer, $response);
    }

    /**
     * Extract the token from the given request object
     *
     * @param Request $request
     *
     * @return Token|null
     *
     * @throws \BadMethodCallException
     */
    private function parseToken(Request $request)
    {
        $cookieStrings = $request->getCookieParams();

        if (! isset($cookieStrings[$this->cookieName])) {
            return null;
        }

        try {
            $token = $this->tokenParser->parse($cookieStrings[$this->cookieName]);
        } catch (\InvalidArgumentException $invalidToken) {
            return null;
        }

        if (! ($token->verify($this->signer, $this->verificationKey) && $token->validate(new ValidationData()))) {
            return null;
        }

        return $token;
    }

    /**
     * @param Request    $request
     * @param Token|null $token
     *
     * @return Request[]|Data[] (ordered tuple, with request first, response second)
     *
     * @throws \OutOfBoundsException
     */
    private function injectSession(Request $request, Token $token = null) : array
    {
        $container = $token
            ? Data::fromDecodedTokenData(
                $token->getClaim(self::SESSION_CLAIM) ?? new \stdClass()
            )
            : Data::newEmptySession();

        return [$request->withAttribute(self::SESSION_ATTRIBUTE, $container), $container];
    }

    /**
     * @param Data     $sessionContainer
     * @param Response $response
     *
     * @return Response
     *
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    private function appendToken(Data $sessionContainer, Response $response) : Response
    {
        if ($sessionContainer->isEmpty()) {
            return $response;
        }

        return $response->withAddedHeader('Set-Cookie', $this->getTokenCookie($sessionContainer));
    }

    /**
     * @param Data $sessionContainer
     *
     * @return string
     *
     * @throws \BadMethodCallException
     */
    private function getTokenCookie(Data $sessionContainer) : string
    {
        $timestamp = (new \DateTime())->getTimestamp();

        $token = (new Builder())
            ->setIssuedAt($timestamp)
            ->setExpiration($timestamp + $this->expirationTime)
            ->set(self::SESSION_CLAIM, $sessionContainer)
            ->sign($this->signer, $this->signatureKey)
            ->getToken();

        return sprintf(
            '%s=%s',
            urlencode($this->cookieName),
            $token
        );
//
//        return sprintf(
//            '%s=%s; Domain=%s; Path=%s; Expires=%s; Secure; HttpOnly',
//            urlencode($this->cookieName),
//            $token,
//            'foo.com',
//            '/',
//            (new DateTime('@' . $token->getClaim('exp')))->format(DateTime::W3C)
//        );
    }
}
