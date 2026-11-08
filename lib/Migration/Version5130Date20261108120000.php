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
class Version5130Date20261108120000 extends SimpleMigrationStep {
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if ($schema->hasTable('mail_message_scans')) {
			return null;
		}

		$table = $schema->createTable('mail_message_scans');
		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('message_id', Types::BIGINT, [
			'notnull' => true,
		]);
		$table->addColumn('status', Types::SMALLINT, [
			'notnull' => true,
		]);
		$table->addColumn('signature', Types::STRING, [
			'notnull' => false,
			'length' => 255,
		]);
		$table->addColumn('scanned_at', Types::BIGINT, [
			'notnull' => true,
		]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['message_id'], 'mail_msgscan_msg_uidx');
		$table->addForeignKeyConstraint(
			$schema->getTable('mail_messages'),
			['message_id'],
			['id'],
			['onDelete' => 'CASCADE'],
			'mail_msgscan_msg_fk',
		);

		return $schema;
	}
}
