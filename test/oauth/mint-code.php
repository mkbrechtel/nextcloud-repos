<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Test helper (issue 28): mints an OAuth2 authorization code for a user the
 * same way Nextcloud's consent screen does, so the token exchange and git
 * authentication can be verified without driving the browser UI.
 *
 * Usage (inside the container, as www-data):
 *   php mint-code.php <client_id> <uid>
 */

require_once '/var/www/html/lib/base.php';

use OCA\OAuth2\Db\AccessToken;
use OCA\OAuth2\Db\AccessTokenMapper;
use OCA\OAuth2\Db\ClientMapper;
use OCP\Authentication\Token\IProvider as ITokenProvider;
use OCP\Authentication\Token\IToken;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;

$clientId = $argv[1] ?? '';
$uid = $argv[2] ?? 'admin';
if ($clientId === '') {
	fwrite(STDERR, "usage: mint-code.php <client_id> <uid>\n");
	exit(1);
}

$random = \OCP\Server::get(ISecureRandom::class);
$crypto = \OCP\Server::get(ICrypto::class);
$tokenProvider = \OCP\Server::get(ITokenProvider::class);
$clientMapper = \OCP\Server::get(ClientMapper::class);
$accessTokenMapper = \OCP\Server::get(AccessTokenMapper::class);

$client = $clientMapper->getByIdentifier($clientId);

$appToken = $random->generate(72, ISecureRandom::CHAR_UPPER . ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_DIGITS);
$generated = $tokenProvider->generateToken(
	$appToken,
	$uid,
	$uid,
	null,
	$client->getName(),
	IToken::PERMANENT_TOKEN,
	IToken::DO_NOT_REMEMBER,
);

$code = $random->generate(128, ISecureRandom::CHAR_UPPER . ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_DIGITS);
$accessToken = new AccessToken();
$accessToken->setClientId($client->getId());
$accessToken->setEncryptedToken($crypto->encrypt($appToken, $code));
$accessToken->setHashedCode(hash('sha512', $code));
$accessToken->setTokenId($generated->getId());
$accessToken->setCodeCreatedAt(time());
$accessTokenMapper->insert($accessToken);

echo $code, "\n";
