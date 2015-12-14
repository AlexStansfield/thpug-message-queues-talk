<?php

namespace AppBundle\Service;

use Aws\S3\S3Client;
use SplFileInfo;

class PhotoUploadService
{
    /**
     * @var string
     */
    protected $bucket;

    /**
     * @var S3Client
     */
    protected $s3;

    /**
     * PhotoUploadService constructor.
     *
     * @param S3Client $s3
     * @param $bucket
     */
    public function __construct(S3Client $s3, $bucket)
    {
        $this->s3 = $s3;
        $this->bucket = $bucket;
    }

    /**
     * Upload photo to s3 and return url
     *
     * @param SplFileInfo $fileInfo
     * @param string $contentType
     * @param string|null $fileName
     * @return string
     */
    public function uploadPhoto(SplFileInfo $fileInfo, $contentType, $fileName = null)
    {
        $result = $this->s3->putObject($this->getUploadOptions($fileInfo, $contentType, $fileName));

        return $result['ObjectURL'];
    }

    /**
     * Upload photo to s3 asynchronously, send result to $callback
     *
     * @param SplFileInfo $fileInfo
     * @param string $contentType
     * @param $callback
     * @param string|null $fileName
     */
    public function uploadPhotoAsync(SplFileInfo $fileInfo, $contentType, $callback, $fileName = null)
    {
        $this->s3->putObjectAsync($this->getUploadOptions($fileInfo, $contentType, $fileName))->then($callback);
    }

    /**
     * Get the Upload Options for S3 put object
     *
     * @param SplFileInfo $fileInfo
     * @param string $contentType
     * @param string|null $fileName
     * @return array
     */
    protected function getUploadOptions(SplFileInfo $fileInfo, $contentType, $fileName = null)
    {
        $options = [
            'Bucket'       => $this->bucket,
            'Key'          => (null !== $fileName ? $fileName : $fileInfo->getFilename()),
            'SourceFile'   => $fileInfo->getPathname(),
            'ContentType'  => $contentType,
            'ACL'          => 'public-read',
            'StorageClass' => 'REDUCED_REDUNDANCY'
        ];

        return $options;
    }
}
