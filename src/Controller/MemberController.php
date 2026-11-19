<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Controller;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\PilotBundle\Entity\Member;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Enum\MemberRole;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\MemberRepository;
use c975L\PilotBundle\Repository\ProjectRepository;
use c975L\PilotBundle\Security\PilotAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

// The members of a space, chosen by its owner: an account of the site added by its email with a role, its role changed, taken out
class MemberController extends AbstractController
{
    use PilotPageTrait;

    public const string ROUTE = 'pilot_members';

    public function __construct(
        private readonly PilotAccess $pilotAccess,
        private readonly ProjectRepository $projectRepository,
        private readonly CardRepository $cardRepository,
        private readonly MemberRepository $memberRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // The members with their role, and the form adding one
    #[Route('/pilot/space/{slug}/members', name: self::ROUTE, methods: ['GET'])]
    public function list(#[MapEntity(mapping: ['slug' => 'slug'])] Project $project): Response
    {
        $this->guard($project, PilotAccess::MANAGE);

        return $this->page('@c975LPilot/pilot/members.html.twig', ['project' => $project, 'members' => $this->memberRepository->findOfProject($project), 'roles' => MemberRole::cases()]);
    }

    // An account of the site made a member by its email, its role changed when it already is one; an unknown email refused
    #[Route('/pilot/space/{slug}/members', name: 'pilot_members_add', methods: ['POST'])]
    public function add(#[MapEntity(mapping: ['slug' => 'slug'])] Project $project, Request $request): RedirectResponse
    {
        $this->guard($project, PilotAccess::MANAGE);
        $role = MemberRole::tryFrom($request->request->getString('role'));
        $email = trim($request->request->getString('email'));
        if (null === $role || !$this->isCsrfTokenValid(self::ROUTE . $project->getId(), $request->request->getString('_token'))) {
            return $this->redirectToRoute(self::ROUTE, ['slug' => $project->getSlug()]);
        }
        $user = '' === $email ? null : $this->entityManager->getRepository(UserInterface::class)->findOneBy(['email' => $email]);
        if (!$user instanceof UserInterface) {
            $this->addFlash('danger', $this->translator->trans('label.member_unknown', ['%email%' => $email], 'pilot'));

            return $this->redirectToRoute(self::ROUTE, ['slug' => $project->getSlug()]);
        }
        $member = $this->memberRepository->findOneBy(['project' => $project, 'user' => $user]);
        if (null === $member) {
            $this->entityManager->persist(new Member($project, $user, $role));
        } else {
            $member->setRole($role);
        }
        $this->entityManager->flush();

        return $this->redirectToRoute(self::ROUTE, ['slug' => $project->getSlug()]);
    }

    // A member's role changed, or the member taken out with « remove »
    #[Route('/pilot/space/{slug}/members/{id}', name: 'pilot_members_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(#[MapEntity(mapping: ['slug' => 'slug'])] Project $project, int $id, Request $request): RedirectResponse
    {
        $this->guard($project, PilotAccess::MANAGE);
        $member = $this->memberRepository->find($id);
        if (null !== $member && $member->getProject() === $project && $this->isCsrfTokenValid(self::ROUTE . $project->getId(), $request->request->getString('_token'))) {
            $role = MemberRole::tryFrom($request->request->getString('role'));
            if ('remove' === $request->request->getString('role')) {
                $this->entityManager->remove($member);
            } elseif (null !== $role) {
                $member->setRole($role);
            }
            $this->entityManager->flush();
        }

        return $this->redirectToRoute(self::ROUTE, ['slug' => $project->getSlug()]);
    }
}
