<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Vezolabs\SsoLoginBundle\Repository\IdentityProviderRepository;
use Vezolabs\SsoLoginBundle\Service\Saml\CertificateManager;

#[AsCommand(
    name: 'vezolabs:sso:rotate-cert',
    description: 'Promote rollover certificate to primary signing certificate for a provider'
)]
class SsoRotateCertCommand extends Command
{
    public function __construct(
        private readonly IdentityProviderRepository $providerRepository,
        private readonly CertificateManager $certificateManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('provider', InputArgument::REQUIRED, 'Provider name slug (e.g. entra-id)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $providerName = $input->getArgument('provider');

        $provider = $this->providerRepository->findByName($providerName);
        if ($provider === null) {
            $io->error(sprintf('Provider "%s" not found.', $providerName));
            return Command::FAILURE;
        }

        $rolloverCert = $provider->getIdpX509CertRollover();
        if (empty($rolloverCert)) {
            $io->error(sprintf('Provider "%s" has no rollover certificate configured to promote.', $providerName));
            return Command::FAILURE;
        }

        $rolloverInfo = $this->certificateManager->inspect($rolloverCert);
        $io->section(sprintf('Promoting Rollover Certificate (Subject: %s, Expires: %s)', $rolloverInfo['subject'], $rolloverInfo['valid_to']));

        $provider->setIdpX509Cert($rolloverCert);
        $provider->setIdpX509CertRollover(null);

        $this->providerRepository->save($provider, 'cli', 'ROTATE_CERT');

        $io->success(sprintf('Certificate successfully rotated for provider "%s".', $provider->getDisplayName()));

        return Command::SUCCESS;
    }
}
