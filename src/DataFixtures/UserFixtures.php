<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $verifiedAt = new \DateTimeImmutable();

        // Create ADMIN user
        $admin = new User();
        $admin->setEmail('admin@binscafe.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setIsActive(true);
        $admin->setIsVerified(true);
        $admin->setVerifiedAt($verifiedAt);
        $hashedPassword = $this->passwordHasher->hashPassword(
            $admin,
            'Admin123!'
        );
        $admin->setPassword($hashedPassword);
        $manager->persist($admin);

        // Create STAFF user
        $staff = new User();
        $staff->setEmail('staff@binscafe.com');
        $staff->setRoles(['ROLE_STAFF']);
        $staff->setIsActive(true);
        $staff->setIsVerified(true);
        $staff->setVerifiedAt($verifiedAt);
        $hashedPassword = $this->passwordHasher->hashPassword(
            $staff,
            'Staff123!'
        );
        $staff->setPassword($hashedPassword);
        $manager->persist($staff);

        // Create CUSTOMER user
        $customer = new User();
        $customer->setEmail('customer@binscafe.com');
        $customer->setRoles(['ROLE_USER']);
        $customer->setIsActive(true);
        $customer->setIsVerified(true);
        $customer->setVerifiedAt($verifiedAt);
        $hashedPassword = $this->passwordHasher->hashPassword(
            $customer,
            'Customer123!'
        );
        $customer->setPassword($hashedPassword);
        $manager->persist($customer);

        $manager->flush();
    }
}
