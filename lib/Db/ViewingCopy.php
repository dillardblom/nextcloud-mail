<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Db;

use OCP\AppFramework\Db\Entity;

/**
 * A copy of an attachment that Mail put in the user's files to open it there
 *
 * @method void setUserId(string $userId)
 * @method string getUserId()
 * @method void setMessageId(int $messageId)
 * @method int getMessageId()
 * @method void setAttachmentId(string $attachmentId)
 * @method string getAttachmentId()
 * @method void setFileId(int $fileId)
 * @method int getFileId()
 * @method void setFileMtime(int $fileMtime)
 * @method int getFileMtime()
 * @method void setFileSize(int $fileSize)
 * @method int getFileSize()
 * @method void setLastUsedAt(int $lastUsedAt)
 * @method int getLastUsedAt()
 */
class ViewingCopy extends Entity {
	protected $userId;
	protected $messageId;
	protected $attachmentId;
	protected $fileId;
	protected $fileMtime;
	protected $fileSize;
	protected $lastUsedAt;

	public function __construct() {
		$this->addType('userId', 'string');
		$this->addType('messageId', 'integer');
		$this->addType('attachmentId', 'string');
		$this->addType('fileId', 'integer');
		$this->addType('fileMtime', 'integer');
		$this->addType('fileSize', 'integer');
		$this->addType('lastUsedAt', 'integer');
	}
}
