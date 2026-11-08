<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<MessageScan>
 */
class MessageScanMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'mail_message_scans', MessageScan::class);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function findByMessageId(int $messageId): MessageScan {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('message_id', $qb->createNamedParameter($messageId, IQueryBuilder::PARAM_INT), IQueryBuilder::PARAM_INT));

		return $this->findEntity($qb);
	}
}
