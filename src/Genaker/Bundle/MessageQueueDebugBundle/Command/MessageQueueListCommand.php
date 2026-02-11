<?php

namespace Genaker\Bundle\MessageQueueDebugBundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Component\MessageQueue\Client\Meta\DestinationMetaRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;

/**
 * Lists all message queue jobs (destinations) with their processors and message counts.
 */
#[AsCommand(
    name: 'genaker:mq:list',
    description: 'List all message queue destinations with processors and message counts'
)]
class MessageQueueListCommand extends Command
{
    public function __construct(
        private DestinationMetaRegistry $destinationMetaRegistry,
        private ManagerRegistry $doctrine,
        private string $tableName,
        private string $mqConnectionName,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('queue', null, InputOption::VALUE_OPTIONAL, 'Filter by queue/destination name')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filterQueue = $input->getOption('queue');
        $asJson = $input->getOption('json');

        $messageCounts = $this->getMessageCountsByQueue();

        $rows = [];
        foreach ($this->destinationMetaRegistry->getDestinationsMeta() as $destinationMeta) {
            $queueName = $destinationMeta->getQueueName();
            if ($filterQueue && stripos($queueName, $filterQueue) === false) {
                continue;
            }

            $transportQueue = $destinationMeta->getTransportQueueName();
            $count = $messageCounts[$transportQueue] ?? 0;
            $processors = $destinationMeta->getMessageProcessors();

            foreach ($processors as $processor) {
                $rows[] = [
                    'queue' => $queueName,
                    'transport_queue' => $transportQueue,
                    'processor' => $processor,
                    'messages' => $count,
                ];
            }

            if ($processors === []) {
                $rows[] = [
                    'queue' => $queueName,
                    'transport_queue' => $transportQueue,
                    'processor' => '(none)',
                    'messages' => $count,
                ];
            }
        }

        if ($asJson) {
            $output->writeln(json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        if ($rows === []) {
            $io->note('No destinations found.');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Queue', 'Transport Queue', 'Processor', 'Messages']);
        foreach ($rows as $row) {
            $table->addRow([$row['queue'], $row['transport_queue'], $row['processor'], $row['messages']]);
        }
        $table->render();

        $total = array_sum(array_column($rows, 'messages'));
        if ($total > 0) {
            $io->note(sprintf('Total messages in queue: %d', $total));
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function getMessageCountsByQueue(): array
    {
        try {
            $conn = $this->doctrine->getConnection($this->mqConnectionName);
        } catch (\Throwable) {
            $conn = $this->doctrine->getConnection();
        }
        try {
            $schemaManager = method_exists($conn, 'createSchemaManager')
                ? $conn->createSchemaManager()
                : $conn->getSchemaManager();
            if (!$schemaManager->tablesExist([$this->tableName])) {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }

        $sql = sprintf(
            'SELECT queue, COUNT(*) as cnt FROM %s WHERE consumer_id IS NULL AND (delayed_until IS NULL OR delayed_until <= :now) GROUP BY queue',
            $conn->getDatabasePlatform()->quoteIdentifier($this->tableName)
        );

        $result = $conn->fetchAllAssociative($sql, ['now' => time()]);
        $counts = [];
        foreach ($result as $row) {
            $counts[$row['queue']] = (int) $row['cnt'];
        }

        return $counts;
    }
}
