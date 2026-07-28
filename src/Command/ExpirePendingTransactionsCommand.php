<?php

namespace App\Command;

use App\Service\TransactionExpiryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsCommand(
    name: 'app:transactions:expire',
    description: 'Blocks transactions left in PENDING for too long',
)]
final class ExpirePendingTransactionsCommand extends Command
{
    public function __construct(
        private readonly TransactionExpiryService $expiry
    ) {
        parent::__construct();
    }

    /**
     * @throws ExceptionInterface
     * @throws \DateMalformedStringException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('Stale transactions sent for blocking: %d', $this->expiry->expire()));

        return Command::SUCCESS;
    }
}
