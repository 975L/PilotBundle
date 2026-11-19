<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Fixtures;

use c975L\ConfigBundle\Contract\UserInterface;
use Doctrine\ORM\Mapping as ORM;

// The account a member and an assigned card name by its interface, mapped for the SQLite schema of the tests, the application's own User being out of reach of a bundle's tests
#[ORM\Entity]
#[ORM\Table(name: 'pilot_user_fixture')]
class UserFixture implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 180)]
        private string $email = 'user-1@example.com',
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoles(): array
    {
        return [];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }
}
