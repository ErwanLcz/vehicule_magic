<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class VehicleFilterTest extends WebTestCase
{
    public function testPageVehicleLoads()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/vehicles');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Liste des Véhicules Magiques');
        $this->assertEquals(3, $crawler->filter('.card')->count(), 'Aucun véhicule trouvé avec cette capacité.');

    }

    public function testFilterNotFoundByType()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/vehicles?form[type]=100');


        $this->assertResponseStatusCodeSame(422);
        //$this->assertSelectorExists('.invalid-feedback', 'The selected choice is invalid.');

        $this->assertEquals(3, $crawler->filter('.card')->count(), 'Aucun véhicule trouvé avec ce type.');

        // Vérification du nom du premier véhicule
        $vehicleName = $crawler->filter('.card-title a')->first()->text();
        $this->assertEquals('Carrosse Enchanté', $vehicleName);
    }

    public function testFilterByCapacity()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/vehicles?form[capacity]=4');

        $this->assertResponseIsSuccessful();
        $this->assertEquals(3, $crawler->filter('.card')->count(), 'Aucun véhicule trouvé avec cette capacité.');

        $vehicleName = $crawler->filter('.card-title a')->first()->text();
        $this->assertEquals('Carrosse Enchanté', $vehicleName);
    }

    public function testFilterByPriceRange()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/vehicles?form[priceMin]=10000&form[priceMax]=20000');

        $this->assertResponseIsSuccessful();
        $this->assertEquals(3, $crawler->filter('.card')->count(), 'Aucun véhicule trouvé avec cette capacité.');

        $vehicleName = $crawler->filter('.card-title a')->first()->text();
        $this->assertEquals('Vélo Magique', $vehicleName);
    }

    public function testFilterByEnchantments()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/vehicles?form[features][]=9&form[features][]=15');

        $this->assertResponseIsSuccessful();
        $this->assertEquals(2, $crawler->filter('.card')->count(), 'Aucun véhicule trouvé avec ces enchantements.');

        $vehicleName = $crawler->filter('.card-title a')->first()->text();
        $this->assertEquals('Scooter Envoûté', $vehicleName);
    }

    public function testFilterReturnsNoResults()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/vehicles?form[capacity]=1000');

        $this->assertResponseStatusCodeSame(422);
        $this->assertEquals(3, $crawler->filter('.card')->count(), 'Aucun véhicule trouvé avec cette capacité.');
        // $this->assertSelectorExists('.alert.alert-warning', 'Aucun véhicule magique trouvé.');

    }

    public function testVehiclePagination()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/vehicles?page=2');
        $this->assertResponseIsSuccessful();
        $this->assertEquals(3, $crawler->filter('.card')->count(), 'La page 2 ne contient pas 3 véhicules.');
        $this->assertSelectorTextContains('.card', 'Trottinette Sorcière');
        $crawler = $client->request('GET', '/vehicles?page=4');
        $this->assertResponseIsSuccessful();
        $this->assertEquals(1, $crawler->filter('.card')->count(), 'La page 4 ne contient pas 1 véhicule.');
        $this->assertSelectorTextContains('.card', 'Tapis Volant Élémentaire');
    }
}