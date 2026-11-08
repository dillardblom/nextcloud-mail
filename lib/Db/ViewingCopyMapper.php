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
 * @template-extends QBMapper<ViewingCopy>
 */
class ViewingCopyMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'mail_viewing_copies', ViewingCopy::class);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function find(string $userId, int $messageId, string $attachmentId): ViewingCopy {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->eq('message_id', $qb->createNamedParameter($messageId, IQueryBuilder::PARAM_INT), IQueryBuilder::PARAM_INT),
				$qb->expr()->eq('attachment_id', $qb->createNamedParameter($attachmentId)),
			);

		return $this->findEntity($qb);
	}

	/**
	 * Files of which no copy was used since the given time; several messages can share one file
	 *
	 * @return ViewingCopy[] one copy per file
	 * @throws Exception
	 */
	public function findUnusedSince(int $time, int $limit): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id', 'file_id')
			->selectAlias($qb->func()->min('file_mtime'), 'file_mtime')
			->selectAlias($qb->func()->min('file_size'), 'file_size')
			->from($this->getTableName())
			->groupBy('user_id', 'file_id')
			->having($qb->expr()->lt($qb->func()->max('last_used_at'), $qb->createNamedParameter($time, IQueryBuilder::PARAM_INT), IQueryBuilder::PARAM_INT))
			->setMaxResults($limit);

		$copies = [];
		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			$copy = new ViewingCopy();
			$copy->setUserId((string)$row['user_id']);
			$copy->setFileId((int)$row['file_id']);
			$copy->setFileMtime((int)$row['file_mtime']);
			$copy->setFileSize((int)$row['file_size']);
			$copies[] = $copy;
		}
		$result->closeCursor();
		return $copies;
	}

	/**
	 * @throws Exception
	 */
	public function deleteByFile(string $userId, int $fileId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT), IQueryBuilder::PARAM_INT),
			);
		$qb->executeStatement();
	}
}
