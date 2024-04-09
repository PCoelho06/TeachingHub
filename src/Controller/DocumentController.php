<?php

namespace App\Controller;

use DateTimeImmutable;
use App\Entity\Document;
use App\Form\DocumentType;
use App\Data\SearchFilters;
use App\Form\DocumentSearchType;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

#[Route('/documents', name: 'document_')]
class DocumentController extends AbstractController
{
    #[Route('/update-downloads/{id}', name: 'update_downloads')]
    public function updateDownloads(Document $document, SerializerInterface $serializer, EntityManagerInterface $entityManager): JsonResponse
    {
        if ($document->getDownloaders()->contains($this->getUser())) {
            return $this->json($serializer->serialize(["proceed" => false, "message" => "Already downloaded"], 'json'));
        }

        $document->setDownloadsNumber($document->getDownloadsNumber() + 1);
        if ($this->getUser()) {
            $document->addDownloader($this->getUser());
        }

        $entityManager->persist($document);
        $entityManager->flush();

        return $this->json($serializer->serialize(["proceed" => true, "message" => "Downloaded"], 'json'));
    }

    #[Route('/get-filtered-documents', name: 'filtered')]
    #[Route('/chercher-un-document', name: 'search')]
    public function search(Request $request, DocumentRepository $documentRepository, SerializerInterface $serializer): Response
    {
        $filters = new SearchFilters();
        $offset = max(0, $request->query->getInt('offset', 0));

        $form = $this->createForm(DocumentSearchType::class, $filters);
        $form->handleRequest($request);

        $paginator = $documentRepository->findBySearchCriteria($filters, $offset);

        if ($request->attributes->get('_route') == 'document_search') {
            return $this->render('document/search.html.twig', [
                'form' => $form->createView(),
                'documents' => $paginator,
                'previous' => $offset - DocumentRepository::DOCUMENTS_PER_PAGE,
                'next' => min(count($paginator), $offset + DocumentRepository::DOCUMENTS_PER_PAGE),
            ]);
        } else {
            // $data = $serializer->serialize($paginator, 'json', [
            //     ObjectNormalizer::CIRCULAR_REFERENCE_HANDLER => function (object $object, string $format, array $context): int {
            //         return $object->getId();
            //     },
            //     ObjectNormalizer::ENABLE_MAX_DEPTH => true
            // ]);

            // return $this->json($data);
            return $this->render('document/results.html.twig', [
                'form' => $form->createView(),
                'documents' => $paginator,
                'previous' => $offset - DocumentRepository::DOCUMENTS_PER_PAGE,
                'next' => min(count($paginator), $offset + DocumentRepository::DOCUMENTS_PER_PAGE),
            ]);
        }
    }

    #[Route('/deposer-un-document', name: 'add')]
    #[Route('/editer-un-document/{slug}', name: 'update')]
    public function handleDocument(Document $document = null, Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        if (is_null($document)) {
            $document = new Document();
            $document->setDownloadsNumber(0);
            $edit = false;
        } else {
            $edit = true;
            if (!$this->getUser()) {
                $this->addFlash(
                    'danger',
                    'Attention, vous devez vous connecter pour modifier ce document.'
                );
                return $this->redirect('app_login');
            } else if ($this->getUser() != $document->getAuthor()) {
                $this->addFlash(
                    'danger',
                    'Attention, vous n\'avez pas les droits pour modifier ce document'
                );
                return $this->redirectToRoute('document_show', [
                    'slug' => $document->getSlug(),
                ]);
            }
        }

        $form = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();

            $slug = $slugger->slug($document->getTitle());
            $filename = uniqid() . '.' . $slug . '.' . $file->guessExtension();

            try {
                $file->move(
                    $this->getParameter('documents_directory'),
                    $filename
                );
            } catch (FileException $e) {
                $this->addFlash(
                    'danger',
                    'Votre document n\'a pas pu être téléchargé. Si le problème persiste, merci de nous contacter.'
                );

                if (is_null($document->getId())) {
                    return $this->redirect('document_add');
                } else {
                    return $this->redirectToRoute('document_update', [
                        'slug' => $document->getSlug(),
                    ]);
                }
            }

            if (!is_null($document->getId())) {
                $document->setUpdatedAt(new DateTimeImmutable());
            } else {
                $document->setUploadedAt(new DateTimeImmutable());
            }

            $document->setSlug($slug)
                ->setFile($filename)
                ->setAuthor($this->getUser());

            $entityManager->persist($document);
            $entityManager->flush();

            return $this->redirectToRoute('document_show', ['slug' => $slug]);
        }

        return $this->render('document/handle.html.twig', [
            'form' => $form,
            'edit' => $edit,
        ]);
    }

    #[Route('/{slug}', name: 'show')]
    public function showDocument(Document $document, DocumentRepository $documentRepository): Response
    {
        $suggestions = $documentRepository->findSuggestions($document);

        return $this->render('document/show.html.twig', [
            'document' => $document,
            'suggestions' => $suggestions
        ]);
    }

    #[Route('/supprimer-un-document/{slug}', name: 'delete')]
    public function delete(Document $document, EntityManagerInterface $entityManager): Response
    {
        // supprimer le document du dossier upload
        unlink($this->getParameter('documents_directory') . '/' . $document->getFile());

        $entityManager->remove($document);
        $entityManager->flush();

        return $this->redirectToRoute('user_documents_show');
    }
}
