<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * @psalm-api
 */
class Version5130Date20261108000000 extends SimpleMigrationStep {
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if ($schema->hasTable('mail_viewing_copies')) {
			return null;
		}

		$table = $schema->createTable('mail_viewing_copies');
		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('user_id', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('message_id', Types::BIGINT, [
			'notnull' => true,
		]);
		$table->addColumn('attachment_id', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('file_id', Types::BIGINT, [
			'notnull' => true,
		]);
		$table->addColumn('file_mtime', Types::BIGINT, [
			'notnull' => true,
		]);
		$table->addColumn('file_size', Types::BIGINT, [
			'notnull' => true,
		]);
		$table->addColumn('last_used_at', Types::BIGINT, [
			'notnull' => true,
		]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['user_id', 'message_id', 'attachment_id'], 'mail_viewcopy_msg_att_uidx');
		$table->addIndex(['user_id', 'file_id'], 'mail_viewcopy_file_idx');

		return $schema;
	}
}
