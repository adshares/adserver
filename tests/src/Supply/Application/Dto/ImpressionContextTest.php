<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types=1);

namespace Adshares\Tests\Supply\Application\Dto;

use Adshares\Supply\Application\Dto\ImpressionContext;
use PHPUnit\Framework\TestCase;

use function json_decode;

class ImpressionContextTest extends TestCase
{
    /** @dataProvider bodyProvider */
    public function testAdUserRequestBody(array $site, array $device, array $user): void
    {
        $context = new ImpressionContext($site, $device, $user);
        $body = $context->adUserRequestBody();

        self::assertNotEmpty($body);

        self::assertArrayHasKey('url', $body);
        self::assertInternalType('string', $body['url']);

        self::assertArrayHasKey('tags', $body);
        self::assertInternalType('array', $body['tags']);

        self::assertArrayHasKey('headers', $body);
        self::assertArrayHasKey('user-agent', $body['headers']);

        self::assertNotEmpty($body['headers']['user-agent']);
        self::assertInternalType('string', $body['headers']['user-agent']);
    }

    public function bodyProvider()
    {
        $json = <<<"JSON"
{
"site": {
"domain": "localhost",
"inframe": "no",
"page": "http://localhost:8101/test-publisher/index.html",
"keywords": [
"lorem ipsum",
"lipsum",
"lorem",
"ipsum",
"text",
"generate",
"generator",
"facts",
"information",
"what"
]
},
"device": {
"ua": "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.86 Safari/537.36",
"ip": "89.231.24.58",
"ips": [
"89.231.24.58"
],
"headers": {
"cookie": [
"__cfduid=d7b9dc5775589296a8badf6169665fe751548759430; tid=zz3wznfknWKiURFxyEwajaUoKT42rA"
],
"accept-language": [
"en-US,en;q=0.9,pl;q=0.8"
],
"accept-encoding": [
"gzip, deflate, br"
],
"referer": [
"http://localhost:8101/test-publisher/index.html"
],
"accept": [
"text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3"
],
"user-agent": [
"Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.86 Safari/537.36"
],
"dnt": [
"1"
],
"upgrade-insecure-requests": [
"1"
],
"connection": [
"keep-alive"
],
"host": [
"dev-server.e11.click"
],
"content-length": [
""
],
"content-type": [
""
]
}
},
"user":{
}
}
JSON;

        $decode = json_decode($json, true);

        return [$decode];
    }
}
