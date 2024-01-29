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
use PrivateBin\Configuration;
use PrivateBin\Data\AbstractData;

/**
 * AbstractModel.
 *
 * Abstract model for PrivateBin objects.
 */
abstract class AbstractModel
{
    /**
     * Instance ID.
     *
     * @var string
     */
    protected $_id = '';

    /**
     * Instance data.
     *
     * @var array
     */
    protected $_data = ['meta' => []];

    /**
     * Configuration.
     *
     * @var Configuration
     */
    protected $_conf;

    /**
     * Data storage.
     *
     * @var AbstractData
     */
    protected $_store;

    /**
     * Instance constructor.
     */
    public function __construct(Configuration $configuration, AbstractData $storage)
    {
        $this->_conf = $configuration;
        $this->_store = $storage;
    }

    /**
     * Get ID.
     *
     * @return string
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * Set ID.
     *
     * @param string $id
     *
     * @throws Exception
     */
    public function setId($id): void
    {
        if (!self::isValidId($id)) {
            throw new Exception('Invalid paste ID.', 60);
        }
        $this->_id = $id;
    }

    /**
     * Set data and recalculate ID.
     *
     * @throws Exception
     */
    public function setData(array $data): void
    {
        $data = $this->_sanitize($data);
        $this->_validate($data);
        $this->_data = $data;

        // calculate a 64 bit checksum to avoid collisions
        $this->setId(hash(version_compare(PHP_VERSION, '5.6', '<') ? 'fnv164' : 'fnv1a64', $data['ct']));
    }

    /**
     * Get instance data.
     *
     * @return array
     */
    public function get()
    {
        return $this->_data;
    }

    /**
     * Store the instance's data.
     *
     * @throws Exception
     */
    abstract public function store();

    /**
     * Delete the current instance.
     *
     * @throws Exception
     */
    abstract public function delete();

    /**
     * Test if current instance exists in store.
     *
     * @return bool
     */
    abstract public function exists();

    /**
     * Validate ID.
     *
     * @static
     *
     * @param string $id
     *
     * @return bool
     */
    public static function isValidId($id)
    {
        return (bool) preg_match('#\A[a-f\d]{16}\z#', (string) $id);
    }

    /**
     * Sanitizes data to conform with current configuration.
     *
     * @return array
     */
    abstract protected function _sanitize(array $data);

    /**
     * Validate data.
     *
     * @throws Exception
     */
    protected function _validate(array $data): void
    {
    }
}
