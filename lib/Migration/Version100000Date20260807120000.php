<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Native git backend (issue 29): refs and the commit index in the database.
 */
class Version100000Date20260807120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('repos_refs')) {
			$table = $schema->createTable('repos_refs');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('folder_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('ref', Types::STRING, ['notnull' => true, 'length' => 250]);
			$table->addColumn('sha', Types::STRING, ['notnull' => true, 'length' => 40]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['folder_id', 'ref'], 'repos_refs_folder_ref');
		}

		if (!$schema->hasTable('repos_commits')) {
			$table = $schema->createTable('repos_commits');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('folder_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('sha', Types::STRING, ['notnull' => true, 'length' => 40]);
			$table->addColumn('parents', Types::TEXT, ['notnull' => false]);
			$table->addColumn('author_name', Types::STRING, ['notnull' => false, 'length' => 250]);
			$table->addColumn('author_email', Types::STRING, ['notnull' => false, 'length' => 250]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$table->addColumn('subject', Types::STRING, ['notnull' => false, 'length' => 500]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['folder_id', 'sha'], 'repos_commits_folder_sha');
		}

		return $schema;
	}
}
