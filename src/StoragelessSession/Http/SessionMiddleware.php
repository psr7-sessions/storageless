<?php
namespace StoragelessSession\Http;

use DateTime;
use Dflydev\FigCookies\FigRequestCookies;
use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\SetCookie;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\ValidationData;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StoragelessSession\Session\Data;
use Zend\Stratigility\MiddlewareInterface;

final class SessionMiddleware implements MiddlewareInterface
{
    const SESSION_CLAIM     = 'session-data';
    const SESSION_ATTRIBUTE = 'session';
    const DEFAULT_COOKIE    = 'slsession';

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
    private $expirationTime;

    /**
     * @var Parser
     */
    private $tokenParser;

    /**
     * @var SetCookie
     */
    private $defaultCookie;

    /**
     * @param Signer    $signer
     * @param string    $signatureKey
     * @param string    $verificationKey
     * @param SetCookie $defaultCookie
     * @param Parser    $tokenParser
     * @param int       $expirationTime
     */
    public function __construct(
        Signer $signer,
        string $signatureKey,
        string $verificationKey,
        SetCookie $defaultCookie,
        Parser $tokenParser,
        int $expirationTime
    ) {
        $this->signer          = $signer;
        $this->signatureKey    = $signatureKey;
        $this->verificationKey = $verificationKey;
        $this->tokenParser     = $tokenParser;
        $this->defaultCookie   = clone $defaultCookie;
        $this->expirationTime  = $expirationTime;
    }

    /**
     * This constructor simplifies instantiation when using HTTPS (REQUIRED!) and symmetric key encription
     *
     * @param string $symmetricKey
     * @param int    $expirationTime
     *
     * @return self
     */
    public static function fromSymmetricKeyDefaults(string $symmetricKey, int $expirationTime)
    {
        return new self(
            new Signer\Hmac\Sha256(),
            $symmetricKey,
            $symmetricKey,
            SetCookie::create(self::DEFAULT_COOKIE)
                ->withSecure(true)
                ->withHttpOnly(true),
            new Parser(),
            $expirationTime
        );
    }

    /**
     * This constructor simplifies instantiation when using HTTPS (REQUIRED!) and asymmetric key encription
     * based on RSA keys
     *
     * @param string $privateRsaKey
     * @param string $publicRsaKey
     * @param int    $expirationTime
     *
     * @return self
     */
    public static function fromAsymmetricKey(string $privateRsaKey, string $publicRsaKey, int $expirationTime)
    {
        return new self(
            new Signer\Rsa\Sha256(),
            $privateRsaKey,
            $publicRsaKey,
            SetCookie::create(self::DEFAULT_COOKIE)
                ->withSecure(true)
                ->withHttpOnly(true),
            new Parser(),
            $expirationTime
        );
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
        if (! $content = FigRequestCookies::get($request, $this->defaultCookie->getName())->getValue()) {
            return null;
        }

        try {
            $token = $this->tokenParser->parse($content);
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

        return FigResponseCookies::set($response, $this->getTokenCookie($sessionContainer));
    }

    /**
     * @param Data $sessionContainer
     *
     * @return SetCookie
     *
     * @throws \BadMethodCallException
     */
    private function getTokenCookie(Data $sessionContainer) : SetCookie
    {
        $timestamp = (new \DateTime())->getTimestamp();

        return $this
            ->defaultCookie
            ->withValue(
                (new Builder())
                    ->setIssuedAt($timestamp)
                    ->setExpiration($timestamp + $this->expirationTime)
                    ->set(self::SESSION_CLAIM, $sessionContainer)
                    ->sign($this->signer, $this->signatureKey)
                    ->getToken()
            )
            ->withExpires($timestamp + $this->expirationTime);
    }
}
