<?php

namespace Feature\API;

use App\Enums\ServerProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServerProvidersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @dataProvider data
     */
    public function test_connect_provider(string $provider, array $input): void
    {
        Sanctum::actingAs($this->user, ['read', 'write']);

        Http::fake();

        $data = array_merge(
            [
                'provider' => $provider,
                'name' => 'profile',
            ],
            $input
        );
        $this->json('POST', route('api.server-providers.create', [
            'project' => $this->user->current_project_id,
        ]), $data)
            ->assertSuccessful()
            ->assertJsonFragment([
                'provider' => $provider,
                'profile' => 'profile',
                'project_id' => isset($input['global']) ? null : $this->user->current_project_id,
            ]);

        $this->assertDatabaseHas('server_providers', [
            'provider' => $provider,
            'profile' => 'profile',
            'project_id' => isset($input['global']) ? null : $this->user->current_project_id,
        ]);
    }

    /**
     * @dataProvider data
     */
    public function test_cannot_connect_to_provider(string $provider, array $input): void
    {
        Sanctum::actingAs($this->user, ['read', 'write']);

        Http::fake([
            '*' => Http::response([], 401),
        ]);

        $data = array_merge(
            [
                'provider' => $provider,
                'name' => 'profile',
            ],
            $input
        );
        $this->json('POST', route('api.server-providers.create', [
            'project' => $this->user->current_project_id,
        ]), $data)
            ->assertJsonValidationErrorFor('provider');

        $this->assertDatabaseMissing('server_providers', [
            'provider' => $provider,
            'profile' => 'profile',
        ]);
    }

    public function test_see_providers_list(): void
    {
        $this->actingAs($this->user);

        /** @var \App\Models\ServerProvider $provider */
        $provider = \App\Models\ServerProvider::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->json('GET', route('api.server-providers', [
            'project' => $this->user->current_project_id,
        ]))
            ->assertSuccessful()
            ->assertJsonFragment([
                'id' => $provider->id,
                'provider' => $provider->provider,
            ]);
    }

    /**
     * @dataProvider data
     */
    public function test_delete_provider(string $provider): void
    {
        $this->actingAs($this->user);

        /** @var \App\Models\ServerProvider $provider */
        $provider = \App\Models\ServerProvider::factory()->create([
            'user_id' => $this->user->id,
            'provider' => $provider,
        ]);

        $this->json('DELETE', route('api.server-providers.delete', [
            'project' => $this->user->current_project_id,
            'serverProvider' => $provider->id,
        ]))
            ->assertNoContent();

        $this->assertDatabaseMissing('server_providers', [
            'id' => $provider->id,
        ]);
    }

    /**
     * @dataProvider data
     */
    public function test_cannot_delete_provider(string $provider): void
    {
        $this->actingAs($this->user);

        /** @var \App\Models\ServerProvider $provider */
        $provider = \App\Models\ServerProvider::factory()->create([
            'user_id' => $this->user->id,
            'provider' => $provider,
        ]);

        $this->server->update([
            'provider_id' => $provider->id,
        ]);

        $this->json('DELETE', route('api.server-providers.delete', [
            'project' => $this->user->current_project_id,
            'serverProvider' => $provider->id,
        ]))
            ->assertJsonValidationErrors([
                'provider' => 'This server provider is being used by a server.',
            ]);

        $this->assertDatabaseHas('server_providers', [
            'id' => $provider->id,
        ]);
    }

    public static function data(): array
    {
        return [
            // [
            //     ServerProvider::AWS,
            //     [
            //         'key' => 'key',
            //         'secret' => 'secret',
            //     ],
            // ],
            [
                ServerProvider::LINODE,
                [
                    'token' => 'token',
                ],
            ],
            [
                ServerProvider::LINODE,
                [
                    'token' => 'token',
                    'global' => 1,
                ],
            ],
            [
                ServerProvider::DIGITALOCEAN,
                [
                    'token' => 'token',
                ],
            ],
            [
                ServerProvider::VULTR,
                [
                    'token' => 'token',
                ],
            ],
            [
                ServerProvider::HETZNER,
                [
                    'token' => 'token',
                ],
            ],
        ];
    }
}