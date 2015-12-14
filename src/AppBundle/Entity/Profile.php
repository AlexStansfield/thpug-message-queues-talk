<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Profile
 *
 * @ORM\Table(name="profile")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ProfileRepository")
 * @ORM\HasLifecycleCallbacks
 */
class Profile
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="username", type="string", length=255, unique=true)
     */
    private $username;

    /**
     * @var string
     *
     * @ORM\Column(name="photo", type="string", length=255, nullable=true)
     */
    private $photo;

    /**
     * @var bool
     *
     * @ORM\Column(name="photo_uploading", type="boolean")
     */
    private $photoUploading;

    /**
     * @var UploadedFile
     *
     * @Assert\NotBlank(message="Please upload the photo.")
     * @Assert\File()
     */
    private $photoUpload;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="updated", type="datetime")
     */
    private $updated;

    public function __construct()
    {
        $this->updated = new \DateTime();
    }

    /**
     * @ORM\PreUpdate
     */
    public function preUpload()
    {
        $this->updated = new \DateTime();
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set username
     *
     * @param string $username
     *
     * @return Profile
     */
    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Get username
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Set photo
     *
     * @param string $photo
     *
     * @return Profile
     */
    public function setPhoto($photo)
    {
        $this->photo = $photo;

        return $this;
    }

    /**
     * Get photo
     *
     * @return string
     */
    public function getPhoto()
    {
        return $this->photo;
    }

    /**
     * @return UploadedFile
     */
    public function getPhotoUpload()
    {
        return $this->photoUpload;
    }

    /**
     * @param UploadedFile $photoUpload
     * @return $this
     */
    public function setPhotoUpload(UploadedFile $photoUpload)
    {
        $this->photoUpload = $photoUpload;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isPhotoUploading()
    {
        return $this->photoUploading;
    }

    /**
     * @param boolean $photoUploading
     * @return $this
     */
    public function setPhotoUploading($photoUploading)
    {
        $this->photoUploading = $photoUploading;

        return $this;
    }
}

