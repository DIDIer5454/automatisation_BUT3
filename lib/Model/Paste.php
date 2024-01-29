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

namespace PrivateBin\Model;

use Exception;
use PrivateBin\Controller;
use PrivateBin\Persistence\ServerSalt;

/**
 * Paste.
 *
 * Model of a PrivateBin paste.
 */
class Paste extends AbstractModel
{
    /**
     * Get paste data.
     *
     * @throws Exception
     *
     * @return array
     */
    public function get()
    {
        $data = $this->_store->read($this->getId());
        if (false === $data) {
            throw new Exception(Controller::GENERIC_ERROR, 64);
        }

        // check if paste has expired and delete it if neccessary.
        if (\array_key_exists('expire_date', $data['meta'])) {
            if ($data['meta']['expire_date'] < time()) {
                $this->delete();

                throw new Exception(Controller::GENERIC_ERROR, 63);
            }
            // We kindly provide the remaining time before expiration (in seconds)
            $data['meta']['time_to_live'] = $data['meta']['expire_date'] - time();
            unset($data['meta']['expire_date']);
        }

        // check if non-expired burn after reading paste needs to be deleted
        if ((\array_key_exists('adata', $data) && 1 === $data['adata'][3])
            || (\array_key_exists('burnafterreading', $data['meta']) && $data['meta']['burnafterreading'])
        ) {
            $this->delete();
        }

        // set formatter for the view in version 1 pastes.
        if (\array_key_exists('data', $data) && !\array_key_exists('formatter', $data['meta'])) {
            // support < 0.21 syntax highlighting
            if (\array_key_exists('syntaxcoloring', $data['meta']) && true === $data['meta']['syntaxcoloring']) {
                $data['meta']['formatter'] = 'syntaxhighlighting';
            } else {
                $data['meta']['formatter'] = $this->_conf->getKey('defaultformatter');
            }
        }

        // support old paste format with server wide salt
        if (!\array_key_exists('salt', $data['meta'])) {
            $data['meta']['salt'] = ServerSalt::get();
        }
        $data['comments'] = array_values($this->getComments());
        $data['comment_count'] = \count($data['comments']);
        $data['comment_offset'] = 0;
        $data['@context'] = '?jsonld=paste';
        $this->_data = $data;

        return $this->_data;
    }

    /**
     * Store the paste's data.
     *
     * @throws Exception
     */
    public function store(): void
    {
        // Check for improbable collision.
        if ($this->exists()) {
            throw new Exception('You are unlucky. Try again.', 75);
        }

        $this->_data['meta']['created'] = time();
        $this->_data['meta']['salt'] = ServerSalt::generate();

        // store paste
        if (false === $this->_store->create(
            $this->getId(),
            $this->_data
        )
        ) {
            throw new Exception('Error saving paste. Sorry.', 76);
        }
    }

    /**
     * Delete the paste.
     *
     * @throws Exception
     */
    public function delete(): void
    {
        $this->_store->delete($this->getId());
    }

    /**
     * Test if paste exists in store.
     *
     * @return bool
     */
    public function exists()
    {
        return $this->_store->exists($this->getId());
    }

    /**
     * Get a comment, optionally a specific instance.
     *
     * @param string $parentId
     * @param string $commentId
     *
     * @throws Exception
     *
     * @return Comment
     */
    public function getComment($parentId, $commentId = '')
    {
        if (!$this->exists()) {
            throw new Exception('Invalid data.', 62);
        }
        $comment = new Comment($this->_conf, $this->_store);
        $comment->setPaste($this);
        $comment->setParentId($parentId);
        if ('' !== $commentId) {
            $comment->setId($commentId);
        }

        return $comment;
    }

    /**
     * Get all comments, if any.
     *
     * @return array
     */
    public function getComments()
    {
        return $this->_store->readComments($this->getId());
    }

    /**
     * Generate the "delete" token.
     *
     * The token is the hmac of the pastes ID signed with the server salt.
     * The paste can be deleted by calling:
     * https://example.com/privatebin/?pasteid=<pasteid>&deletetoken=<deletetoken>
     *
     * @return string
     */
    public function getDeleteToken()
    {
        if (!\array_key_exists('salt', $this->_data['meta'])) {
            $this->get();
        }

        return hash_hmac(
            $this->_conf->getKey('zerobincompatibility') ? 'sha1' : 'sha256',
            $this->getId(),
            $this->_data['meta']['salt']
        );
    }

    /**
     * Check if paste has discussions enabled.
     *
     * @throws Exception
     *
     * @return bool
     */
    public function isOpendiscussion()
    {
        if (!\array_key_exists('adata', $this->_data) && !\array_key_exists('data', $this->_data)) {
            $this->get();
        }

        return
            (\array_key_exists('adata', $this->_data) && 1 === $this->_data['adata'][2])
            || (\array_key_exists('opendiscussion', $this->_data['meta']) && $this->_data['meta']['opendiscussion']);
    }

    /**
     * Sanitizes data to conform with current configuration.
     *
     * @return array
     */
    protected function _sanitize(array $data)
    {
        $expiration = $data['meta']['expire'];
        unset($data['meta']['expire']);
        $expire_options = $this->_conf->getSection('expire_options');
        if (\array_key_exists($expiration, $expire_options)) {
            $expire = $expire_options[$expiration];
        } else {
            // using getKey() to ensure a default value is present
            $expire = $this->_conf->getKey($this->_conf->getKey('default', 'expire'), 'expire_options');
        }
        if ($expire > 0) {
            $data['meta']['expire_date'] = time() + $expire;
        }

        return $data;
    }

    /**
     * Validate data.
     *
     * @throws Exception
     */
    protected function _validate(array $data): void
    {
        // reject invalid or disabled formatters
        if (!\array_key_exists($data['adata'][1], $this->_conf->getSection('formatter_options'))) {
            throw new Exception('Invalid data.', 75);
        }

        // discussion requested, but disabled in config or burn after reading requested as well, or invalid integer
        if ((1 === $data['adata'][2] && ( // open discussion flag
            !$this->_conf->getKey('discussion')
            || 1 === $data['adata'][3]  // burn after reading flag
        ))
            || (0 !== $data['adata'][2] && 1 !== $data['adata'][2])
        ) {
            throw new Exception('Invalid data.', 74);
        }

        // reject invalid burn after reading
        if (0 !== $data['adata'][3] && 1 !== $data['adata'][3]) {
            throw new Exception('Invalid data.', 73);
        }
    }
}
