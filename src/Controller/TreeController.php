<?php
namespace App\Controller;

use Exception;
use App\Entity\Personne;
use App\Entity\Generation;
use App\Form\PersonneType;
use App\Form\GenerationType;
use App\Repository\PersonneRepository;
use App\Repository\GenerationRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class TreeController extends AbstractController
{
    /**
     * @Route("/", name="family_tree")
     */
    public function index(PersonneRepository $personneRepository): Response
    {
        $personnes = $personneRepository->findAll(); // ou ton fetch actuel
        $layout = $this->layoutTreeByDisplayOrder($personnes);

        return $this->render('tree/index.html.twig', [
            'personnes' => $layout['ordered'],
            'xById' => $layout['xById'], // optionnel si tu veux positionner vraiment en X
        ]);
    }

    /**
     * @Route("/list", name="family_list")
     */
    public function list(PersonneRepository $personneRepository): Response
    {
        $personnes = $personneRepository->findAll();
        return $this->render('tree/list.html.twig', [
            'personnes' => $personnes,
        ]);
    }

    /**
     * @Route("/personne", name="personne_new")
     */
    public function new(Request $request, PersonneRepository $personneRepository): Response
    {
        $personne = new Personne();
        $form = $this->createForm(PersonneType::class, $personne, ['current_person' => $personne]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $personne = $form->getData();

            try {
                $personneRepository->add($personne, true);
            } catch (Exception $e){
                return $this->render('/personne/form.html.twig', [
                    'form' => $form->createView(),
                    'error' => true
                ]);
            }
            return $this->redirectToRoute('family_tree');
        }
        return $this->render('personne/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/personne/{id}/update", name="personne_edit")
     */
    public function edit($id, Request $request, PersonneRepository $personneRepository, EntityManagerInterface $em): Response
    {
        $personne = $personneRepository->find($id);
        $form = $this->createForm(PersonneType::class, $personne, ['current_person' => $personne]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($personne);
            $em->flush();
            return $this->redirectToRoute('family_tree');
        }

        return $this->render('personne/edit.html.twig', [
            'form' => $form->createView(),
            'personne' => $personne,
        ]);
    }


    /**
     * @Route("/personne/{id}/delete", name="personne_delete", methods={"POST"})
     */
    public function delete($id, Request $request, PersonneRepository $personneRepository, EntityManagerInterface $em): Response
    {
        $personne = $personneRepository->find($id);

        if (!$personne) {
            $this->addFlash('error', 'Personne introuvable.');
            return $this->redirectToRoute('family_list');
        }

        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('delete' . $personne->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('family_list');
        }

        $fullName = $personne->getFullName();

        // Nettoyer tous les enfants (elle était père ou mère)
        foreach ($personne->getEnfantMere()->toArray() as $enfant) {
            $enfant->setMere(null);
            $em->persist($enfant);
        }
        foreach ($personne->getEnfantPere()->toArray() as $enfant) {
            $enfant->setPere(null);
            $em->persist($enfant);
        }

        // Nettoyer les parents (si elle était enfant)
        if ($personne->getPere()) {
            $personne->getPere()->removeEnfantPere($personne);
            $em->persist($personne->getPere());
        }
        if ($personne->getMere()) {
            $personne->getMere()->removeEnfantMere($personne);
            $em->persist($personne->getMere());
        }

        // Supprimer les relations de partenaires (bidirectionnels)
        foreach ($personne->getPartenaires()->toArray() as $partenaire) {
            $personne->removePartenaire($partenaire);
            $partenaire->removePersonne($personne);
            $em->persist($partenaire);
        }
        foreach ($personne->getPersonnes()->toArray() as $p) {
            $personne->removePersonne($p);
            $p->removePartenaire($personne);
            $em->persist($p);
        }

        // Supprimer la personne
        $em->remove($personne);
        $em->flush();

        $this->addFlash('success', sprintf('%s a été supprimé(e).', $fullName));

        return $this->redirectToRoute('family_list');
    }

    /**
     * @Route("/admin/generations", name="generations_list")
     */
    public function generationsList(GenerationRepository $generationRepository): Response
    {
        $generations = $generationRepository->findAllOrderedByDisplay();
        return $this->render('admin/generations/list.html.twig', [
            'generations' => $generations,
        ]);
    }

    /**
     * @Route("/admin/generations/new", name="generation_new")
     */
    public function generationNew(Request $request, GenerationRepository $generationRepository): Response
    {
        $generation = new Generation();
        $form = $this->createForm(GenerationType::class, $generation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $generationRepository->add($generation, true);
            $this->addFlash('success', sprintf('Génération "%s" créée.', $generation->getGenerationName()));
            return $this->redirectToRoute('generations_list');
        }

        return $this->render('admin/generations/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/generations/{id}/edit", name="generation_edit")
     */
    public function generationEdit($id, Request $request, GenerationRepository $generationRepository, EntityManagerInterface $em): Response
    {
        $generation = $generationRepository->find($id);

        if (!$generation) {
            $this->addFlash('error', 'Génération introuvable.');
            return $this->redirectToRoute('generations_list');
        }

        $form = $this->createForm(GenerationType::class, $generation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($generation);
            $em->flush();
            $this->addFlash('success', sprintf('Génération "%s" modifiée.', $generation->getGenerationName()));
            return $this->redirectToRoute('generations_list');
        }

        return $this->render('admin/generations/edit.html.twig', [
            'form' => $form->createView(),
            'generation' => $generation,
        ]);
    }

    /**
     * @Route("/admin/generations/{id}/delete", name="generation_delete", methods={"POST"})
     */
    public function generationDelete($id, Request $request, GenerationRepository $generationRepository): Response
    {
        $generation = $generationRepository->find($id);

        if (!$generation) {
            $this->addFlash('error', 'Génération introuvable.');
            return $this->redirectToRoute('generations_list');
        }

        if (!$this->isCsrfTokenValid('delete' . $generation->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('generations_list');
        }

        $genName = $generation->getGenerationName();
        $generationRepository->remove($generation, true);
        $this->addFlash('success', sprintf('Génération "%s" supprimée.', $genName));

        return $this->redirectToRoute('generations_list');
    }

    /**
     * @param Personne[] $personnes
     * @return array{ordered: Personne[], xById: array<int,int>}
    */
    private function layoutTreeByDisplayOrder(array $personnes): array
    {
        // 1) Group by generation displayOrder
        $byGen = [];
        foreach ($personnes as $p) {
            $gen = $p->getGeneration();
            $order = $gen ? (int) $gen->getDisplayOrder() : 0;
            $byGen[$order][] = $p;
        }
        ksort($byGen);

        if (empty($byGen)) {
            return ['ordered' => [], 'xById' => []];
        }

        $genKeys = array_keys($byGen);

        // 2) Tri stable de la première génération (par nom/prénom)
        $firstGen = $genKeys[0];
        usort($byGen[$firstGen], fn($a,$b) => $this->personKey($a) <=> $this->personKey($b));

        // positions (index) de la gen précédente
        $prevPos = [];
        foreach ($byGen[$firstGen] as $i => $p) {
            if ($p->getId()) $prevPos[$p->getId()] = $i;
        }

        // x positions pour spacing (colonne) - optionnel
        $xById = [];
        foreach ($byGen[$firstGen] as $i => $p) {
            if ($p->getId()) $xById[$p->getId()] = $i * 2; // spacing = 2
        }

        // 3) Pour chaque génération suivante :
        for ($idx = 1; $idx < count($genKeys); $idx++) {
            $order = $genKeys[$idx];
            $list = $byGen[$order];

            // 3.a) Grouper par fratrie (mêmes parents)
            $groups = $this->groupSiblings($list);

            // 3.b) Ordonner les groupes par barycentre des parents
            usort($groups, function(array $gA, array $gB) use ($prevPos) {
                $a = $this->groupBarycenter($gA, $prevPos);
                $b = $this->groupBarycenter($gB, $prevPos);
                if ($a === $b) {
                    return $this->personKey($gA[0]) <=> $this->personKey($gB[0]);
                }
                return $a <=> $b;
            });

            // 3.c) Trier les frères/sœurs dans chaque groupe
            foreach ($groups as &$g) {
                usort($g, fn($a,$b) => $this->personKey($a) <=> $this->personKey($b));
            }
            unset($g);

            // 3.d) Spacing : assigner des positions X
            $newList = [];
            $cursorX = 0;

            foreach ($groups as $g) {
                $target = $this->groupBarycenter($g, $prevPos);
                $targetX = (int) round($target * 2);

                $cursorX = max($cursorX, $targetX);

                foreach ($g as $child) {
                    $newList[] = $child;

                    if ($child->getId()) {
                        $xById[$child->getId()] = $cursorX;
                    }

                    $cursorX += 2; // spacing entre personnes
                }

                $cursorX += 2; // spacing entre groupes
            }

            $byGen[$order] = $newList;

            // rebuild prevPos pour génération suivante
            $prevPos = [];
            foreach ($byGen[$order] as $i => $p) {
                if ($p->getId()) $prevPos[$p->getId()] = $i;
            }
        }

        // 4) Flatten
        $ordered = [];
        foreach ($byGen as $order => $list) {
            foreach ($list as $p) $ordered[] = $p;
        }

        return ['ordered' => $ordered, 'xById' => $xById];
    }

    private function personKey(Personne $p): string
    {
        return mb_strtolower(($p->getNom() ?? '') . '|' . ($p->getPrenom() ?? ''));
    }

    /**
     * @param Personne[] $list
     * @return array<int, Personne[]> groups
     */
    private function groupSiblings(array $list): array
    {
        $map = [];

        foreach ($list as $p) {
            $pereId = $p->getPere()?->getId() ?? 0;
            $mereId = $p->getMere()?->getId() ?? 0;

            $key = 'p' . (int)$pereId . '-m' . (int)$mereId;

            $map[$key][] = $p;
        }

        return array_values($map);
    }

    /**
     * @param Personne[] $group
     */
    private function groupBarycenter(array $group, array $prevPos): float
    {
        $scores = [];
        foreach ($group as $p) {
            $scores[] = $this->parentBarycenter($p, $prevPos);
        }
        return array_sum($scores) / max(1, count($scores));
    }

    private function parentBarycenter(Personne $p, array $prevPos): float
    {
        $positions = [];

        $pere = $p->getPere();
        if ($pere && $pere->getId() && isset($prevPos[$pere->getId()])) {
            $positions[] = $prevPos[$pere->getId()];
        }

        $mere = $p->getMere();
        if ($mere && $mere->getId() && isset($prevPos[$mere->getId()])) {
            $positions[] = $prevPos[$mere->getId()];
        }

        if (!$positions) {
            return 1e9; // pas de parent connu -> fin
        }

        return array_sum($positions) / count($positions);
    }
}