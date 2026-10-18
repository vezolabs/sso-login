<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Vezolabs\SsoLoginBundle\Repository\IdentityProviderRepository;
use Vezolabs\SsoLoginBundle\Service\Diagnostics\SsoDiagnosticsService;
use Vezolabs\SsoLoginBundle\Service\Saml\SamlConfigurationValidator;

#[AsCommand(
    name: 'vezolabs:sso:test',
    description: 'Test and diagnose SAML SSO provider configurations'
)]
class SsoTestCommand extends Command
{
    public function __construct(
        private readonly IdentityProviderRepository $providerRepository,
        private readonly SamlConfigurationValidator $validator,
        private readonly SsoDiagnosticsService $diagnosticsService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('provider', InputArgument::OPTIONAL, 'Provider name slug (e.g. entra-id)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $providerName = $input->getArgument('provider');

        $providers = $providerName !== null
            ? array_filter([$this->providerRepository->findByName($providerName)])
            : $this->providerRepository->findAll();

        if (empty($providers)) {
            $io->error($providerName ? sprintf('Provider "%s" not found.', $providerName) : 'No SSO providers configured.');
            return Command::FAILURE;
        }

        $io->title('VezoLabs SSO Login — Configuration Diagnostics');

        $overallSuccess = true;

        foreach ($providers as $provider) {
            $io->section(sprintf('Provider: %s (%s) [Status: %s]',
                $provider->getDisplayName(),
                $provider->getName(),
                $provider->isEnabled() ? '<fg=green>ENABLED</>' : '<fg=yellow>DISABLED</>'
            ));

            $validation = $this->validator->validate($provider);

            $table = new Table($output);
            $table->setHeaders(['Check', 'Status', 'Details']);

            foreach ($validation['checks'] as $check) {
                $status = $check['passed'] ? '<fg=green>PASS</>' : ($check['severity'] === 'error' ? '<fg=red>FAIL</>' : '<fg=yellow>WARN</>');
                $table->addRow([$check['name'], $status, $check['message']]);
            }

            $table->render();

            if (!$validation['valid']) {
                $overallSuccess = false;
                $io->error(sprintf('Provider has %d configuration errors.', \count($validation['errors'])));
            } elseif (!empty($validation['warnings'])) {
                $io->warning(sprintf('Provider has %d configuration warnings.', \count($validation['warnings'])));
            } else {
                $io->success('All security and configuration checks passed.');
            }
        }

        return $overallSuccess ? Command::SUCCESS : Command::FAILURE;
    }
}
