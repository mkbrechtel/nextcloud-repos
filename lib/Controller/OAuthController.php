<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IURLGenerator;

/**
 * Authorization entry point for git credential helpers (issue 28).
 *
 * Native CLI clients listen on a random loopback port and pass
 * `http://127.0.0.1:<port>` as the redirect URI (RFC 8252). Nextcloud's
 * wildcard client accepts loopback redirects only under the literal host
 * `localhost`, so this endpoint validates the loopback URI and normalizes
 * the host before handing over to Nextcloud's own consent screen.
 */
class OAuthController extends Controller {
	private const LOOPBACK = '/^http:\/\/(127\.0\.0\.1|localhost|\[::1\]):([0-9]{1,5})$/';

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IURLGenerator $urlGenerator,
	) {
		parent::__construct($appName, $request);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function authorize(
		string $client_id = '',
		string $redirect_uri = '',
		string $state = '',
		string $response_type = 'code',
	): Response {
		if ($client_id === '' || $redirect_uri === '') {
			return new DataResponse(['error' => 'invalid_request'], Http::STATUS_BAD_REQUEST);
		}
		if (preg_match(self::LOOPBACK, rtrim($redirect_uri, '/'), $matches) !== 1) {
			return new DataResponse(
				['error' => 'invalid_request', 'error_description' => 'redirect_uri must be a loopback address with a port'],
				Http::STATUS_BAD_REQUEST,
			);
		}

		$normalized = 'http://localhost:' . $matches[2];
		$target = $this->urlGenerator->linkToRouteAbsolute('oauth2.LoginRedirector.authorize') . '?' . http_build_query([
			'response_type' => $response_type,
			'client_id' => $client_id,
			'state' => $state,
			'redirect_uri' => $normalized,
		]);
		return new RedirectResponse($target);
	}
}
