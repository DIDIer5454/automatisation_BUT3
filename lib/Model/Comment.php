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
use Identicon\Identicon;
use Jdenticon\Identicon as Jdenticon;
use PrivateBin\Persistence\TrafficLimiter;
use PrivateBin\Vizhash16x16;

/**
 * Comment.
 *
 * Model of a PrivateBin comment.
 */
class Comment extends AbstractModel
{
    /**
     * Instance's parent.
     *
     * @var Paste
     */
    private $_paste;

    /**
     * Store the comment's data.
     *
     * @throws Exception
     */
    public function store(): void
    {
        // Make sure paste exists.
        $pasteid = $this->getPaste()->getId();
        if (!$this->getPaste()->exists()) {
            throw new Exception('Invalid data.', 67);
        }

        // Make sure the discussion is opened in this paste and in configuration.
        if (!$this->getPaste()->isOpendiscussion() || !$this->_conf->getKey('discussion')) {
            throw new Exception('Invalid data.', 68);
        }

        // Check for improbable collision.
        if ($this->exists()) {
            throw new Exception('You are unlucky. Try again.', 69);
        }

        $this->_data['meta']['created'] = time();

        // store comment
        if (false === $this->_store->createComment(
            $pasteid,
            $this->getParentId(),
            $this->getId(),
            $this->_data
        )
        ) {
            throw new Exception('Error saving comment. Sorry.', 70);
        }
    }

    /**
     * Delete the comment.
     *
     * @throws Exception
     */
    public function delete(): void
    {
        throw new Exception('To delete a comment, delete its parent paste', 64);
    }

    /**
     * Test if comment exists in store.
     *
     * @return bool
     */
    public function exists()
    {
        return $this->_store->existsComment(
            $this->getPaste()->getId(),
            $this->getParentId(),
            $this->getId()
        );
    }

    /**
     * Set paste.
     *
     * @throws Exception
     */
    public function setPaste(Paste $paste): void
    {
        $this->_paste = $paste;
        $this->_data['pasteid'] = $paste->getId();
    }

    /**
     * Get paste.
     *
     * @return Paste
     */
    public function getPaste()
    {
        return $this->_paste;
    }

    /**
     * Set parent ID.
     *
     * @param string $id
     *
     * @throws Exception
     */
    public function setParentId($id): void
    {
        if (!self::isValidId($id)) {
            throw new Exception('Invalid paste ID.', 65);
        }
        $this->_data['parentid'] = $id;
    }

    /**
     * Get parent ID.
     *
     * @return string
     */
    public function getParentId()
    {
        if (!\array_key_exists('parentid', $this->_data)) {
            $this->_data['parentid'] = $this->getPaste()->getId();
        }

        return $this->_data['parentid'];
    }

    /**
     * Sanitizes data to conform with current configuration.
     *
     * @return array
     */
    protected function _sanitize(array $data)
    {
        // we generate an icon based on a SHA512 HMAC of the users IP, if configured
        $icon = $this->_conf->getKey('icon');
        if ('none' !== $icon) {
            $pngdata = '';
            $hmac = TrafficLimiter::getHash();
            if ('identicon' === $icon) {
                $identicon = new Identicon();
                $pngdata = $identicon->getImageDataUri($hmac, 16);
            } elseif ('jdenticon' === $icon) {
                $jdenticon = new Jdenticon(
                    [
                        'hash' => $hmac,
                        'size' => 16,
                        'style' => [
                            'backgroundColor' => '#fff0', // fully transparent, for dark mode
                            'padding' => 0,
                        ],
                    ]
                );
                $pngdata = $jdenticon->getImageDataUri('png');
            } elseif ('vizhash' === $icon) {
                $vh = new Vizhash16x16();
                $pngdata = 'data:image/png;base64,'.base64_encode(
                    $vh->generate($hmac)
                );
            }
            if ('' !== $pngdata) {
                if (!\array_key_exists('meta', $data)) {
                    $data['meta'] = [];
                }
                $data['meta']['icon'] = $pngdata;
            }
        }

        return $data;
    }
}
