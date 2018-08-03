<?php

namespace AppBundle\Command;

use JackTales\Command\AbstractWorkerCommand;
use Pheanstalk\Job;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class ExampleWorkerCommand
 *
 * @package JackTales\Command
 */
class PhotoUploadWorkerCommand extends AbstractWorkerCommand implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    protected function configure()
    {
        $this
            ->setName('thpug:photoupload')
            ->setDescription('Upload Photos to S3');
    }

    /**
     * @param Job $job
     * @return bool
     */
    protected function isValid(Job $job)
    {
        $data = json_decode($job->getData(), true);

        $valid = is_array($data) && array_key_exists('profile_id', $data);

        return $valid;
    }

    /**
     * @param Job $job
     * @return string
     */
    protected function getStartMessage(Job $job)
    {
        $data = json_decode($job->getData());

        return 'Starting Photo Upload for Profile ' . $data->profile_id;
    }

    /**
     * @param Job $job
     * @return int
     * @throws \Exception
     */
    protected function processJob(Job $job)
    {
        $data = json_decode($job->getData());

        $em = $this->container->get('doctrine.orm.entity_manager');

        // Get the File
        $file = new \SplFileInfo($data->file_path);

        // Check if we have a filename to use
        $fileName = isset($data->file_name) ? $data->file_name : null;

        // Upload the File to S3
        $photoUrl = $this->container->get('service_photo_upload')->uploadPhoto($file, $data->content_type, $fileName);

        // Get the Profile entity
        $profile = $em->find('AppBundle:Profile', $data->profile_id);

        // Update Profile
        $profile->setPhoto($photoUrl);
        $profile->setPhotoUploading(false);

        // Flush
        $em->flush($profile);
        $em->clear();

        $this->output->writeln('<comment>Profile Photo Uploaded</comment>');

        return self::ACTION_DELETE;
    }

    /**
     * @return ContainerInterface
     *
     * @throws \LogicException
     */
    protected function getContainer()
    {
        if (null === $this->container) {
            $application = $this->getApplication();
            if (null === $application) {
                throw new \LogicException('The container cannot be retrieved as the application instance is not yet set.');
            }

            $this->container = $application->getKernel()->getContainer();
        }

        return $this->container;
    }

    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;

        $this->pheanstalk = $container->get('leezy.pheanstalk');
    }
}
