<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_x_forwarded_proto_https_is_honored_from_an_untrusted_looking_source_ip(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.7',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'app.rahai.sch.id',
        ])->get('/up');

        $response->assertOk();
        $this->assertTrue($response->baseRequest->isSecure());
        $this->assertSame('app.rahai.sch.id', $response->baseRequest->getHost());
    }

    public function test_without_the_forwarded_header_the_request_is_not_treated_as_secure(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.7',
        ])->get('/up');

        $response->assertOk();
        $this->assertFalse($response->baseRequest->isSecure());
    }
}
