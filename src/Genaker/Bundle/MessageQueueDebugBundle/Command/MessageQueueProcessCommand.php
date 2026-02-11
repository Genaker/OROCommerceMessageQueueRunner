<?php

namespace Genaker\Bundle\MessageQueueDebugBundle\Command;

use Oro\Bundle\MessageQueueBundle\Consumption\Extension\ChainExtension;
use Oro\Component\MessageQueue\Consumption\Extension\LimitConsumedMessagesExtension;
use Oro\Component\MessageQueue\Consumption\Extension\LoggerExtension;
use Oro\Component\MessageQueue\Consumption\Extension\LimitConsumptionTimeExtension;
use Oro\Component\MessageQueue\Consumption\ExtensionInterface;
use Oro\Component\MessageQueue\Consumption\QueueConsumer;
use Oro\Component\MessageQueue\Client\Meta\DestinationMetaRegistry;
use Oro\Component\MessageQueue\Log\ConsumerState;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Run message queue processor for debugging - process one or more messages from a specific queue.
 */
#[AsCommand(
    name: 'genaker:mq:process',
    description: 'Process message(s) from queue for debugging (single processor, optional message limit)'
)]
class MessageQueueProcessCommand extends Command
{
    public function __construct(
        private QueueConsumer $queueConsumer,
        private DestinationMetaRegistry $destinationMetaRegistry,
        private ConsumerState $consumerState,
        private LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('queue', InputArgument::OPTIONAL, 'Queue/destination name to consume from (e.g. default)')
            ->addOption('processor', 'p', InputOption::VALUE_OPTIONAL, 'Process only with this processor (service id)')
            ->addOption('message-limit', 'm', InputOption::VALUE_REQUIRED, 'Process N messages and exit', '1')
            ->addOption('time-limit', 't', InputOption::VALUE_REQUIRED, 'Exit after this time (e.g. 0:0:30)')
            ->setHelp(
                <<<'HELP'
Run a single processor for debugging. Use with <info>--message-limit=1</info> to process one message and exit.

Examples:

  Process one message from default queue:
    <info>php %command.full_name% -m 1</info>

  Process from specific queue:
    <info>php %command.full_name% default -m 1</info>

  Process with specific processor (when queue has multiple):
    <info>php %command.full_name% default -p oro_message_queue.async.unique_message_processor -m 1</info>

  Process up to 5 messages:
    <info>php %command.full_name% -m 5</info>

HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $clientDestinationName = $input->getArgument('queue');
        $processorName = $input->getOption('processor') ?? '';
        $messageLimit = (int) $input->getOption('message-limit');

        if ($clientDestinationName) {
            $dest = $this->destinationMetaRegistry->getDestinationMeta($clientDestinationName);
            $this->queueConsumer->bind($dest->getTransportQueueName(), $processorName);
        } else {
            foreach ($this->destinationMetaRegistry->getDestinationsMeta() as $dest) {
                $this->queueConsumer->bind($dest->getTransportQueueName(), $processorName);
            }
        }

        $extensions = [
            new LoggerExtension(new \Symfony\Component\Console\Logger\ConsoleLogger($output)),
        ];
        if ($messageLimit > 0) {
            $extensions[] = new LimitConsumedMessagesExtension($messageLimit);
        }

        $timeLimit = $input->getOption('time-limit');
        if ($timeLimit) {
            try {
                $extensions[] = new LimitConsumptionTimeExtension(new \DateTime($timeLimit));
            } catch (\Throwable $e) {
                $output->writeln('<error>Invalid time-limit: ' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }
        }

        $this->consumerState->startConsumption();
        try {
            $this->queueConsumer->consume(new ChainExtension($extensions, $this->consumerState));
        } finally {
            $this->consumerState->stopConsumption();
            $this->queueConsumer->getConnection()->close();
        }

        return Command::SUCCESS;
    }
}
