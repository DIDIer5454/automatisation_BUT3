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

use Google\Cloud\Core\Exception\BadRequestException;
use Google\Cloud\Core\Exception\NotFoundException;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\Connection\ConnectionInterface;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Storage\StorageObject;
use PrivateBin\Persistence\ServerSalt;

error_reporting(E_ALL | E_STRICT);

// change this, if your php files and data is outside of your webservers document root
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', '..');
}
if (!defined('PATH')) {
    define('PATH', '..'.DIRECTORY_SEPARATOR);
}
if (!defined('CONF')) {
    define('CONF', PATH.'cfg'.DIRECTORY_SEPARATOR.'conf.php');
}
if (!defined('CONF_SAMPLE')) {
    define('CONF_SAMPLE', PATH.'cfg'.DIRECTORY_SEPARATOR.'conf.sample.php');
}

require PATH.'vendor/autoload.php';
Helper::updateSubresourceIntegrity();

/**
 * Class StorageClientStub provides a limited stub for performing the unit test.
 */
class StorageClientStub extends StorageClient
{
    private $_config;
    private $_connection;
    private static $_buckets = [];

    public function __construct(array $config = [])
    {
        $this->_config = $config;
        $this->_connection = new ConnectionInterfaceStub();
    }

    public function bucket($name, $userProject = false)
    {
        if (!array_key_exists($name, self::$_buckets)) {
            $b = new BucketStub($this->_connection, $name, [], $this);
            self::$_buckets[$name] = $b;
        }

        return self::$_buckets[$name];
    }

    /**
     * @param mixed $name
     *
     * @throws \Google\Cloud\Core\Exception\NotFoundException
     */
    public function deleteBucket($name): void
    {
        if (array_key_exists($name, self::$_buckets)) {
            unset(self::$_buckets[$name]);
        } else {
            throw new NotFoundException();
        }
    }

