<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TestPresenceTest extends WebTestCase
{
    public function testContact()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/contact');

        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Envoyer')->form([
            'form[name]' => 'Erwan',
            'form[email]' => 'erwan.lecoz05@gmail.com',
            'form[message]' => 'Test',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/contact');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-info', 'Votre message :');
        $this->assertSelectorTextContains('.alert-info', 'Nom du Sorcier : Erwan');
        $this->assertSelectorTextContains('.alert-info', 'Adresse de Chouette Postale : erwan.lecoz05@gmail.com');
        $this->assertSelectorTextContains('.alert-info', 'Message : Test');
    }
}
