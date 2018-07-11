<?php

namespace Adshares\Adserver\Tests\Feature;

use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UsersTest extends TestCase
{
    use RefreshDatabase;

    const URI = '/app/users';

    public function testEmptyDb()
    {
        $this->actingAs(factory(User::class)->create());

        $response = $this->getJson(self::URI);
        $response->assertStatus(200);
        $response->assertJsonCount(0);

        $response = $this->getJson(self::URI.'/1');
        $response->assertStatus(404);
    }

    public function testCreateUser()
    {
        /* @var $user \Adshares\Adserver\Models\User */
        $user = factory(\Adshares\Adserver\Models\User::class)->make();

        $response = $this->postJson(self::URI, ['user' => $user->getAttributes(), 'uri' => '/']);

        $response->assertStatus(201);
        $response->assertHeader('Location');
        $response->assertJsonFragment(['email' => $user->email]);

        $uri = $response->headers->get('Location');
        $matches = [];
        $this->assertTrue(1 === preg_match('/(\d+)$/', $uri, $matches));

        $this->actingAs(factory(User::class)->create(['is_admin' => true]));

        $response = $this->getJson(self::URI.'/'.$matches[1]);
        $response->assertStatus(200);
        $response->assertJsonFragment(['email' => $user->email]);

        $response = $this->getJson(self::URI);
        $response->assertStatus(200);
        $response->assertJsonCount(1);

//        $response->assertJsonFragment([
//            'email' => $user->email,
//            'isAdvertiser' => $user->isAdvertiser,
//            'isPublisher' => $user->isPublisher
//        ]);
    }

    public function testCreateUsers()
    {
        $count = 10;

        $users = factory(\Adshares\Adserver\Models\User::class, $count)->make();
        foreach ($users as $user) {
            $response = $this->postJson(self::URI, ['user' => $user->getAttributes(), 'uri' => '/']);
            $response->assertStatus(201);
        }

        $this->actingAs(factory(User::class)->create(['is_admin' => true]));

        $response = $this->getJson(self::URI);
        $response->assertStatus(200);
        $response->assertJsonCount($count);
    }
}
