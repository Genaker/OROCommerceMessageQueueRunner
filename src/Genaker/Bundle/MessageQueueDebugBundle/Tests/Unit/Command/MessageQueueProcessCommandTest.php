<?php

namespace Genaker\Bundle\MessageQueueDebugBundle\Tests\Unit\Command;

use Genaker\Bundle\MessageQueueDebugBundle\Command\MessageQueueProcessCommand;
use Oro\Component\MessageQueue\Client\Meta\DestinationMeta;
use Oro\Component\MessageQueue\Client\Meta\DestinationMetaRegistry;
use Oro\Component\MessageQueue\Consumption\QueueConsumer;
use Oro\Component\MessageQueue\Log\ConsumerState;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class MessageQueueProcessCommandTest extends TestCase
{
    public function testExecuteBindsQueueAndConsumes(): void
    {
        $dest = new DestinationMeta('default', 'oro.default', ['processor1']);
        $destMetaRegistry = $this->createMock(DestinationMetaRegistry::class);
        $destMetaRegistry->method('getDestinationMeta')->with('default')->willReturn($dest);

        $queueConsumer = $this->createMock(QueueConsumer::class);
        $queueConsumer->expects(self::once())->method('bind')->with('oro.default', '');
        $queueConsumer->expects(self::once())->method('consume')->willReturn(null);
        $queueConsumer->method('getConnection')->willReturn($this->createMock(\Oro\Component\MessageQueue\Transport\ConnectionInterface::class));

        $consumerState = $this->createMock(ConsumerState::class);
        $consumerState->expects(self::once())->method('startConsumption');
        $consumerState->expects(self::once())->method('stopConsumption');

        $command = new MessageQueueProcessCommand(
            $queueConsumer,
            $destMetaRegistry,
            $consumerState,
            $this->createMock(LoggerInterface::class)
        );

        $input = new ArrayInput(['queue' => 'default', '--message-limit' => '1']);
        $output = new BufferedOutput();

        $status = $command->run($input, $output);

        self::assertSame(0, $status);
    }

    public function testExecuteWithProcessorOption(): void
    {
        $dest = new DestinationMeta('default', 'oro.default', ['processor1']);
        $destMetaRegistry = $this->createMock(DestinationMetaRegistry::class);
        $destMetaRegistry->method('getDestinationMeta')->with('default')->willReturn($dest);

        $queueConsumer = $this->createMock(QueueConsumer::class);
        $queueConsumer->expects(self::once())->method('bind')->with('oro.default', 'my_processor');
        $queueConsumer->expects(self::once())->method('consume')->willReturn(null);
        $queueConsumer->method('getConnection')->willReturn($this->createMock(\Oro\Component\MessageQueue\Transport\ConnectionInterface::class));

        $consumerState = $this->createMock(ConsumerState::class);
        $consumerState->method('startConsumption');
        $consumerState->method('stopConsumption');

        $command = new MessageQueueProcessCommand(
            $queueConsumer,
            $destMetaRegistry,
            $consumerState,
            $this->createMock(LoggerInterface::class)
        );

        $input = new ArrayInput(['queue' => 'default', '--processor' => 'my_processor', '--message-limit' => '1']);
        $output = new BufferedOutput();

        $status = $command->run($input, $output);

        self::assertSame(0, $status);
    }

    public function testExecuteFailsWithInvalidTimeLimit(): void
    {
        $dest = new DestinationMeta('default', 'oro.default', []);
        $destMetaRegistry = $this->createMock(DestinationMetaRegistry::class);
        $destMetaRegistry->method('getDestinationMeta')->willReturn($dest);

        $queueConsumer = $this->createMock(QueueConsumer::class);
        $queueConsumer->method('bind');
        $queueConsumer->expects(self::never())->method('consume');

        $consumerState = $this->createMock(ConsumerState::class);

        $command = new MessageQueueProcessCommand(
            $queueConsumer,
            $destMetaRegistry,
            $consumerState,
            $this->createMock(LoggerInterface::class)
        );

        $input = new ArrayInput(['queue' => 'default', '--time-limit' => 'invalid', '--message-limit' => '1']);
        $output = new BufferedOutput();

        $status = $command->run($input, $output);

        self::assertSame(1, $status);
        self::assertStringContainsString('Invalid time-limit', $output->fetch());
    }
}
