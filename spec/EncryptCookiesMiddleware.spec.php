<?php

use function Eloquent\Phony\Kahlan\mock;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Defuse\Crypto\Key;
use Defuse\Crypto\Crypto;

use Laminas\Diactoros\Response\TextResponse;

use Ellipse\Cookies\EncryptCookiesMiddleware;

describe('EncryptCookiesMiddleware', function () {

    beforeEach(function () {

        $this->key = Key::createNewRandomKey();
        $this->bypassed = ['bypassed'];

        $this->middleware = new EncryptCookiesMiddleware($this->key, $this->bypassed);

    });

    it('should implement MiddlewareInterface', function () {

        expect($this->middleware)->toBeAnInstanceOf(MiddlewareInterface::class);

    });

    describe('->process()', function () {

        beforeEach(function () {

            $this->request1 = mock(ServerRequestInterface::class);
            $this->request2 = mock(ServerRequestInterface::class);
            $this->response = new TextResponse('body', 404, ['header' => 'header-value']);

            $this->handler = mock(RequestHandlerInterface::class);

            $this->request1->getCookieParams->returns([]);

            $this->request1->withCookieParams->returns($this->request2);

            $this->handler->handle->returns($this->response);

        });

        it('should return a ResponseInterface', function () {

            $test = $this->middleware->process($this->request1->get(), $this->handler->get());

            expect($test)->toBeAnInstanceOf(ResponseInterface::class);

        });

        it('should call the request handler ->handle() method with the modified request', function () {

            $this->middleware->process($this->request1->get(), $this->handler->get());

            $this->handler->handle->calledWith($this->request2);

        });

        it('should return a response with the same body as the one returned by the request handler', function () {

            $test = $this->middleware->process($this->request1->get(), $this->handler->get())
                ->getBody()
                ->getContents();

            expect($test)->toEqual('body');

        });

        it('should return a response with the same status code as the one returned by the request handler', function () {

            $test = $this->middleware->process($this->request1->get(), $this->handler->get())
                ->getStatusCode();

            expect($test)->toEqual(404);

        });

        it('should return a response with the same headers as the one returned by the request handler', function () {

            $test = $this->middleware->process($this->request1->get(), $this->handler->get());

            expect($test->getHeaderLine('Content-type'))->toContain('text');
            expect($test->getHeaderLine('header'))->toContain('header-value');

        });

        context('when the request has a non bypassed cookie attached to it', function () {

            context('when the encryption is valid', function () {

                it('should attach a cookie with the same name and the decrypted value to the request', function () {

                    $this->request1->getCookieParams->returns([
                        'encrypted' => Crypto::encrypt('value', $this->key),
                    ]);

                    $this->middleware->process($this->request1->get(), $this->handler->get());

                    $this->request1->withCookieParams->calledWith([
                        'encrypted' => 'value',
                    ]);

                });

            });

            context('when the encryption is not valid', function () {

                it('should attach a cookie with the same name and a blank string value to the request', function () {

                    $this->request1->getCookieParams->returns([
                        'encrypted' => 'value',
                    ]);

                    $this->middleware->process($this->request1->get(), $this->handler->get());

                    $this->request1->withCookieParams->calledWith([
                        'encrypted' => '',
                    ]);

                });

            });

        });

        context('when the request has a bypassed cookie attached to it', function () {

            it('should attach a cookie with the same name and value to the request', function () {

                $this->request1->getCookieParams->returns([
                    'bypassed' => 'value',
                ]);

                $this->middleware->process($this->request1->get(), $this->handler->get());

                $this->request1->withCookieParams->calledWith([
                    'bypassed' => 'value',
                ]);

            });

        });

        context('when the request has multple cookie attached to it', function () {

            it('should attach them all to the new request', function () {

                $this->request1->getCookieParams->returns([
                    'encrypted' => Crypto::encrypt('encrypted', $this->key),
                    'wrong' => 'wrong',
                    'bypassed' => 'bypassed',
                ]);

                $this->middleware->process($this->request1->get(), $this->handler->get());

                $this->request1->withCookieParams->calledWith([
                    'encrypted' => 'encrypted',
                    'wrong' => '',
                    'bypassed' => 'bypassed',
                ]);

            });

        });

        context('when the response has a non bypassed cookie attached to it', function () {

            it('should attach a cookie with the same name and the encrypted value to the response', function () {

                $response = new TextResponse('body', 404, ['set-cookie' => 'encrypted=value']);

                $this->handler->handle->returns($response);

                $header = $this->middleware->process($this->request1->get(), $this->handler->get())
                    ->getHeaderLine('set-cookie');

                $parts = explode('=', $header);

                $value = end($parts);

                $test = Crypto::decrypt($value, $this->key);

                expect($test)->toEqual('value');

            });

        });

        context('when the response has a bypassed cookie attached to it', function () {

            it('should attach a cookie with the same name and value to the response', function () {

                $response = new TextResponse('body', 404, ['set-cookie' => 'bypassed=value']);

                $this->handler->handle->returns($response);

                $test = $this->middleware->process($this->request1->get(), $this->handler->get())
                    ->getHeaderLine('set-cookie');

                expect($test)->toContain('bypassed=value');

            });

        });

        context('when the response has multple cookie attached to it', function () {

            it('should attach them all to the response', function () {

                $response = new TextResponse('body', 404, ['set-cookie' => ['encrypted=encrypted', 'bypassed=bypassed']]);

                $this->handler->handle->returns($response);

                $headers = $this->middleware->process($this->request1->get(), $this->handler->get())
                    ->getHeader('set-cookie');

                $parts1 = explode('=', $headers[0]);

                $value = end($parts1);

                $test1 = Crypto::decrypt($value, $this->key);

                $parts2 = explode('=', $headers[1]);

                $test2 = end($parts2);

                expect($test1)->toEqual('encrypted');
                expect($test2)->toEqual('bypassed');

            });

        });

    });

});
