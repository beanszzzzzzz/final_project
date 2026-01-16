<?php

namespace App\DataFixtures;

use App\Entity\Customer;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CustomerFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $customers = [
            ['Karlo', 'karlo@gmail.com', '09987654321', 'San Jose, Negros Oriental'],
            ['Angelo', 'Angelo@gmail.com', '09234567899', 'San Jose, Negros Oriental'],
            ['Walk-in Customer', 'walkin@test.com', '09123456789', 'N/A'],
        ];

        foreach ($customers as [$name, $email, $phone, $address]) {
            $customer = new Customer();
            $customer->setName($name);
            $customer->setEmail($email);
            $customer->setPhone($phone);
            $customer->setAddress($address); // ⭐ REQUIRED

            $manager->persist($customer);
        }

        $manager->flush();
    }
}
