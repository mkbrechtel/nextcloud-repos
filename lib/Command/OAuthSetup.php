<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Command;

use OC\Core\Command\Base;
use OCA\OAuth2\Db\Client;
use OCA\OAuth2\Db\ClientMapper;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Registers the OAuth2 client git clients use, and prints the git config
 * that drives git-credential-oauth against this instance (issue 28).
 */
class OAuthSetup extends Base {
	private const CLIENT_NAME = 'Git (Nextcloud Repositories)';
	private const REDIRECT_URI = 'http://localhost:*';
	private const VALID_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

	public function __construct(
		private readonly IConfig $config,
		private readonly IURLGenerator $urlGenerator,
		private readonly ISecureRandom $secureRandom,
		private readonly ICrypto $crypto,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('repos:oauth:setup')
			->setDescription('Register the OAuth2 client for git access and print the client configuration')
			->addOption('recreate', null, InputOption::VALUE_NONE, 'Replace an existing git OAuth2 client with a fresh one (invalidates the old secret)');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		if (!class_exists(ClientMapper::class)) {
			$output->writeln('<error>The oauth2 app is not available. Enable it with: occ app:enable oauth2</error>');
			return 1;
		}

		/** @var ClientMapper $clientMapper */
		$clientMapper = \OCP\Server::get(ClientMapper::class);

		// localhost wildcard redirects: git credential helpers listen on a
		// random loopback port for the authorization code
		$this->config->setSystemValue('oauth2.enable_oc_clients', true);

		$existing = null;
		foreach ($clientMapper->getClients() as $client) {
			if ($client->getName() === self::CLIENT_NAME) {
				$existing = $client;
				break;
			}
		}

		if ($existing !== null && !$input->getOption('recreate')) {
			$output->writeln('<comment>A git OAuth2 client already exists; its secret cannot be read back.</comment>');
			$output->writeln('<comment>Run with --recreate to issue a new one.</comment>');
			$output->writeln('');
			$this->printConfig($output, $existing->getClientIdentifier(), '<client secret from the original setup>');
			return 0;
		}

		if ($existing !== null) {
			$clientMapper->delete($existing);
		}

		$secret = $this->secureRandom->generate(64, self::VALID_CHARS);
		$client = new Client();
		$client->setName(self::CLIENT_NAME);
		$client->setRedirectUri(self::REDIRECT_URI);
		$client->setSecret(bin2hex($this->crypto->calculateHMAC($secret)));
		$client->setClientIdentifier($this->secureRandom->generate(64, self::VALID_CHARS));
		$client = $clientMapper->insert($client);

		$this->printConfig($output, $client->getClientIdentifier(), $secret);
		return 0;
	}

	private function printConfig(OutputInterface $output, string $clientId, string $secret): void {
		$base = rtrim($this->urlGenerator->getAbsoluteURL(''), '/');
		$host = parse_url($base, PHP_URL_SCHEME) . '://' . parse_url($base, PHP_URL_HOST);

		$output->writeln('<info>OAuth2 client for git access</info>');
		$output->writeln('');
		$output->writeln('Client ID:     ' . $clientId);
		$output->writeln('Client secret: ' . $secret);
		$output->writeln('');
		$output->writeln('<info>Client setup — install git-credential-oauth, then run:</info>');
		$output->writeln('');
		$lines = [
			sprintf('git config --global credential.%s.oauthClientId %s', $host, $clientId),
			sprintf('git config --global credential.%s.oauthClientSecret %s', $host, $secret),
			sprintf('git config --global credential.%s.oauthAuthURL %s', $host, $base . '/apps/repos/oauth/authorize'),
			sprintf('git config --global credential.%s.oauthTokenURL %s', $host, $base . '/index.php/apps/oauth2/api/v1/token'),
			sprintf('git config --global --add credential.helper oauth'),
		];
		foreach ($lines as $line) {
			$output->writeln('  ' . $line);
		}
		$output->writeln('');
		$output->writeln('<comment>Cloning then opens a browser once; tokens refresh automatically.</comment>');
		$output->writeln('<comment>App passwords keep working — both methods are supported.</comment>');
	}
}
