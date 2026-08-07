<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Git\Native;

use OCP\IDBConnection;

/**
 * Refs and the commit index live in the database (issue 29).
 */
class RefStore {
	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	/**
	 * @return array<string, string> ref => sha
	 */
	public function all(int $folderId): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('ref', 'sha')->from('repos_refs')
			->where($qb->expr()->eq('folder_id', $qb->createNamedParameter($folderId)))
			->executeQuery();
		$refs = [];
		while ($row = $result->fetch()) {
			$refs[$row['ref']] = $row['sha'];
		}
		$result->closeCursor();
		return $refs;
	}

	public function get(int $folderId, string $ref): ?string {
		return $this->all($folderId)[$ref] ?? null;
	}

	public function set(int $folderId, string $ref, string $sha): void {
		$this->db->executeStatement(
			'DELETE FROM `*PREFIX*repos_refs` WHERE `folder_id` = ? AND `ref` = ?',
			[$folderId, $ref],
		);
		$qb = $this->db->getQueryBuilder();
		$qb->insert('repos_refs')->values([
			'folder_id' => $qb->createNamedParameter($folderId),
			'ref' => $qb->createNamedParameter($ref),
			'sha' => $qb->createNamedParameter($sha),
		])->executeStatement();
	}

	public function delete(int $folderId, string $ref): void {
		$this->db->executeStatement(
			'DELETE FROM `*PREFIX*repos_refs` WHERE `folder_id` = ? AND `ref` = ?',
			[$folderId, $ref],
		);
	}

	public function deleteAll(int $folderId): void {
		$this->db->executeStatement('DELETE FROM `*PREFIX*repos_refs` WHERE `folder_id` = ?', [$folderId]);
		$this->db->executeStatement('DELETE FROM `*PREFIX*repos_commits` WHERE `folder_id` = ?', [$folderId]);
	}

	// ---- commit index ------------------------------------------------------

	public function indexCommit(int $folderId, string $sha, array $commit): void {
		$this->db->executeStatement(
			'DELETE FROM `*PREFIX*repos_commits` WHERE `folder_id` = ? AND `sha` = ?',
			[$folderId, $sha],
		);
		$qb = $this->db->getQueryBuilder();
		$qb->insert('repos_commits')->values([
			'folder_id' => $qb->createNamedParameter($folderId),
			'sha' => $qb->createNamedParameter($sha),
			'parents' => $qb->createNamedParameter(implode(',', $commit['parents'])),
			'author_name' => $qb->createNamedParameter(mb_substr($commit['authorName'], 0, 250)),
			'author_email' => $qb->createNamedParameter(mb_substr($commit['authorEmail'], 0, 250)),
			'created_at' => $qb->createNamedParameter($commit['timestamp']),
			'subject' => $qb->createNamedParameter(mb_substr(explode("\n", $commit['message'])[0], 0, 500)),
		])->executeStatement();
	}
}
