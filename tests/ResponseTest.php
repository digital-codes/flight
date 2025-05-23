<?php

declare(strict_types=1);

namespace tests;

use Exception;
use flight\net\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER = [];
        $_REQUEST = [];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];
    }

    protected function tearDown(): void
    {
        unset($_REQUEST);
        unset($_SERVER);
    }

    public function testStatusDefault(): void
    {
        $response = new Response();
        $this->assertSame(200, $response->status());
    }

    public function testStatusValidCode(): void
    {
        $response = new Response();
        $response->status(200);
        $this->assertEquals(200, $response->status());
    }

    public function testStatusInvalidCode(): void
    {
        $response = new Response();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid status code.');
        $response->status(999);
    }

    public function testStatusReturnObject(): void
    {
        $response = new Response();
        $this->assertEquals($response, $response->status(200));
    }

    public function testHeaderSingle(): void
    {
        $response = new Response();
        $response->header('Content-Type', 'text/html');
        $this->assertEquals(['Content-Type' => 'text/html'], $response->headers());
    }

    public function testHeaderSingleKeepCaseSensitive(): void
    {
        $response = new Response();
        $response->header('content-type', 'text/html');
        $response->header('x-test', 'test');
        $this->assertEquals(['content-type' => 'text/html', 'x-test' => 'test'], $response->getHeaders());
    }

    public function testHeaderArray(): void
    {
        $response = new Response();
        $response->header(['Content-Type' => 'text/html', 'X-Test' => 'test']);
        $this->assertEquals(['Content-Type' => 'text/html', 'X-Test' => 'test'], $response->headers());
    }

    public function testHeaderReturnObject(): void
    {
        $response = new Response();
        $this->assertEquals($response, $response->header('Content-Type', 'text/html'));
    }

    public function testGetHeaderCrazyCase(): void
    {
        $response = new Response();
        $response->setHeader('CoNtEnT-tYpE', 'text/html');
        $this->assertEquals('text/html', $response->getHeader('content-type'));
    }

    public function testWrite(): void
    {
        $response = new Response();
        $response->write('test');
        $this->assertEquals('test', $response->getBody());
    }

    public function testWriteEmptyString(): void
    {
        $response = new Response();
        $response->write('');
        $this->assertEquals('', $response->getBody());
    }

    public function testWriteReturnObject(): void
    {
        $response = new Response();
        $this->assertEquals($response, $response->write('test'));
    }

    public function testClear(): void
    {
        $response = new Response();

        // Should clear this echo out
        echo 'hi';
        $response->write('test');
        $response->status(404);
        $response->header('Content-Type', 'text/html');
        $response->clear();
        $this->assertEquals('', $response->getBody());
        $this->assertEquals(200, $response->status());
        $this->assertEquals([], $response->headers());
        $this->assertEquals(0, ob_get_length());
    }

    public function testCacheSimple(): void
    {
        $response = new Response();
        $cache_time = time() + 60;
        $response->cache($cache_time);
        $this->assertEquals([
            'Expires' => gmdate('D, d M Y H:i:s', $cache_time) . ' GMT',
            'Cache-Control' => 'max-age=60'
        ], $response->headers());
    }

    public function testCacheSimpleWithString(): void
    {
        $response = new Response();
        $cache_time = time() + 60;
        $response->cache('now +60 seconds');
        $this->assertEquals([
            'Expires' => gmdate('D, d M Y H:i:s', $cache_time) . ' GMT',
            'Cache-Control' => 'max-age=60'
        ], $response->headers());
    }

    public function testCacheSimpleWithPragma(): void
    {
        $response = new Response();
        $cache_time = time() + 60;
        $response->header('Pragma', 'no-cache');
        $response->cache($cache_time);
        $this->assertEquals([
            'Expires' => gmdate('D, d M Y H:i:s', $cache_time) . ' GMT',
            'Cache-Control' => 'max-age=60'
        ], $response->headers());
    }

    public function testCacheFalseExpiresValue(): void
    {
        $response = new Response();
        $response->cache(false);
        $this->assertEquals([
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache'
        ], $response->headers());
    }

    public function testSendHeadersRegular(): void
    {
        $response = new class extends Response {
            protected $test_sent_headers = [];

            protected array $headers = [
                'Cache-Control' => [
                    'no-store, no-cache, must-revalidate',
                    'post-check=0, pre-check=0',
                    'max-age=0',
                ]
            ];
            public function setRealHeader(string $header_string, bool $replace = true, int $response_code = 0): self
            {
                $this->test_sent_headers[] = $header_string;
                return $this;
            }

            public function getSentHeaders(): array
            {
                return $this->test_sent_headers;
            }
        };
        $response->header('Content-Type', 'text/html');
        $response->header('X-Test', 'test');
        $response->write('Something');

        $response->sendHeaders();
        $sent_headers = $response->getSentHeaders();
        $this->assertEquals([
            'HTTP/1.1 200 OK',
            'Cache-Control: no-store, no-cache, must-revalidate',
            'Cache-Control: post-check=0, pre-check=0',
            'Cache-Control: max-age=0',
            'Content-Type: text/html',
            'X-Test: test',
            'Content-Length: 9'
        ], $sent_headers);
    }

    public function testSentDefault(): void
    {
        $response = new Response();
        $this->assertFalse($response->sent());
    }

    public function testSentTrue(): void
    {
        $response = new class extends Response {
            protected $test_sent_headers = [];

            public function setRealHeader(string $header_string, bool $replace = true, int $response_code = 0): self
            {
                $this->test_sent_headers[] = $header_string;
                return $this;
            }
        };
        $response->setHeader('Content-Type', 'text/html');
        $response->setHeader('X-Test', 'test');
        $response->write('Something');

        $this->expectOutputString('Something');
        $response->send();
        $this->assertTrue($response->sent());
    }

    public function testSendWithNoHeadersSent(): void
    {
        $response = new class extends Response {
            protected $test_sent_headers = [];

            public function setRealHeader(string $header_string, bool $replace = true, int $response_code = 0): self
            {
                $this->test_sent_headers[] = $header_string;
                return $this;
            }

            public function getSentHeaders(): array
            {
                return $this->test_sent_headers;
            }

            public function headersSent(): bool
            {
                return false;
            }
        };
        $response->header('Content-Type', 'text/html');
        $response->header('X-Test', 'test');
        $response->write('Something');

        $this->expectOutputString('Something');

        $response->send();
        $sent_headers = $response->getSentHeaders();
        $this->assertEquals([
            'HTTP/1.1 200 OK',
            'Content-Type: text/html',
            'X-Test: test',
            'Content-Length: 9'
        ], $sent_headers);
    }

    public function testClearBody(): void
    {
        $response = new Response();
        $response->write('test');
        $response->clearBody();
        $this->assertEquals('', $response->getBody());
    }

    public function testOverwriteBody(): void
    {
        $response = new Response();
        $response->write('test');
        $response->write('lots more test');
        $response->write('new', true);
        $this->assertEquals('new', $response->getBody());
    }

    public function testResponseBodyCallback(): void
    {
        $response = new Response();
        $response->write('test');
        $str_rot13 = function ($body) {
            return str_rot13($body);
        };
        $response->addResponseBodyCallback($str_rot13);
        ob_start();
        $response->send();
        $rot13_body = ob_get_clean();
        $this->assertEquals('grfg', $rot13_body);
    }

    public function testResponseBodyCallbackGzip(): void
    {
        $response = new Response();
        $response->content_length = true;
        $response->write('test');
        $gzip = function ($body) {
            return gzencode($body);
        };
        $response->addResponseBodyCallback($gzip);
        ob_start();
        $response->send();
        $gzip_body = ob_get_clean();
        switch (PHP_OS) {
            case 'WINNT':
                $expected = 'H4sIAAAAAAAACitJLS4BAAx+f9gEAAAA';
                break;
            case 'Darwin':
                $expected = 'H4sIAAAAAAAAEytJLS4BAAx+f9gEAAAA';
                break;
            default:
                $expected = 'H4sIAAAAAAAAAytJLS4BAAx+f9gEAAAA';
        }
        $this->assertEquals($expected, base64_encode($gzip_body));
        $this->assertEquals(strlen(gzencode('test')), strlen($gzip_body));
    }

    public function testResponseBodyCallbackMultiple(): void
    {
        $response = new Response();
        $response->write('test');
        $str_rot13 = function ($body) {
            return str_rot13($body);
        };
        $str_replace = function ($body) {
            return str_replace('g', 'G', $body);
        };
        $response->addResponseBodyCallback($str_rot13);
        $response->addResponseBodyCallback($str_replace);
        $response->addResponseBodyCallback($str_rot13);
        ob_start();
        $response->send();
        $rot13_body = ob_get_clean();
        $this->assertEquals('TesT', $rot13_body);
    }
}
