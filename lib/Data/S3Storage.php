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

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use PrivateBin\Json;

class S3Storage extends AbstractData
{
    /**
     * S3 client.
     *
     * @var S3Client
     */
    private $_client;

    /**
     * S3 client options.
     *
     * @var array
     */
    private $_options = [];

    /**
     * S3 bucket.
     *
     * @var string
     */
    private $_bucket;

    /**
     * S3 prefix for all PrivateBin data in this bucket.
     *
     * @var string
     */
    private $_prefix = '';

    /**
     * instantiates a new S3 data backend.
     *
     * @return
     */
    public function __construct(array $options)
    {
        if (\is_array($options)) {
            // AWS SDK will try to load credentials from environment if credentials are not passed via configuration
            // ref: https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_credentials.html#default-credential-chain
            if (isset($options['accesskey'], $options['secretkey'])) {
                $this->_options['credentials'] = [];

                $this->_options['credentials']['key'] = $options['accesskey'];
                $this->_options['credentials']['secret'] = $options['secretkey'];
            }
            if (\array_key_exists('region', $options)) {
                $this->_options['region'] = $options['region'];
            }
            if (\array_key_exists('version', $options)) {
                $this->_options['version'] = $options['version'];
            }
            if (\array_key_exists('endpoint', $options)) {
                $this->_options['endpoint'] = $options['endpoint'];
            }
            if (\array_key_exists('use_path_style_endpoint', $options)) {
                $this->_options['use_path_style_endpoint'] = filter_var($options['use_path_style_endpoint'], FILTER_VALIDATE_BOOLEAN);
            }
            if (\array_key_exists('bucket', $options)) {
                $this->_bucket = $options['bucket'];
            }
            if (\array_key_exists('prefix', $options)) {
                $this->_prefix = $options['prefix'];
            }
        }

        $this->_client = new S3Client($this->_options);
    }

    /**
     * {@inheritDoc}
     */
    public function create($pasteid, array $paste)
    {
        if ($this->exists($pasteid)) {
            return false;
        }

        return $this->_upload($this->_getKey($pasteid), $paste);
    }

