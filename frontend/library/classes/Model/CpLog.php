<?php
/**
 * i-MSCP - internet Multi Server Control Panel
 * Copyright (C) 2010-2018 by Laurent Declercq <l.declercq@nuxwin.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

namespace iMSCP\Model;

/**
 * Class CpLog
 * @package iMSCP\Model
 */
class CpLog extends BaseModel
{
    /**
     * @var int
     */
    private $cpLogID;

    /**
     * @var \DateTimeImmutable
     */
    private $logTime;

    /**
     * @var string
     */
    private $log;

    /**
     * @return int
     */
    public function getCpLogID(): int
    {
        return $this->cpLogID;
    }

    /**
     * @param int $cpLogID
     * @return CpLog
     */
    public function setCpLogID(int $cpLogID): CpLog
    {
        $this->cpLogID = $cpLogID;
        return $this;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getLogTime(): \DateTimeImmutable
    {
        return $this->logTime;
    }

    /**
     * @param \DateTimeImmutable $logTime
     * @return CpLog
     */
    public function setLogTime(\DateTimeImmutable $logTime): CpLog
    {
        $this->logTime = $logTime;
        return $this;
    }

    /**
     * @return string
     */
    public function getLog(): string
    {
        return $this->log;
    }

    /**
     * @param string $log
     * @return CpLog
     */
    public function setLog(string $log): CpLog
    {
        $this->log = $log;
        return $this;
    }
}