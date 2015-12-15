<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Profile;
use AppBundle\Form\ProfilesType;
use AppBundle\Form\ProfileType;
use Aws\Result;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', array(
            'base_dir' => realpath($this->container->getParameter('kernel.root_dir') . '/..'),
        ));
    }

    /**
     * @Route("/profile/{id}", name="profile_view")
     * @ParamConverter("profile", class="AppBundle:Profile")
     * @param Profile $profile
     * @return Response
     */
    public function viewProfileAction(Profile $profile)
    {
        $html = $this->container->get('templating')->render(
            'default/profile.html.twig',
            array('profile' => $profile)
        );

        return new Response($html);
    }

    /**
     * @Route("/profile/{id}/edit", name="profile_edit")
     * @ParamConverter("profile", class="AppBundle:Profile")
     * @param Request $request
     * @param Profile $profile
     * @return Response
     */
    public function editProfileAction(Request $request, Profile $profile)
    {
        $form = $this->createForm(ProfileType::class, $profile);
        $form->handleRequest($request);

        if ($form->isValid()) {
            // $file stores the uploaded PDF file
            /** @var UploadedFile $tmpFile */
            $uploadedFile = $profile->getPhotoUpload();
            $contentType = $uploadedFile->getMimeType();

            // Rename and Move the Uploaded File to uploads folder
            $tmpFile = $this->processUploadedFile($uploadedFile);

            // Upload Profile Photo
            $this->uploadProfilePhoto($profile, $tmpFile, $contentType);

            return $this->redirect($this->generateUrl('profile_view', ['id' => $profile->getId()]));
        }

        return $this->render(
            'default/profile_edit.html.twig',
            [
                'form' => $form->createView(),
                'profile' => $profile
            ]
        );
    }

    /**
     * @Route("/profiles/edit", name="profiles_edit")
     * @param Request $request
     * @return Response
     */
    public function editProfilesAction(Request $request)
    {
        $form = $this->createForm(ProfilesType::class);

        if ('POST' === $request->getMethod()) {
            $em = $this->getDoctrine()->getManager();

            /** @var UploadedFile $uploadedFile */
            $uploadedFile = $request->files->get('profiles')['photoUpload'];
            $contentType = $uploadedFile->getMimeType();
            $extension = $uploadedFile->guessExtension();

            // Rename and Move the Uploaded File to uploads folder
            $tmpFile = $this->processUploadedFile($uploadedFile);

            $profiles = $em->getRepository('AppBundle:Profile')->findAll();
            $numProfiles = count($profiles);
            $i = 1;

            foreach ($profiles as $profile) {
                if ($i === $numProfiles) {
                    // Last Profile so just Upload Profile Photo
                    $this->uploadProfilePhoto($profile, $tmpFile, $contentType);
                } else {
                    // Not the last Profile so upload it asynchronously
                    $fileName = md5(uniqid()) . '.' . $extension;

                    $callback = function(Result $result) use ($em, $profile) {
                        // Update the Profile Photo
                        $profile->setPhoto($result['ObjectURL']);
                        $profile->setPhotoUploading(false);

                        // Flush to db
                        $em->flush($profile);
                    };

                    $this->get('service_photo_upload')->uploadPhotoAsync($tmpFile, $contentType, $callback, $fileName);
                }

                $i++;
            }

            return $this->redirect($this->generateUrl('profiles_view'));
        }

        return $this->render(
            'default/profiles_edit.html.twig',
            [
                'form' => $form->createView()
            ]
        );
    }

    /**
     * @Route("/profiles/editmq", name="profiles_editmq")
     * @param Request $request
     * @return Response
     */
    public function editProfilesMqAction(Request $request)
    {
        $form = $this->createForm(ProfilesType::class);

        if ('POST' === $request->getMethod()) {
            $em = $this->getDoctrine()->getManager();

            /** @var UploadedFile $uploadedFile */
            $uploadedFile = $request->files->get('profiles')['photoUpload'];
            $contentType = $uploadedFile->getMimeType();
            $extension = $uploadedFile->guessExtension();

            // Rename and Move the Uploaded File to uploads folder
            $tmpFile = $this->processUploadedFile($uploadedFile);

            $profiles = $em->getRepository('AppBundle:Profile')->findAll();

            // Put message on queue for each profile
            foreach ($profiles as $profile) {
                $fileName = md5(uniqid()) . '.' . $extension;

                // Send to the Queue
                $this->sendToQueue($profile, $tmpFile, $contentType, $fileName);
            }

            // Flush Changes to DB
            $em->flush();

            return $this->redirect($this->generateUrl('profiles_view'));
        }

        return $this->render(
            'default/profiles_edit.html.twig',
            [
                'form' => $form->createView()
            ]
        );
    }

    /**
     * @Route("/profile/{id}/editmq", name="profile_editmq")
     * @ParamConverter("profile", class="AppBundle:Profile")
     * @param Request $request
     * @param Profile $profile
     * @return Response
     */
    public function editProfileMqAction(Request $request, Profile $profile)
    {
        $form = $this->createForm(ProfileType::class, $profile);
        $form->handleRequest($request);

        if ($form->isValid()) {
            // $file stores the uploaded PDF file
            /** @var UploadedFile $tmpFile */
            $uploadedFile = $profile->getPhotoUpload();
            $contentType = $uploadedFile->getMimeType();

            // Rename and Move the Uploaded File to uploads folder
            $tmpFile = $this->processUploadedFile($uploadedFile);

            // Send to the Queue
            $this->sendToQueue($profile, $tmpFile, $contentType);

            // Flush Changes to DB
            $this->getDoctrine()->getManager()->flush($profile);

            // Redirect to the Profile Page
            return $this->redirect($this->generateUrl('profile_view', ['id' => $profile->getId()]));
        }

        return $this->render(
            'default/profile_edit.html.twig',
            [
                'form' => $form->createView(),
                'profile' => $profile
            ]
        );
    }

    /**
     * @Route("/profiles", name="profiles_view")
     */
    public function viewProfilesAction()
    {
        $profiles = $this->getDoctrine()->getManager()->getRepository('AppBundle:Profile')->findAll();

        $html = $this->container->get('templating')->render(
            'default/profiles.html.twig',
            array('profiles' => $profiles)
        );

        return new Response($html);
    }

    /**
     * Create Message and send to Queue
     *
     * @param Profile $profile
     * @param \SplFileInfo $file
     * @param string $contentType
     * @param string|null $fileName
     */
    protected function sendToQueue(Profile $profile, \SplFileInfo $file, $contentType, $fileName = null)
    {
        $message = [
            'profile_id' => $profile->getId(),
            'file_path' => $file->getPathname(),
            'content_type' => $contentType
        ];

        // If target filename supplied then use it
        if (null !== $fileName) {
            $message['file_name'] = $fileName;
        }

        // Put message on message queue
        $this->get('leezy.pheanstalk')->put(json_encode($message));

        // Mark the Photo as Uploading
        $profile->setPhotoUploading(true);
    }

    /**
     * Upload Profile Photo and update Profile
     *
     * @param Profile $profile
     * @param File $file
     * @param string $contentType
     */
    protected function uploadProfilePhoto(Profile $profile, File $file, $contentType)
    {
        // Upload the photo to S3
        $fileUrl = $this->get('service_photo_upload')->uploadPhoto($file, $contentType);

        // Update the Profile Photo
        $profile->setPhoto($fileUrl);
        $profile->setPhotoUploading(false);

        // Flush to DB
        $this->getDoctrine()->getManager()->flush($profile);
    }

    /**
     * Process the UploadedFile and move it to a permanent location
     *
     * @param UploadedFile $tmpFile
     * @return File
     */
    public function processUploadedFile(UploadedFile $tmpFile)
    {
        // Generate a unique name for the file before saving it
        $fileName = md5(uniqid()) . '.' . $tmpFile->guessExtension();

        // Move the file to the directory where brochures are stored
        $photosDir = $this->container->getParameter('kernel.root_dir') . '/../web/uploads/photos';
        $file = $tmpFile->move($photosDir, $fileName);

        return $file;
    }
}
