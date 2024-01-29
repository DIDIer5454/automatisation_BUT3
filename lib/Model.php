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

namespace PrivateBin;

use PrivateBin\Model\Paste;
use PrivateBin\Persistence\PurgeLimiter;

/**
 * Model.
 *
 * Factory of PrivateBin instance models.
 */
class Model
{
    /**
     * Configuration.
     *
     * @var Configuration
     */
    private $_conf;

    /**
     * Data storage.
     *
     * @var Data\AbstractData
     */
    private $_store;

    /**
     * Factory constructor.
     */
    public function __construct(Configuration $conf)
    {
        $this->_conf = $conf;
    }

    /**
     * Get a paste, optionally a specific instance.
     *
     * @param string $pasteId
     *
     * @return Paste
     */
    public function getPaste($pasteId = null)
    {
        $paste = new Paste($this->_conf, $this->getStore());
        if (null !== $pasteId) {
            $paste->setId($pasteId);
        }

        return $paste;
    }

    /**
     * Checks if a purge is necessary and triggers it if yes.
     */
    public function purge(): void
    {
        PurgeLimiter::setConfiguration($this->_conf);
        PurgeLimiter::setStore($this->getStore());
        if (PurgeLimiter::canPurge()) {
            $this->getStore()->purge($this->_conf->getKey('batchsize', 'purge'));
        }
    }

    /**
     * Gets, and creates if neccessary, a store object.
     *
     * @return Data\AbstractData
     */
    public function getStore()
    {
        if (null === $this->_store) {
            $class = 'PrivateBin\\Data\\'.$this->_conf->getKey('class', 'model');
            $this->_store = new $class($this->_conf->getSection('model_options'));
        }

        return $this->_store;
    }
}
