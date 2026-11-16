<?php

namespace Tests\Unit\Broadcasting;

use App\Broadcasting\CentrifugoBroadcaster;
use App\Services\Realtime\CentrifugoClient;
use Illuminate\Broadcasting\PrivateChannel;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class CentrifugoBroadcasterTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const NAMESPACES = ['user' => 'user', 'conversation' => 'conv'];

    private function broadcaster(MockInterface $client): CentrifugoBroadcaster
    {
        return new CentrifugoBroadcaster($client, self::NAMESPACES);
    }

    public function test_private_channels_are_mapped_to_centrifugo_namespaces(): void
    {
        $client = Mockery::mock(CentrifugoClient::class);
        $client->shouldReceive('broadcast')
            ->once()
            ->with(['user:42', 'conv:abc-123'], ['event' => 'Something', 'data' => ['foo' => 'bar']]);

        $this->broadcaster($client)->broadcast(
            [new PrivateChannel('user.42'), new PrivateChannel('conversation.abc-123')],
            'Something',
            ['foo' => 'bar'],
        );
    }

    public function test_channel_names_given_as_strings_are_mapped_with_or_without_the_private_prefix(): void
    {
        $client = Mockery::mock(CentrifugoClient::class);
        $client->shouldReceive('broadcast')
            ->once()
            ->with(['user:1', 'conv:2'], Mockery::type('array'));

        $this->broadcaster($client)->broadcast(['private-user.1', 'conversation.2'], 'Something');
    }

    public function test_unmapped_channels_are_dropped(): void
    {
        $client = Mockery::mock(CentrifugoClient::class);
        $client->shouldReceive('broadcast')
            ->once()
            ->with(['user:1'], Mockery::type('array'));

        $this->broadcaster($client)->broadcast(
            ['private-orders.7', 'private-user.1', 'presence-room.3', 'users.9'],
            'Something',
        );
    }

    public function test_nothing_is_published_when_no_channel_can_be_mapped(): void
    {
        $client = Mockery::mock(CentrifugoClient::class);
        $client->shouldNotReceive('broadcast');

        $broadcaster = $this->broadcaster($client);
        $broadcaster->broadcast(['private-orders.7'], 'Something', ['foo' => 'bar']);
        $broadcaster->broadcast([], 'Something', ['foo' => 'bar']);
    }

    public function test_socket_id_is_moved_out_of_the_payload_into_origin_client(): void
    {
        $client = Mockery::mock(CentrifugoClient::class);
        $client->shouldReceive('broadcast')
            ->once()
            ->with(['user:1'], [
                'event' => 'Something',
                'data' => ['foo' => 'bar'],
                '_origin_client' => 'client-123',
            ]);

        $this->broadcaster($client)->broadcast(
            ['private-user.1'],
            'Something',
            ['foo' => 'bar', 'socket' => 'client-123'],
        );
    }

    public function test_a_null_socket_is_removed_from_the_payload(): void
    {
        $client = Mockery::mock(CentrifugoClient::class);
        $client->shouldReceive('broadcast')
            ->once()
            ->with(['user:1'], ['event' => 'Something', 'data' => ['foo' => 'bar']]);

        $this->broadcaster($client)->broadcast(
            ['private-user.1'],
            'Something',
            ['foo' => 'bar', 'socket' => null],
        );
    }
}
