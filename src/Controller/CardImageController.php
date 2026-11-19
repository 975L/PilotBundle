<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Controller;

use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\ProjectRepository;
use c975L\PilotBundle\Security\PilotAccess;
use c975L\PilotBundle\Service\CardImages;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

// The picture of a card (a sheet's portrait): shown to whoever sees the card, sent or removed by whoever edits it, from its dialog or its page
class CardImageController extends AbstractController
{
    use PilotPageTrait;

    public function __construct(
        private readonly PilotAccess $pilotAccess,
        private readonly ProjectRepository $projectRepository,
        private readonly CardRepository $cardRepository,
        private readonly CardImages $cardImages,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // The picture under the type of its extension, never run as anything else
    #[Route('/pilot/card/{id}/image', name: 'pilot_card_image', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Card $card): BinaryFileResponse
    {
        $this->guard($card, PilotAccess::VIEW);
        $path = $this->cardImages->path($card);
        $type = null === $path ? null : $this->cardImages->type($path);
        if (null === $path || null === $type) {
            throw $this->createNotFoundException();
        }

        return new BinaryFileResponse($path, Response::HTTP_OK, ['Content-Type' => $type, 'X-Content-Type-Options' => 'nosniff']);
    }

    // A picture sent replaces the one the card had, « remove » takes it off; the dialog then reads the card again
    #[Route('/pilot/card/{id}/image', name: 'pilot_card_image_set', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function set(Card $card, Request $request): Response
    {
        $this->guard($card, PilotAccess::EDIT);
        $refusal = $this->apply($card, $request);
        if (null === $refusal) {
            $this->entityManager->flush();
        }
        if ($request->headers->has(PilotController::DIALOG_HEADER)) {
            return null === $refusal ? new Response(null, Response::HTTP_NO_CONTENT) : new Response($refusal, Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (null !== $refusal) {
            $this->addFlash('danger', $refusal);
        }

        return $this->redirectToRoute(PilotController::CARD_ROUTE, ['id' => $card->getId()]);
    }

    // The refusal to show, null once the picture is kept or removed
    private function apply(Card $card, Request $request): ?string
    {
        if (!$this->isCsrfTokenValid(PilotController::GESTURE_ROUTE . $card->getId(), $request->request->getString('_token'))) {
            return $this->translator->trans('label.gesture_expired', [], 'pilot');
        }
        if ($request->request->getBoolean('remove')) {
            $this->cardImages->remove($card);

            return null;
        }
        $file = $request->files->get('image');

        return $file instanceof UploadedFile && $this->cardImages->store($card, $file) ? null : $this->translator->trans('label.card_image_refused', [], 'pilot');
    }
}
