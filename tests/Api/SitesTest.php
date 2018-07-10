<?php

namespace Adshares\Adserver\Tests\Feature;

use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SitesTest extends TestCase
{
    use RefreshDatabase;

    const URI = '/app/sites';

    public function testEmptyDb()
    {
        $this->actingAs(factory(User::class)->create());

        $response = $this->getJson(self::URI);
        $response->assertStatus(200);
        $response->assertJsonCount(0);

        $response = $this->getJson(self::URI . '/1');
        $response->assertStatus(404);
    }

    public function testCreateSite()
    {
        $this->actingAs(factory(User::class)->create());

        /* @var $site \Adshares\Adserver\Models\Site */
        $site = factory(\Adshares\Adserver\Models\Site::class)->make();

        $response = $this->postJson(self::URI, ["site" => $site->getAttributes()]);

        $response->assertStatus(201);
        $response->assertHeader('Location');
        $response->assertJsonFragment(['name' => $site->name]);
        $response->assertJsonFragment(['url' => $site->url]);

        $uri = $response->headers->get('Location');
        $matches = [];
        $this->assertTrue(1 === preg_match('/(\d+)$/', $uri, $matches));

        $response = $this->getJson(self::URI . '/' . $matches[1]);
        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $site->name]);
        $response->assertJsonFragment(['url' => $site->url]);

        $response = $this->getJson(self::URI);
        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    public function testCreateSites()
    {
        $this->actingAs(factory(User::class)->create());
        $count = 10;

        $users = factory(\Adshares\Adserver\Models\Site::class, $count)->make();
        foreach ($users as $site) {
            $response = $this->postJson(self::URI, ["site" => $site->getAttributes()]);
            $response->assertStatus(201);
        }

        $response = $this->getJson(self::URI);
        $response->assertStatus(200);
        $response->assertJsonCount($count);
    }
}
