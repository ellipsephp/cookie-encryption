<?php declare(strict_types=1);

namespace Ellipse\Cookies;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Dflydev\FigCookies\SetCookie;
use Dflydev\FigCookies\SetCookies;
use Dflydev\FigCookies\FigResponseCookies;

use Defuse\Crypto\Key;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;

class EncryptCookiesMiddleware implements MiddlewareInterface
{
    /**
     * The defuse encryption keu.
     *
     * @var \Defuse\Crypto\Key
     */
    private $key;

    /**
     * The names of the cookies which bypass encryption.
     *
     * @var array
     */
    private $bypassed;

    /**
     * Set up a encrypt cookie middleware with the given defuse key and an array
     * of bypassed cookie names.
     *
     * @param \Defuse\Crypto\Key    $key
     * @param array                 $bypassed
     */
    public function __construct(Key $key, array $bypassed = [])
    {
        $this->key = $key;
        $this->bypassed = $bypassed;
    }

    /**
     * Start the session, delegate the request processing and add the session
     * cookie to the response.
     *
     * @param \Psr\Http\Message\ServerRequestInterface  $request
     * @param \Psr\Http\Server\RequestHandlerInterface  $handler
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request = $this->withDecryptedCookies($request);

        $response = $handler->handle($request);

        return $this->withEncryptedCookies($response);
    }

    /**
     * Encrypt the given value using the key.
     *
     * @param string $value
     * @return string
     */
    private function encrypt(string $value): string
    {
        return Crypto::encrypt($value, $this->key);
    }

    /**
     * Decrypt the given value using the key. Default to blank string when the
     * key is wrong or the cypher text has been modified.
     *
     * @param string $value
     * @return string
     */
    private function decrypt(string $value): string
    {
        try {

            return Crypto::decrypt($value, $this->key);

        }

        catch (WrongKeyOrModifiedCiphertextException $e) {

            return '';

        }
    }

    /**
     * Decrypt the non bypassed cookie values attached to the given request
     * and return a new request with those values.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    private function withDecryptedCookies(ServerRequestInterface $request): ServerRequestInterface
    {
        $cookies = $request->getCookieParams();

        $decrypted = [];

        foreach ($cookies as $name => $value) {

            $decrypted[$name] = in_array($name, $this->bypassed)
                ? $value
                : $this->decrypt($value);

        }

        return $request->withCookieParams($decrypted);
    }

    /**
     * Encrypt the non bypassed cookie values attached to the given response
     * and return a new response with those values.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return \Psr\Http\Message\ResponseInterface
     */
    private function withEncryptedCookies(ResponseInterface $response): ResponseInterface
    {
        $cookies = (SetCookies::fromResponse($response))->getAll();

        foreach ($cookies as $cookie) {

             $name = $cookie->getName();

             if (in_array($name, $this->bypassed)) continue;

             $response = FigResponseCookies::modify($response, $name, function (SetCookie $cookie) {

                 $value = $cookie->getValue();

                 $encrypted = $this->encrypt($value);

                 return $cookie->withValue($encrypted);

             });

         }

         return $response;
    }
}
