<?php

namespace AppBundle\Service;

use Aws\S3\S3Client;
use Aws\Symfony\AwsBundle;
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
     * @return string
     */
    public function uploadPhoto(SplFileInfo $fileInfo, $contentType, $fileName = null)
    {
        $result = $this->s3->putObject([
            'Bucket'       => $this->bucket,
            'Key'          => (null !== $fileName ? $fileName : $fileInfo->getFilename()),
            'SourceFile'   => $fileInfo->getPathname(),
            'ContentType'  => $contentType,
            'ACL'          => 'public-read',
            'StorageClass' => 'REDUCED_REDUNDANCY'
        ]);

        return $result['ObjectURL'];
    }

    public function uploadPhotoAsync(SplFileInfo $fileInfo, $contentType, $callback, $fileName = null)
    {
        $this->s3->putObjectAsync([
            'Bucket'       => $this->bucket,
            'Key'          => (null !== $fileName ? $fileName : $fileInfo->getFilename()),
            'SourceFile'   => $fileInfo->getPathname(),
            'ContentType'  => $contentType,
            'ACL'          => 'public-read',
            'StorageClass' => 'REDUCED_REDUNDANCY'
        ])->then($callback);
    }
}