<?php

namespace App\Controller;

use App\Entity\Feature;
use App\Entity\TypeVehicle;
use App\Entity\Vehicle;
use App\Form\VehicleType;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

final class VehicleController extends AbstractController
{


    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('vehicle/index.html.twig', [

        ]);
    }

    #[Route('/vehicles', name: 'app_vehicle')]
    public function vehicles(Request $request,EntityManagerInterface $em,PaginatorInterface $paginator): Response
    {
        // Création du formulaire de filtrage
        $form = $this->createFormBuilder(null, ['method' => 'GET', 'csrf_protection' => false])
            ->add('type', EntityType::class, [
                'class' => TypeVehicle::class,
                'choice_label' => 'name',
                'placeholder' => 'Tous',
                'required' => false,
                'label' => 'Type de véhicule'
            ])
            ->add('capacity', IntegerType::class, [
                'required' => false,
                'label' => 'Capacité minimum',
                'constraints' => [
                    new Assert\GreaterThanOrEqual(0),
                    new Assert\LessThanOrEqual(10)
                ]
            ])
            ->add('priceMin', IntegerType::class, [
                'required' => false,
                'label' => 'Tarif minimum',
                'constraints' => [new Assert\GreaterThan(0)]
            ])
            ->add('priceMax', IntegerType::class, [
                'required' => false,
                'label' => 'Tarif maximum',
                'constraints' => [new Assert\GreaterThan(0)]
            ])
            ->add('features', EntityType::class, [
                'class' => Feature::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'label' => 'Enchantements'
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Appliquer les filtres'
            ])
            ->getForm();
        $form->handleRequest($request);
        $queryBuilder = $em->getRepository(Vehicle::class)->createQueryBuilder('v');

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            if (!empty($data['features'])) {
                foreach ($data['features'] as $index => $feature) {
                    $queryBuilder
                        ->andWhere(':feature' . $index . ' MEMBER OF v.features')
                        ->setParameter('feature' . $index, $feature);
                }
            }
            if (!empty($data['capacity'])) {
                $queryBuilder->andWhere('v.capacity >= :capacity')
                    ->setParameter('capacity', $data['capacity']);
            }

            if (!empty($data['priceMin'])) {
                $queryBuilder->andWhere('v.price >= :priceMin')
                    ->setParameter('priceMin', $data['priceMin']);
            }

            if (!empty($data['priceMax'])) {
                $queryBuilder->andWhere('v.price <= :priceMax')
                    ->setParameter('priceMax', $data['priceMax']);
            }
        }

        //$vehicles = $queryBuilder->getQuery()->getResult();
        $pagination = $paginator->paginate(
            $queryBuilder, // Requête Doctrine
            $request->query->getInt('page', 1), // Numéro de page (1 par défaut)
        );

        return $this->render('vehicle/vehicles.html.twig', [
            //'vehicles' => $vehicles,
            'pagination' => $pagination,
            'form' => $form
        ]);
    }

    #[Route('/reservation/{id}', name: 'reservation_vehicle')]
    public function reservation($id): Response
    {
       //throw 404 always
        throw $this->createNotFoundException('Les réservations ne sont pas encore disponibles.');
    }


    #[Route('/details/{id}', name: 'details_vehicle')]
    public function details(Vehicle $vehicle, EntityManagerInterface $em): Response
    {
        $features = $em->getRepository(Feature::class)->findAll();
        return $this->render('vehicle/details.html.twig', [
            'vehicle' => $vehicle,
            'features' => $features
        ]);
    }

    // Déclare une route associée à la création d'un nouveau produit
    #[Route('/admin/vehicle/edit_or_create/{id?}', name: 'vehicle_edit_or_create')]
    public function editOrCreate (Request $request, EntityManagerInterface $entityManager, ?Vehicle $vehicle = null): Response
    {
        // Crée une nouvelle instance de l'entité Product si aucun produit n'est fourni
        if (!$vehicle) {
            $vehicle = new Vehicle();
        }

        // Crée un formulaire basé sur ProductType et lui associe l'entité Product
        $form = $this->createForm(VehicleType::class, $vehicle);

        // Traite la requête HTTP et remplit le formulaire avec les données soumises
        $form->handleRequest($request);

        // Vérifie si le formulaire a été soumis et est valide
        if ($form->isSubmitted() && $form->isValid()) {
            $isNew = $vehicle->getId() === null;

            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile instanceof UploadedFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/assets/img';
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move($uploadsDir, $newFilename);
                $vehicle->setImagePath('img/' . $newFilename);
            }

            if( $vehicle->getImagePath() == null){
                $form->get('imageFile')->addError(new FormError("Veuillez télécharger une image valide (JPG ou PNG)."));
            }

            $typeVehicle = $vehicle->getTypeVehicle();

            if ($typeVehicle) {
                $featureNames = [];

                foreach ($vehicle->getFeatures() as $feature) {
                    $featureNames[] = $feature->getName();
                }



                if ($typeVehicle->getName() === 'Utilitaire' && !in_array('Caméra de recul', $featureNames)) {
                    $form->addError(new FormError('Un véhicule de type Utilitaire doit avoir la caractéristique "Caméra de recul".'));
                }

                if ($typeVehicle->getName() === '4x4' && !in_array('GPS', $featureNames)) {
                    $form->addError(new FormError('Un véhicule de type 4x4 doit avoir la caractéristique "GPS".'));
                }
            }

            if ($form->isValid()) { // Vérifie à nouveau après ajout des erreurs
                $entityManager->persist($vehicle);
                $entityManager->flush();

                $this->addFlash(
                    $isNew ? 'success' : 'warning',
                    $isNew ? 'Le véhicule a bien été créé.' : 'Le véhicule a bien été mis à jour.'
                );

                // Redirige vers la liste des produits après la création
                return $this->redirectToRoute('app_vehicle');
            }
        }

        // Affiche le formulaire dans le template Twig 'product/edit_or_create.html.twig'
        return $this->render('vehicle/edit_or_create.html.twig', [
            'form' => $form,
            'vehicle' => $vehicle
        ]);
    }

    #[Route('/admin/vehicle/confirm-delete/{id}', name: 'vehicle_confirm_delete', methods: ['GET', 'POST'])]
    public function confirmDelete(Request $request, EntityManagerInterface $entityManager, Vehicle $vehicle): Response
    {
        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('vehicle_confirm_delete', ['id' => $vehicle->getId()]))
            ->setMethod('POST')
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {


            $entityManager->remove($vehicle);
            $entityManager->flush();

            $this->addFlash('danger', 'Le véhicule a bien été supprimé.');

            return $this->redirectToRoute('app_vehicle');
        }

        return $this->render('vehicle/confirm_delete.html.twig', [
            'vehicle' => $vehicle,
            'form' => $form->createView(),
        ]);
    }


}
