<?php

namespace Genaker\Bundle\MessageQueueDebugBundle\Tests\Unit\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\Persistence\ManagerRegistry;
use Genaker\Bundle\MessageQueueDebugBundle\Command\MessageQueueListCommand;
use Oro\Component\MessageQueue\Client\Meta\DestinationMeta;
use Oro\Component\MessageQueue\Client\Meta\DestinationMetaRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class MessageQueueListCommandTest extends TestCase
{
    public function testExecuteOutputsTableWithDestinations(): void
    {
        $dest1 = new DestinationMeta('default', 'oro.default', ['processor1']);
        $destMetaRegistry = $this->createMock(DestinationMetaRegistry::class);
        $destMetaRegistry->method('getDestinationsMeta')->willReturn([$dest1]);

        $conn = $this->createMock(Connection::class);
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method('quoteIdentifier')->willReturnArgument(0);
        $conn->method('getDatabasePlatform')->willReturn($platform);
        $conn->method('fetchAllAssociative')->willReturn([['queue' => 'oro.default', 'cnt' => 5]]);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(true);
        $conn->method('getSchemaManager')->willReturn($schemaManager);

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->method('getConnection')->willReturn($conn);

        $command = new MessageQueueListCommand($destMetaRegistry, $doctrine, 'oro_message_queue', 'default');
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $status = $command->run($input, $output);

        self::assertSame(0, $status);
        $out = $output->fetch();
        self::assertStringContainsString('default', $out);
        self::assertStringContainsString('oro.default', $out);
        self::assertStringContainsString('processor1', $out);
        self::assertStringContainsString('5', $out);
    }

    public function testExecuteOutputsJsonWhenJsonOption(): void
    {
        $dest1 = new DestinationMeta('default', 'oro.default', ['processor1']);
        $destMetaRegistry = $this->createMock(DestinationMetaRegistry::class);
        $destMetaRegistry->method('getDestinationsMeta')->willReturn([$dest1]);

        $conn = $this->createMock(Connection::class);
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method('quoteIdentifier')->willReturnArgument(0);
        $conn->method('getDatabasePlatform')->willReturn($platform);
        $conn->method('fetchAllAssociative')->willReturn([['queue' => 'oro.default', 'cnt' => 2]]);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(true);
        $conn->method('getSchemaManager')->willReturn($schemaManager);

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->method('getConnection')->willReturn($conn);

        $command = new MessageQueueListCommand($destMetaRegistry, $doctrine, 'oro_message_queue', 'default');
        $input = new ArrayInput(['--json' => true]);
        $output = new BufferedOutput();

        $status = $command->run($input, $output);

        self::assertSame(0, $status);
        $out = $output->fetch();
        self::assertJson($out);
        $data = json_decode($out, true);
        self::assertIsArray($data);
        self::assertCount(1, $data);
        self::assertSame('default', $data[0]['queue']);
        self::assertSame('processor1', $data[0]['processor']);
        self::assertSame(2, $data[0]['messages']);
    }

    public function testExecuteFiltersByQueue(): void
    {
        $dest1 = new DestinationMeta('default', 'oro.default', ['processor1']);
        $dest2 = new DestinationMeta('other', 'oro.other', ['processor2']);
        $destMetaRegistry = $this->createMock(DestinationMetaRegistry::class);
        $destMetaRegistry->method('getDestinationsMeta')->willReturn([$dest1, $dest2]);

        $conn = $this->createMock(Connection::class);
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method('quoteIdentifier')->willReturnArgument(0);
        $conn->method('getDatabasePlatform')->willReturn($platform);
        $conn->method('fetchAllAssociative')->willReturn([['queue' => 'oro.default', 'cnt' => 1]]);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(true);
        $conn->method('getSchemaManager')->willReturn($schemaManager);

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->method('getConnection')->willReturn($conn);

        $command = new MessageQueueListCommand($destMetaRegistry, $doctrine, 'oro_message_queue', 'default');
        $input = new ArrayInput(['--queue' => 'other']);
        $output = new BufferedOutput();

        $status = $command->run($input, $output);

        self::assertSame(0, $status);
        $out = $output->fetch();
        self::assertStringContainsString('other', $out);
        self::assertStringNotContainsString('processor1', $out);
    }

    public function testExecuteShowsNoteWhenNoDestinations(): void
    {
        $destMetaRegistry = $this->createMock(DestinationMetaRegistry::class);
        $destMetaRegistry->method('getDestinationsMeta')->willReturn([]);

        $doctrine = $this->createMock(ManagerRegistry::class);

        $command = new MessageQueueListCommand($destMetaRegistry, $doctrine, 'oro_message_queue', 'default');
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $status = $command->run($input, $output);

        self::assertSame(0, $status);
        self::assertStringContainsString('No destinations found', $output->fetch());
    }
}
