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
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Security\PilotAccess;
use Symfony\Component\HttpFoundation\Response;

// A piloting page behind a signed-in account, with the spaces it sees and their open cards in the column on the left; the using controller holds $pilotAccess, $projectRepository and $cardRepository
trait PilotPageTrait
{
    // A piloting page, with the spaces the account sees and their open cards for the column on the left
    /** @param array<string, mixed> $parameters */
    private function page(string $view, array $parameters): Response
    {
        $scope = $this->pilotAccess->scope();

        return $this->render($view, $parameters + [
            'pilot_projects' => $this->projectRepository->findOrdered($scope),
            'pilot_open' => $this->cardRepository->countOpenByProject($scope),
            'pilot_admin' => $scope->all,
        ]);
    }

    // The piloting pages sit behind a signed-in account, what it sees following its spaces
    private function denyAccess(): void
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
    }

    // What belongs to the whole site (the settings, the balances, the order of the spaces) stays the admin's
    private function denyUnlessAdmin(): void
    {
        $this->denyAccess();
        if (!$this->pilotAccess->isAdmin()) {
            throw $this->createAccessDeniedException();
        }
    }

    // A space or a card the account does not see answers as if it did not exist; one it sees but may not act on so, refused
    private function guard(Project | Card $subject, string $level): void
    {
        $this->denyAccess();
        if (!$this->pilotAccess->can($subject, PilotAccess::VIEW)) {
            throw $this->createNotFoundException();
        }
        if (!$this->pilotAccess->can($subject, $level)) {
            throw $this->createAccessDeniedException();
        }
    }
}