    /**
     * {@inheritDoc}
     */
    public function read($pasteid)
    {
        try {
            $object = $this->_client->getObject(
                [
                    'Bucket' => $this->_bucket,
                    'Key' => $this->_getKey($pasteid),
                ]
            );
            $data = $object['Body']->getContents();

            return Json::decode($data);
        } catch (S3Exception $e) {
            error_log(
                'failed to read '.$pasteid.' from '.$this->_bucket.', '.
                trim(preg_replace('/\s\s+/', ' ', $e->getMessage()))
            );

            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function delete($pasteid): void
    {
        $name = $this->_getKey($pasteid);

        try {
            $comments = $this->_listAllObjects($name.'/discussion/');
            foreach ($comments as $comment) {
                try {
                    $this->_client->deleteObject(
                        [
                            'Bucket' => $this->_bucket,
                            'Key' => $comment['Key'],
                        ]
                    );
                } catch (S3Exception $e) {
                    // ignore if already deleted.
                }
            }
        } catch (S3Exception $e) {
            // there are no discussions associated with the paste
        }

        try {
            $this->_client->deleteObject(
                [
                    'Bucket' => $this->_bucket,
                    'Key' => $name,
                ]
            );
        } catch (S3Exception $e) {
            // ignore if already deleted
        }
    }

    /**
     * {@inheritDoc}
     */
    public function exists($pasteid)
    {
        return $this->_client->doesObjectExistV2($this->_bucket, $this->_getKey($pasteid));
    }

    /**
     * {@inheritDoc}
     */
    public function createComment($pasteid, $parentid, $commentid, array $comment)
    {
        if ($this->existsComment($pasteid, $parentid, $commentid)) {
            return false;
        }
        $key = $this->_getKey($pasteid).'/discussion/'.$parentid.'/'.$commentid;

        return $this->_upload($key, $comment);
    }

    /**
     * {@inheritDoc}
     */
    public function readComments($pasteid)
    {
        $comments = [];
        $prefix = $this->_getKey($pasteid).'/discussion/';

        try {
            $entries = $this->_listAllObjects($prefix);
            foreach ($entries as $entry) {
                $object = $this->_client->getObject(
                    [
                        'Bucket' => $this->_bucket,
                        'Key' => $entry['Key'],
                    ]
                );
                $body = JSON::decode($object['Body']->getContents());
                $items = explode('/', $entry['Key']);
                $body['id'] = $items[3];
                $body['parentid'] = $items[2];
                $slot = $this->getOpenSlot($comments, (int) $object['Metadata']['created']);
                $comments[$slot] = $body;
            }
        } catch (S3Exception $e) {
            // no comments found
        }

        return $comments;
    }

    /**
     * {@inheritDoc}
     */
    public function existsComment($pasteid, $parentid, $commentid)
    {
        $name = $this->_getKey($pasteid).'/discussion/'.$parentid.'/'.$commentid;

        return $this->_client->doesObjectExistV2($this->_bucket, $name);
    }

    /**
     * {@inheritDoc}
     */
    public function purgeValues($namespace, $time): void
    {
        $path = $this->_prefix;
        if ('' !== $path) {
            $path .= '/';
        }
        $path .= 'config/'.$namespace;

        try {
            foreach ($this->_listAllObjects($path) as $object) {
                $name = $object['Key'];
                if (\strlen($name) > \strlen($path) && '/' !== substr($name, \strlen($path), 1)) {
                    continue;
                }
                $head = $this->_client->headObject(
                    [
                        'Bucket' => $this->_bucket,
                        'Key' => $name,
                    ]
                );
                if (null !== $head->get('Metadata') && \array_key_exists('value', $head->get('Metadata'))) {
                    $value = $head->get('Metadata')['value'];
                    if (is_numeric($value) && (int) $value < $time) {
                        try {
                            $this->_client->deleteObject(
                                [
                                    'Bucket' => $this->_bucket,
                                    'Key' => $name,
                                ]
                            );
                        } catch (S3Exception $e) {
                            // deleted by another instance.
                        }
                    }
                }
            }
        } catch (S3Exception $e) {
            // no objects in the bucket yet
        }
    }

    /**
     * For S3, the value will also be stored in the metadata for the
     * namespaces traffic_limiter and purge_limiter.
     *
     * {@inheritDoc}
     */
    public function setValue($value, $namespace, $key = '')
    {
        $prefix = $this->_prefix;
        if ('' !== $prefix) {
            $prefix .= '/';
        }

        if ('' === $key) {
            $key = $prefix.'config/'.$namespace;
        } else {
            $key = $prefix.'config/'.$namespace.'/'.$key;
        }

        $metadata = ['namespace' => $namespace];
        if ('salt' !== $namespace) {
            $metadata['value'] = (string) $value;
        }

        try {
            $this->_client->putObject(
                [
                    'Bucket' => $this->_bucket,
                    'Key' => $key,
                    'Body' => $value,
                    'ContentType' => 'application/json',
                    'Metadata' => $metadata,
                ]
            );
        } catch (S3Exception $e) {
            error_log(
                'failed to set key '.$key.' to '.$this->_bucket.', '.
                trim(preg_replace('/\s\s+/', ' ', $e->getMessage()))
            );

            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getValue($namespace, $key = '')
    {
        $prefix = $this->_prefix;
        if ('' !== $prefix) {
            $prefix .= '/';
        }

        if ('' === $key) {
            $key = $prefix.'config/'.$namespace;
        } else {
            $key = $prefix.'config/'.$namespace.'/'.$key;
        }

        try {
            $object = $this->_client->getObject(
                [
                    'Bucket' => $this->_bucket,
                    'Key' => $key,
                ]
            );

            return $object['Body']->getContents();
        } catch (S3Exception $e) {
            return '';
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getAllPastes()
    {
        $pastes = [];
        $prefix = $this->_prefix;
        if ('' !== $prefix) {
            $prefix .= '/';
        }

        try {
            foreach ($this->_listAllObjects($prefix) as $object) {
                $candidate = substr($object['Key'], \strlen($prefix));
                if (!str_contains($candidate, '/')) {
                    $pastes[] = $candidate;
                }
            }
        } catch (S3Exception $e) {
            // no objects in the bucket yet
        }

        return $pastes;
    }

    /**
     * {@inheritDoc}
     */
    protected function _getExpiredPastes($batchsize)
    {
        $expired = [];
        $now = time();
        $prefix = $this->_prefix;
        if ('' !== $prefix) {
            $prefix .= '/';
        }

        try {
            foreach ($this->_listAllObjects($prefix) as $object) {
                $head = $this->_client->headObject(
                    [
                        'Bucket' => $this->_bucket,
                        'Key' => $object['Key'],
                    ]
                );
                if (null !== $head->get('Metadata') && \array_key_exists('expire_date', $head->get('Metadata'))) {
                    $expire_at = (int) ($head->get('Metadata')['expire_date']);
                    if (0 !== $expire_at && $expire_at < $now) {
                        $expired[] = $object['Key'];
                    }
                }

                if (\count($expired) > $batchsize) {
                    break;
                }
            }
        } catch (S3Exception $e) {
            // no objects in the bucket yet
        }

        return $expired;
    }

    /**
     * returns all objects in the given prefix.
     *
     * @param $prefix string with prefix
     *
     * @return array all objects in the given prefix
     */
    private function _listAllObjects($prefix)
    {
        $allObjects = [];
        $options = [
            'Bucket' => $this->_bucket,
            'Prefix' => $prefix,
        ];

        do {
            $objectsListResponse = $this->_client->listObjects($options);
            $objects = $objectsListResponse['Contents'] ?? [];
            foreach ($objects as $object) {
                $allObjects[] = $object;
                $options['Marker'] = $object['Key'];
            }
        } while ($objectsListResponse['IsTruncated']);

        return $allObjects;
    }

    /**
     * returns the S3 storage object key for $pasteid in $this->_bucket.
     *
     * @param $pasteid string to get the key for
     *
     * @return string
     */
    private function _getKey($pasteid)
    {
        if ('' !== $this->_prefix) {
            return $this->_prefix.'/'.$pasteid;
        }

        return $pasteid;
    }

    /**
     * Uploads the payload in the $this->_bucket under the specified key.
     * The entire payload is stored as a JSON document. The metadata is replicated
     * as the S3 object's metadata except for the fields attachment, attachmentname
     * and salt.
     *
     * @param $key     string to store the payload under
     * @param $payload array to store
     *
     * @return bool true if successful, otherwise false
     */
    private function _upload($key, $payload)
    {
        $metadata = \array_key_exists('meta', $payload) ? $payload['meta'] : [];
        unset($metadata['attachment'], $metadata['attachmentname'], $metadata['salt']);
        foreach ($metadata as $k => $v) {
            $metadata[$k] = (string) $v;
        }

        try {
            $this->_client->putObject(
                [
                    'Bucket' => $this->_bucket,
                    'Key' => $key,
                    'Body' => Json::encode($payload),
                    'ContentType' => 'application/json',
                    'Metadata' => $metadata,
                ]
            );
        } catch (S3Exception $e) {
            error_log(
                'failed to upload '.$key.' to '.$this->_bucket.', '.
                trim(preg_replace('/\s\s+/', ' ', $e->getMessage()))
            );

            return false;
        }

        return true;
    }
}
