<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Security;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Enum\MemberRole;
use c975L\PilotBundle\Repository\MemberRepository;
use Symfony\Bundle\SecurityBundle\Security;

// What the signed-in account may do on the piloting pages: the admin everything, a member what its role in a space holds, read once per request
class PilotAccess
{
    // Seeing a space or a card
    public const string VIEW = 'view';

    // Ticking a card's boxes and adding feedback, a guest doing it on the cards given to it
    public const string WORK = 'work';

    // Writing a card or adding one to a space
    public const string EDIT = 'edit';

    // Handing a card to a machine
    public const string RUN = 'run';

    // Deleting a card for good
    public const string DELETE = 'delete';

    // Setting a space up and choosing its members
    public const string MANAGE = 'manage';

    // The role each level asks for in the space, a guest's work being checked against the card itself
    private const array LEVELS = [self::VIEW => MemberRole::Guest, self::WORK => MemberRole::Guest, self::EDIT => MemberRole::Contributor, self::RUN => MemberRole::Editor, self::DELETE => MemberRole::Editor, self::MANAGE => MemberRole::Owner];

    /** @var ?array<int, MemberRole> */
    private ?array $roles = null;

    private ?bool $admin = null;

    public function __construct(
        private readonly Security $security,
        private readonly ConfigServiceInterface $configService,
        private readonly MemberRepository $memberRepository,
    ) {
    }

    // Whether the account is the site's admin, who sees and does everything, read once
    public function isAdmin(): bool
    {
        return $this->admin ??= $this->security->isGranted((string) $this->configService->get('site-role-admin'));
    }

    // The signed-in account, null for a visitor or an account of another kind
    public function user(): ?UserInterface
    {
        $user = $this->security->getUser();

        return $user instanceof UserInterface ? $user : null;
    }

    // The account's role in a space, the admin owning them all, null when it is no member
    public function role(Project $project): ?MemberRole
    {
        if ($this->isAdmin()) {
            return MemberRole::Owner;
        }

        return $this->roles()[(int) $project->getId()] ?? null;
    }

    // Whether the account may do something on a space or a card: a guest only on the cards given to it
    public function can(Project | Card $subject, string $level): bool
    {
        $project = $subject instanceof Card ? $subject->getProject() : $subject;
        $role = $this->role($project);
        $needed = self::LEVELS[$level] ?? MemberRole::Owner;
        if (null === $role || !$role->holds($needed)) {
            return false;
        }
        if (MemberRole::Guest !== $role || !\in_array($level, [self::VIEW, self::WORK], true)) {
            return true;
        }
        $user = $this->user();

        return $subject instanceof Project || (null !== $user && $subject->isAssignedTo($user));
    }

    // What the account sees, for the lists of the piloting pages
    public function scope(): PilotScope
    {
        if ($this->isAdmin()) {
            return new PilotScope(true);
        }
        $full = [];
        $guest = [];
        foreach ($this->roles() as $id => $role) {
            if (MemberRole::Guest === $role) {
                $guest[] = $id;
            } else {
                $full[] = $id;
            }
        }

        return new PilotScope(false, $full, $guest, $this->user());
    }

    // The account's roles, read once
    /** @return array<int, MemberRole> */
    private function roles(): array
    {
        $user = $this->user();

        return $this->roles ??= null === $user ? [] : $this->memberRepository->rolesOf($user);
    }
}
