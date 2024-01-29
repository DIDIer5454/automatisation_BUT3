<?php

declare(strict_types=1);

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PrivateBin\Data;

/**
 * AbstractData.
 *
 * Abstract model for data access
 */
abstract class AbstractData
{
    /**
     * cache for the traffic limiter.
     *
     * @var array
     */
    protected $_last_cache = [];

    /**
     * Create a paste.
     *
     * @param string $pasteid
     *
     * @return bool
     */
    abstract public function create($pasteid, array $paste);

    /**
     * Read a paste.
     *
     * @param string $pasteid
     *
     * @return array|false
     */
    abstract public function read($pasteid);

    /**
     * Delete a paste and its discussion.
     *
     * @param string $pasteid
     */
    abstract public function delete($pasteid);

    /**
     * Test if a paste exists.
     *
     * @param string $pasteid
     *
     * @return bool
     */
    abstract public function exists($pasteid);

    /**
     * Create a comment in a paste.
     *
     * @param string $pasteid
     * @param string $parentid
     * @param string $commentid
     *
     * @return bool
     */
    abstract public function createComment($pasteid, $parentid, $commentid, array $comment);

    /**
     * Read all comments of paste.
     *
     * @param string $pasteid
     *
     * @return array
     */
    abstract public function readComments($pasteid);

    /**
     * Test if a comment exists.
     *
     * @param string $pasteid
     * @param string $parentid
     * @param string $commentid
     *
     * @return bool
     */
    abstract public function existsComment($pasteid, $parentid, $commentid);

    /**
     * Purge outdated entries.
     *
     * @param string $namespace
     * @param int    $time
     */
    public function purgeValues($namespace, $time): void
    {
        if ('traffic_limiter' === $namespace) {
            foreach ($this->_last_cache as $key => $last_submission) {
                if ($last_submission <= $time) {
                    unset($this->_last_cache[$key]);
                }
            }
        }
    }

    /**
     * Save a value.
     *
     * @param string $value
     * @param string $namespace
     * @param string $key
     *
     * @return bool
     */
    abstract public function setValue($value, $namespace, $key = '');

    /**
     * Load a value.
     *
     * @param string $namespace
     * @param string $key
     *
     * @return string
     */
    abstract public function getValue($namespace, $key = '');

    /**
     * Perform a purge of old pastes, at most the given batchsize is deleted.
     *
     * @param int $batchsize
     */
    public function purge($batchsize): void
    {
        if ($batchsize < 1) {
            return;
        }
        $pastes = $this->_getExpiredPastes($batchsize);
        if (\count($pastes)) {
            foreach ($pastes as $pasteid) {
                $this->delete($pasteid);
            }
        }
    }

    /**
     * Returns all paste ids.
     *
     * @return array
     */
    abstract public function getAllPastes();

    /**
     * Returns up to batch size number of paste ids that have expired.
     *
     * @param int $batchsize
     *
     * @return array
     */
    abstract protected function _getExpiredPastes($batchsize);

    /**
     * Get next free slot for comment from postdate.
     *
     * @param int|string $postdate
     *
     * @return int|string
     */
    protected function getOpenSlot(array &$comments, $postdate)
    {
        if (\array_key_exists($postdate, $comments)) {
            $parts = explode('.', $postdate, 2);
            if (!\array_key_exists(1, $parts)) {
                $parts[1] = 0;
            }
            ++$parts[1];

            return $this->getOpenSlot($comments, implode('.', $parts));
        }

        return $postdate;
    }

    /**
     * Upgrade pre-version 1 pastes with attachment to version 1 format.
     *
     * @static
     *
     * @return array
     */
    protected static function upgradePreV1Format(array $paste)
    {
        if (\array_key_exists('attachment', $paste['meta'])) {
            $paste['attachment'] = $paste['meta']['attachment'];
            unset($paste['meta']['attachment']);
            if (\array_key_exists('attachmentname', $paste['meta'])) {
                $paste['attachmentname'] = $paste['meta']['attachmentname'];
                unset($paste['meta']['attachmentname']);
            }
        }

        return $paste;
    }
}
