<?php

namespace App\Controller;

use App\Entity\Media;
use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\MediaRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/product')]
class ProductController extends AbstractController
{
    #[Route('/', name: 'app_product_index', methods: ['GET'])]
    public function index(ProductRepository $productRepository): Response
    {
        return $this->render('product/index.html.twig', [
            'products' => $productRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $files = $request->files->all()['product']['medias'];
            $imagesNames = $this->uploadFiles($files);

            $medias = $product->getMedias();

            foreach ($medias as $key => $media) {
                $media->setSrc($imagesNames[$key]);
            }

            $entityManager->persist($product);

            $entityManager->flush();

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    private function uploadFiles(array $files, array $imagesNames = []): array
    {
        foreach ($files as $imageFile) {
            if ($imageFile['file'] !== null) {
                $imageName = date('Y-m-d-H-i-s') . '-' . uniqid() . '.' . $imageFile['file']->getClientOriginalExtension();

                $imageFile['file']->move(
                    $this->getParameter('upload_dir'), // Chemin vers le répertoire d'upload
                    $imageName
                );

                $imagesNames[] = $imageName;
            } else {
                $imagesNames[] = $imageFile['file'];
            }
        }

        return $imagesNames;
    }

    public function deleteUploadedFiles(string $file): void
    {
        // verify file exists
       //if (file_exists($this->getParameter('upload_dir') . '/' . $file)) {
            unlink($this->getParameter('upload_dir') . '/' . $file);
        //}
    }

    #[Route('/{id}', name: 'app_product_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_product_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Product $product, EntityManagerInterface $entityManager, MediaRepository $mediaRepository): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $files = $request->files->all()['product']['medias'];
            $imagesNames = $this->uploadFiles($files);

            $medias = $product->getMedias();

            foreach ($medias as $key => $media) {
                if ($media->getSrc() === null) {
                    foreach ($imagesNames as $key => $imageName) {
                        if ($key !== null) {
                            $media->setSrc($imageName);
                        }
                    }
                }
                $entityManager->persist($media);
            }
            $entityManager->flush();
            // handle delete file from directory and media from database
            $mediasToDelete = $mediaRepository->findBy(['product' => null]);
            //dd($mediasToDelete);
            foreach ($mediasToDelete as $media) {
                $this->deleteUploadedFiles($media->getSrc());
                $entityManager->remove($media);
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_product_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->getPayload()->get('_token'))) {
            $entityManager->remove($product);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
    }

}
