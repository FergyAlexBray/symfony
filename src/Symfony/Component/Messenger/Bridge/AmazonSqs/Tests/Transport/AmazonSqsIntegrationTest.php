<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmazonSqs\Tests\Transport;

use AsyncAws\Sqs\SqsClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\Connection;

class AmazonSqsIntegrationTest extends TestCase
{
    public function testConnectionSendToFifoQueueAndGet(): void
    {
        if (!getenv('MESSENGER_SQS_FIFO_QUEUE_DSN')) {
            $this->markTestSkipped('The "MESSENGER_SQS_FIFO_QUEUE_DSN" environment variable is required.');
        }

        $this->execute(getenv('MESSENGER_SQS_FIFO_QUEUE_DSN'));
    }

    public function testConnectionSendAndGet(): void
    {
        if (!getenv('MESSENGER_SQS_DSN')) {
            $this->markTestSkipped('The "MESSENGER_SQS_DSN" environment variable is required.');
        }

        $this->execute(getenv('MESSENGER_SQS_DSN'));
    }

    private function execute(string $dsn): void
    {
        $connection = Connection::fromDsn($dsn, []);
        $connection->setup();
        $this->clearSqs($dsn);

        $connection->send('{"message": "Hi"}', ['type' => DummyMessage::class]);
        $this->assertSame(1, $connection->getMessageCount());

        $wait = 0;
        while ((null === $encoded = $connection->get()) && $wait++ < 200) {
            usleep(5000);
        }

        $this->assertEquals('{"message": "Hi"}', $encoded['body']);
        $this->assertEquals(['type' => DummyMessage::class], $encoded['headers']);
    }

    private function clearSqs(string $dsn): void
    {
        $url = parse_url($dsn);
        $client = new SqsClient(['endpoint' => "http://{$url['host']}:{$url['port']}"]);
        $client->purgeQueue([
            'QueueUrl' => $client->getQueueUrl(['QueueName' => ltrim($url['path'], '/')])->getQueueUrl(),
        ]);
    }
}