    public function buckets(array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function registerStreamWrapper($protocol = null): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function unregisterStreamWrapper($protocol = null): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function signedUrlUploader($uri, $data, array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function timestamp(DateTimeInterface $timestamp, $nanoSeconds = null): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function getServiceAccount(array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function hmacKeys(array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function hmacKey($accessId, $projectId = null, array $metadata = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function createHmacKey($serviceAccountEmail, array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function createBucket($name, array $options = [])
    {
        if (array_key_exists($name, self::$_buckets)) {
            throw new BadRequestException('already exists');
        }
        $b = new BucketStub($this->_connection, $name, [], $this);
        self::$_buckets[$name] = $b;

        return $b;
    }
}

/**
 * Class BucketStub stubs a GCS bucket.
 */
class BucketStub extends Bucket
{
    public $_objects;
    private $_name;
    private $_info;
    private $_connection;
    private $_client;

    public function __construct(ConnectionInterface $connection, $name, array $info = [], $client = null)
    {
        $this->_name = $name;
        $this->_info = $info;
        $this->_connection = $connection;
        $this->_objects = [];
        $this->_client = $client;
    }

    public function acl(): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function defaultAcl(): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function exists(array $options = [])
    {
        return true;
    }

    public function upload($data, array $options = []): void
    {
        if (!is_string($data) || !array_key_exists('name', $options)) {
            throw new BadMethodCallException('not supported by this stub');
        }

        $name = $options['name'];
        $generation = '1';
        $o = new StorageObjectStub($this->_connection, $name, $this, $generation, $options);
        $this->_objects[$options['name']] = $o;
        $o->setData($data);
    }

    public function uploadAsync($data, array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function getResumableUploader($data, array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function getStreamableUploader($data, array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function object($name, array $options = [])
    {
        if (array_key_exists($name, $this->_objects)) {
            return $this->_objects[$name];
        }

        return new StorageObjectStub($this->_connection, $name, $this, null, $options);
    }

    public function objects(array $options = [])
    {
        $prefix = array_key_exists('prefix', $options) ? $options['prefix'] : '';

        return new CallbackFilterIterator(
            new ArrayIterator($this->_objects),
            fn ($current, $key, $iterator) => substr($key, 0, strlen($prefix)) === $prefix
        );
    }

    public function createNotification($topic, array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function notification($id): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function notifications(array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function delete(array $options = []): void
    {
        $this->_client->deleteBucket($this->_name);
    }

    public function update(array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function compose(array $sourceObjects, $name, array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function info(array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function reload(array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function name()
    {
        return $this->_name;
    }

    public static function lifecycle(array $lifecycle = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function currentLifecycle(array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function isWritable($file = null): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function iam(): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function lockRetentionPolicy(array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function signedUrl($expires, array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function generateSignedPostPolicyV4($objectName, $expires, array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }
}

/**
 * Class StorageObjectStub stubs a GCS storage object.
 */
class StorageObjectStub extends StorageObject
{
    private $_name;
    private $_data;
    private $_info;
    private $_bucket;
    private $_generation;
    private $_exists = false;
    private $_connection;

    public function __construct(ConnectionInterface $connection, $name, $bucket, $generation = null, array $info = [], $encryptionKey = null, $encryptionKeySHA256 = null)
    {
        $this->_name = $name;
        $this->_bucket = $bucket;
        $this->_generation = $generation;
        $this->_info = $info;
        $this->_connection = $connection;
        $timeCreated = new DateTime();
        $this->_info['metadata']['timeCreated'] = $timeCreated->format('Y-m-d\TH:i:s.u\Z');
    }

    public function acl(): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function exists(array $options = [])
    {
        return array_key_exists($this->_name, $this->_bucket->_objects);
    }

    /**
     * @throws NotFoundException
     */
    public function delete(array $options = []): void
    {
        if (array_key_exists($this->_name, $this->_bucket->_objects)) {
            unset($this->_bucket->_objects[$this->_name]);
        } else {
            throw new NotFoundException('key '.$this->_name.' not found.');
        }
    }

    /**
     * @throws NotFoundException
     */
    public function update(array $metadata, array $options = []): void
    {
        if (!$this->_exists) {
            throw new NotFoundException('key '.$this->_name.' not found.');
        }
        $this->_info = $metadata;
    }

    public function copy($destination, array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function rewrite($destination, array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function rename($name, array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    /**
     * @throws NotFoundException
     */
    public function downloadAsString(array $options = [])
    {
        if (!$this->_exists) {
            throw new NotFoundException('key '.$this->_name.' not found.');
        }

        return $this->_data;
    }

    public function downloadToFile($path, array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function downloadAsStream(array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function downloadAsStreamAsync(array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function signedUrl($expires, array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function signedUploadUrl($expires, array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function beginSignedUploadSession(array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function info(array $options = [])
    {
        return array_key_exists('metadata', $this->_info) ? $this->_info['metadata'] : [];
    }

    public function reload(array $options = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function name()
    {
        return $this->_name;
    }

    public function identity(): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function gcsUri()
    {
        return sprintf(
            'gs://%s/%s',
            $this->_bucket->name(),
            $this->_name
        );
    }

    public function setData($data): void
    {
        $this->_data = $data;
        $this->_exists = true;
    }
}

/**
 * Class ConnectionInterfaceStub required for the stubs.
 */
class ConnectionInterfaceStub implements ConnectionInterface
{
    public function deleteAcl(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function getAcl(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function listAcl(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function insertAcl(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function patchAcl(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function deleteBucket(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function getBucket(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function listBuckets(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function insertBucket(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function getBucketIamPolicy(array $args): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function setBucketIamPolicy(array $args): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function testBucketIamPermissions(array $args): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function patchBucket(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function deleteObject(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function copyObject(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function rewriteObject(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function composeObject(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function getObject(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function listObjects(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function patchObject(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function downloadObject(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function insertObject(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function getNotification(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function deleteNotification(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function insertNotification(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function listNotifications(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function getServiceAccount(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function lockRetentionPolicy(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function createHmacKey(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function deleteHmacKey(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function getHmacKey(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function updateHmacKey(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }

    public function listHmacKeys(array $args = []): void
    {
        throw new BadMethodCallException('not supported by this stub');
    }
}

/**
 * Class Helper provides unit tests pastes and comments of various formats.
 */
class Helper
{
    /**
     * example ID of a paste.
     *
     * @var string
     */
    private static $pasteid = '5b65a01b43987bc2';

    /**
     * example paste version 1.
     *
     * @var array
     */
    private static $pasteV1 = [
        'data' => '{"iv":"EN39/wd5Nk8HAiSG2K5AsQ","v":1,"iter":1000,"ks":128,"ts":64,"mode":"ccm","adata":"","cipher":"aes","salt":"QKN1DBXe5PI","ct":"8hA83xDdXjD7K2qfmw5NdA"}',
        'attachment' => '{"iv":"Pd4pOKWkmDTT9uPwVwd5Ag","v":1,"iter":1000,"ks":128,"ts":64,"mode":"ccm","adata":"","cipher":"aes","salt":"ZIUhFTliVz4","ct":"6nOCU3peNDclDDpFtJEBKA"}',
        'attachmentname' => '{"iv":"76MkAtOGC4oFogX/aSMxRA","v":1,"iter":1000,"ks":128,"ts":64,"mode":"ccm","adata":"","cipher":"aes","salt":"ZIUhFTliVz4","ct":"b6Ae/U1xJdsX/+lATud4sQ"}',
        'meta' => [
            'formatter' => 'plaintext',
            'postdate' => 1344803344,
            'opendiscussion' => true,
        ],
    ];

    /**
     * example paste version 2.
     *
     * @var array
     */
    private static $pasteV2 = [
        'adata' => [
            [
                'gMSNoLOk4z0RnmsYwXZ8mw==',
                'TZO+JWuIuxs=',
                100000,
                256,
                128,
                'aes',
                'gcm',
                'zlib',
            ],
            'plaintext',
            1,
            0,
        ],
        'meta' => [
            'expire' => '5min',
            'created' => 1344803344,
        ],
        'v' => 2,
        'ct' => 'ME5JF/YBEijp2uYMzLZozbKtWc5wfy6R59NBb7SmRig=',
    ];

    /**
     * example ID of a comment.
     *
     * @var string
     */
    private static $commentid = '5a52eebf11c4c94b';

    /**
     * example comment.
     *
     * @var array
     */
    private static $commentV1 = [
        'data' => '{"iv":"Pd4pOKWkmDTT9uPwVwd5Ag","v":1,"iter":1000,"ks":128,"ts":64,"mode":"ccm","adata":"","cipher":"aes","salt":"ZIUhFTliVz4","ct":"6nOCU3peNDclDDpFtJEBKA"}',
        'meta' => [
            'nickname' => '{"iv":"76MkAtOGC4oFogX/aSMxRA","v":1,"iter":1000,"ks":128,"ts":64,"mode":"ccm","adata":"","cipher":"aes","salt":"ZIUhFTliVz4","ct":"b6Ae/U1xJdsX/+lATud4sQ"}',
            'vizhash' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAIAAACQkWg2AAABGUlEQVQokWOsl5/94983CNKQMjnxaOePf98MeKwPfNjkLZ3AgARab6b9+PeNEVnDj3/ff/z7ZiHnzsDA8Pv7H2TVPJw8EAYLAwb48OaVgIgYKycLsrYv378wMDB8//qdCVMDRA9EKSsnCwRBxNsepaLboMFlyMDAICAi9uHNK24GITQ/MDAwoNhgIGMLtwGrzegaLjw5jMz9+vUdnN17uwDCQDhJgk0O07yvX9+teDX1x79v6DYIsIjgcgMaYGFgYOBg4kJx2JejkAiBxAw+PzAwMNz4dp6wDXDw4MdNNOl0rWYsNkD89OLXI/xmo9sgzatJjAYmBgYGDiauD3/ePP18nVgb4MF89+M5ZX6js293wUMpnr8KTQMAxsCJnJ30apMAAAAASUVORK5CYII=',
            'postdate' => 1344803528,
        ],
    ];

    /**
     * JS files and their SRI hashes.
     *
     * @var array
     */
    private static $hashes = [];

    /**
     * get example paste ID.
     *
     * @return string
     */
    public static function getPasteId()
    {
        return version_compare(PHP_VERSION, '5.6', '<') ? hash('fnv164', self::$pasteV2['ct']) : self::$pasteid;
    }

    /**
     * get example paste, as stored on server.
     *
     * @param int $version
     *
     * @return array
     */
    public static function getPaste($version = 2, array $meta = [])
    {
        $example = self::getPasteWithAttachment($version, $meta);
        // v1 has the attachment stored in a separate property
        if (1 === $version) {
            unset($example['attachment'], $example['attachmentname']);
        }

        return $example;
    }

    /**
     * get example paste with attachment, as stored on server.
     *
     * @param int $version
     *
     * @return array
     */
    public static function getPasteWithAttachment($version = 2, array $meta = [])
    {
        $example = 1 === $version ? self::$pasteV1 : self::$pasteV2;
        $example['meta']['salt'] = ServerSalt::generate();
        $example['meta'] = array_merge($example['meta'], $meta);

        return $example;
    }

    /**
     * get example paste, as decoded from POST by the request object.
     *
     * @param int $version
     *
     * @return array
     */
    public static function getPastePost($version = 2, array $meta = [])
    {
        $example = self::getPaste($version, $meta);
        if (2 === $version) {
            $example['meta'] = ['expire' => $example['meta']['expire']];
        } else {
            unset($example['meta']['postdate']);
        }

        return $example;
    }

    /**
     * get example paste, as received via POST by the user.
     *
     * @param int $version
     *
     * @return array
     */
    public static function getPasteJson($version = 2, array $meta = [])
    {
        return json_encode(self::getPastePost($version, $meta));
    }

    /**
     * get example paste ID.
     *
     * @return string
     */
    public static function getCommentId()
    {
        return self::$commentid;
    }

    /**
     * get example comment, as stored on server.
     *
     * @param int $version
     *
     * @return array
     */
    public static function getComment($version = 2, array $meta = [])
    {
        $example = 1 === $version ? self::$commentV1 : self::$pasteV2;
        if (2 === $version) {
            $example['adata'] = $example['adata'][0];
            $example['pasteid'] = $example['parentid'] = self::getPasteId();
            $example['meta']['created'] = self::$commentV1['meta']['postdate'];
            $example['meta']['icon'] = self::$commentV1['meta']['vizhash'];
            unset($example['meta']['expire']);
        }
        $example['meta'] = array_merge($example['meta'], $meta);

        return $example;
    }

    /**
     * get example comment, as decoded from POST by the request object.
     *
     * @param int $version
     *
     * @return array
     */
    public static function getCommentPost()
    {
        $example = self::getComment();
        unset($example['meta']);

        return $example;
    }

    /**
     * get example comment, as received via POST by user.
     *
     * @param int $version
     *
     * @return array
     */
    public static function getCommentJson()
    {
        return json_encode(self::getCommentPost());
    }

    /**
     * delete directory and all its contents recursively.
     *
     * @param string $path
     *
     * @throws Exception
     */
    public static function rmDir($path): void
    {
        if (is_dir($path)) {
            $path .= DIRECTORY_SEPARATOR;
            $dir = dir($path);
            while (false !== ($file = $dir->read())) {
                if ('.' !== $file && '..' !== $file) {
                    if (is_dir($path.$file)) {
                        self::rmDir($path.$file);
                    } elseif (is_file($path.$file)) {
                        if (!unlink($path.$file)) {
                            throw new Exception('Error deleting file "'.$path.$file.'".');
                        }
                    }
                }
            }
            $dir->close();
            if (!rmdir($path)) {
                throw new Exception('Error deleting directory "'.$path.'".');
            }
        }
    }

    /**
     * create a backup of the config file.
     */
    public static function confBackup(): void
    {
        if (!is_file(CONF.'.bak') && is_file(CONF)) {
            rename(CONF, CONF.'.bak');
        }
        if (!is_file(CONF_SAMPLE.'.bak') && is_file(CONF_SAMPLE)) {
            copy(CONF_SAMPLE, CONF_SAMPLE.'.bak');
        }
    }

    /**
     * restor backup of the config file.
     */
    public static function confRestore(): void
    {
        if (is_file(CONF.'.bak')) {
            rename(CONF.'.bak', CONF);
        }
        if (is_file(CONF_SAMPLE.'.bak')) {
            rename(CONF_SAMPLE.'.bak', CONF_SAMPLE);
        }
    }

    /**
     * create ini file.
     *
     * @param string $pathToFile
     */
    public static function createIniFile($pathToFile, array $values): void
    {
        if (count($values)) {
            @unlink($pathToFile);
            $ini = fopen($pathToFile, 'a');
            foreach ($values as $section => $options) {
                fwrite($ini, "[{$section}]".PHP_EOL);
                foreach ($options as $option => $setting) {
                    if (null === $setting) {
                        continue;
                    }
                    if (is_string($setting)) {
                        $setting = '"'.$setting.'"';
                    } elseif (is_array($setting)) {
                        foreach ($setting as $key => $value) {
                            if (null === $value) {
                                $value = 'null';
                            } elseif (is_string($value)) {
                                $value = '"'.$value.'"';
                            } else {
                                $value = var_export($value, true);
                            }
                            fwrite($ini, $option."[{$key}] = {$value}".PHP_EOL);
                        }

                        continue;
                    } else {
                        $setting = var_export($setting, true);
                    }
                    fwrite($ini, "{$option} = {$setting}".PHP_EOL);
                }
                fwrite($ini, PHP_EOL);
            }
            fclose($ini);
        }
    }

    /**
     * a var_export that returns arrays without line breaks
     * by linus@flowingcreativity.net via php.net.
     *
     * @param mixed $var
     * @param bool  $return
     *
     * @return string|void
     */
    public static function varExportMin($var, $return = false)
    {
        if (is_array($var)) {
            $toImplode = [];
            foreach ($var as $key => $value) {
                $toImplode[] = var_export($key, true).' => '.self::varExportMin($value, true);
            }
            $code = 'array('.implode(', ', $toImplode).')';
            if ($return) {
                return $code;
            }
            echo $code;
        } else {
            return var_export($var, $return);
        }
    }

    /**
     * update all templates with the latest SRI hashes for all JS files.
     */
    public static function updateSubresourceIntegrity(): void
    {
        $dir = dir(PATH.'js');
        while (false !== ($file = $dir->read())) {
            if ('.js' === substr($file, -3)) {
                self::$hashes[$file] = base64_encode(
                    hash(
                        'sha512',
                        file_get_contents(
                            PATH.'js'.DIRECTORY_SEPARATOR.$file
                        ),
                        true
                    )
                );
            }
        }

        $dir = dir(PATH.'tpl');
        while (false !== ($file = $dir->read())) {
            if ('.php' === substr($file, -4)) {
                $content = file_get_contents(
                    PATH.'tpl'.DIRECTORY_SEPARATOR.$file
                );
                $content = preg_replace_callback(
                    '#<script ([^>]+) src="js/([a-z0-9.-]+.js)([^"]*)"( integrity="[^"]+" crossorigin="[^"]+")?></script>#',
                    function ($matches) {
                        if (array_key_exists($matches[2], self::$hashes)) {
                            return '<script '.$matches[1].' src="js/'.
                                $matches[2].$matches[3].
                                '" integrity="sha512-'.self::$hashes[$matches[2]].
                                '" crossorigin="anonymous"></script>';
                        }

                        return $matches[0];
                    },
                    $content
                );
                file_put_contents(
                    PATH.'tpl'.DIRECTORY_SEPARATOR.$file,
                    $content
                );
            }
        }
    }
}
