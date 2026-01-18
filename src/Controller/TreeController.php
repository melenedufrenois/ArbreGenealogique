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
        $personnes = $personneRepository->findAll();
        $layout = $this->layoutPivotTree($personnes);

        // Si ton twig/JS a besoin d'une liste plate :
        $flat = [];
        foreach ($layout['orderedByGen'] as $gen => $list) {
            foreach ($list as $p) $flat[] = $p;
        }

        return $this->render('tree/index.html.twig', [
            'personnes' => $flat,
            'xById' => $layout['xById'],
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
     * @Route("/personne/options", name="personne_options", methods={"GET"})
     */
    public function personneOptions(
        Request $request,
        GenerationRepository $generationRepository,
        PersonneRepository $personneRepository
    ): Response {
        $generationId = $request->query->getInt('generation_id');
        $currentId = $request->query->getInt('current_id') ?: null;

        $generation = $generationId ? $generationRepository->find($generationId) : null;
        if (!$generation) {
            return $this->json([
                'pere' => [],
                'mere' => [],
                'partenaires' => [],
                'enfants' => [],
            ]);
        }

        $order = (int) $generation->getDisplayOrder();

        $buildList = function(string $genre, string $operator) use ($personneRepository, $currentId, $order): array {
            $qb = $personneRepository->createQueryBuilder('p')
                ->innerJoin('p.generation', 'g')
                ->where('g.displayOrder ' . $operator . ' :order')
                ->setParameter('order', $order);

            if ($genre !== '') {
                $qb->andWhere('p.genre = :genre')
                    ->setParameter('genre', $genre);
            }

            if ($currentId) {
                $qb->andWhere('p.id != :id')
                    ->setParameter('id', $currentId);
            }

            $list = [];
            foreach ($qb->getQuery()->getResult() as $personne) {
                $list[] = [
                    'id' => $personne->getId(),
                    'label' => $personne->getFullName(),
                ];
            }
            return $list;
        };

        return $this->json([
            'pere' => $buildList('M', '<'),
            'mere' => $buildList('F', '<'),
            'partenaires' => $buildList('', '='),
            'enfants' => $buildList('', '>'),
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

        // Nettoyer les enfants (elle était père ou mère)
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
        }
        if ($personne->getMere()) {
            $personne->getMere()->removeEnfantMere($personne);
        }

        // Nettoyer les partenaires (relation bidirectionnelle)
        // removePersonne appelle automatiquement removePartenaire sur l'autre côté
        foreach ($personne->getPartenaires()->toArray() as $partenaire) {
            $personne->removePartenaire($partenaire);
            $partenaire->removePersonne($personne);
        }
        
        // Nettoyer les personnes liées (l'autre côté de la relation)
        foreach ($personne->getPersonnes()->toArray() as $p) {
            $personne->removePersonne($p);
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
    private function layoutPivotTree(array $personnes): array
    {
        // group by gen
        $byGen = [];
        foreach ($personnes as $p) {
            $byGen[$this->getGenOrder($p)][] = $p;
        }
        ksort($byGen);

        $pivot = $this->pickPivotGen($byGen);
        $xById = [];
        $orderedByGen = [];

        $childrenByParent = $this->buildChildrenIndex($personnes);

        // 1) place pivot: fratries + tri intra + couples
        $orderedByGen[$pivot] = $this->placeGenerationDown($byGen[$pivot] ?? [], $pivot, $xById);

        // 2) place upward: pivot-1, pivot-2...
        $genKeys = array_keys($byGen);
        $pivotIdx = array_search($pivot, $genKeys, true);

        for ($i = $pivotIdx - 1; $i >= 0; $i--) {
            $g = (int)$genKeys[$i];
            $orderedByGen[$g] = $this->placeGenerationUp($byGen[$g], $g, $childrenByParent, $xById);
        }

        // 3) place downward: pivot+1, pivot+2...
        for ($i = $pivotIdx + 1; $i < count($genKeys); $i++) {
            $g = (int)$genKeys[$i];
            $orderedByGen[$g] = $this->placeGenerationDown($byGen[$g], $g, $xById);
        }

        ksort($orderedByGen);

        return ['orderedByGen' => $orderedByGen, 'xById' => $xById];
    }


    private function getGenOrder(Personne $p): int
    {
        $g = $p->getGeneration();
        return $g ? (int)$g->getDisplayOrder() : 0;
    }

    private function hasAnyParent(Personne $p): bool
    {
        return $p->getPere() !== null || $p->getMere() !== null;
    }

    private function familyKey(Personne $p): string
    {
        $pereId = $p->getPere()?->getId() ?? 0;
        $mereId = $p->getMere()?->getId() ?? 0;
        // on garde père/mère distincts (si tu veux ignorer qui est père/mère: sort les ids)
        return 'p'.$pereId.'-m'.$mereId;
    }

    private function sortSiblings(array &$siblings): void
    {
        // Tri UNIQUEMENT à l'intérieur d'une fratrie :
        // - naissance si dispo
        // - sinon fallback nom/prenom
        usort($siblings, function(Personne $a, Personne $b) {
            $da = $a->getNaissance();
            $db = $b->getNaissance();

            if ($da && $db) {
                $cmp = $da <=> $db;
                if ($cmp !== 0) return $cmp;
            } elseif ($da && !$db) {
                return -1; // ceux avec date d'abord (optionnel)
            } elseif (!$da && $db) {
                return 1;
            }

            $ka = mb_strtolower(($a->getNom() ?? '').'|'.($a->getPrenom() ?? ''));
            $kb = mb_strtolower(($b->getNom() ?? '').'|'.($b->getPrenom() ?? ''));
            return $ka <=> $kb;
        });
    }

    private function groupByFamily(array $people): array
    {
        $map = [];
        foreach ($people as $p) {
            $map[$this->familyKey($p)][] = $p;
        }
        // transforme en liste de groupes
        return array_values($map);
    }

    private function pickPivotGen(array $byGen): int
    {
        $pivot = array_key_first($byGen);
        $max = -1;

        foreach ($byGen as $gen => $list) {
            $count = count($list);
            if ($count > $max) {
                $max = $count;
                $pivot = (int)$gen;
            }
        }
        return (int)$pivot;
    }

    private function avgXForChildren(Personne $parent, array $childrenByParentId, array $xById): ?float
    {
        $pid = $parent->getId();
        if (!$pid) return null;

        $children = $childrenByParentId[$pid] ?? [];
        $xs = [];
        foreach ($children as $child) {
            $cid = $child->getId();
            if ($cid && isset($xById[$cid])) {
                $xs[] = $xById[$cid];
            }
        }
        if (!$xs) return null;
        return array_sum($xs) / count($xs);
    }

    private function avgXForParents(Personne $child, array $xById): ?float
    {
        $xs = [];
        $pere = $child->getPere();
        $mere = $child->getMere();

        if ($pere && $pere->getId() && isset($xById[$pere->getId()])) $xs[] = $xById[$pere->getId()];
        if ($mere && $mere->getId() && isset($xById[$mere->getId()])) $xs[] = $xById[$mere->getId()];

        if (!$xs) return null;
        return array_sum($xs) / count($xs);
    }
    private function couplePass(array $genList, int $genOrder, array &$xById): void
    {
        $occupied = [];
        foreach ($genList as $p) {
            $id = $p->getId();
            if ($id && isset($xById[$id])) {
                $occupied[$xById[$id]] = true;
            }
        }

        foreach ($genList as $p) {
            $id = $p->getId();
            if (!$id || !isset($xById[$id])) continue;

            foreach ($p->getPartenaires() as $partner) {
                if (!$partner || !$partner->getId()) continue;

                if ($this->getGenOrder($partner) !== $genOrder) continue;

                $pid = $partner->getId();
                // si partenaire déjà placé, on ignore (on évite ping-pong)
                if (isset($xById[$pid])) continue;

                $targetX = $xById[$id] + 2; // juste à droite
                while (isset($occupied[$targetX])) $targetX += 2;

                $xById[$pid] = $targetX;
                $occupied[$targetX] = true;
                break;
            }
        }
    }
    private function placeGenerationDown(array $genPeople, int $genOrder, array &$xById): array
    {
        $groups = $this->groupByFamily($genPeople);

        // trie des groupes par ancrage parents (barycentre), fallback = "à droite"
        usort($groups, function(array $ga, array $gb) use ($xById) {
            $a = $this->groupAnchorFromParents($ga, $xById);
            $b = $this->groupAnchorFromParents($gb, $xById);
            return $a <=> $b;
        });

        // tri intra-fratrie par naissance (si dispo)
        foreach ($groups as &$g) $this->sortSiblings($g);
        unset($g);

        $placed = [];
        $occupied = [];
        $cursor = 0;

        foreach ($groups as $g) {
            $anchor = $this->groupAnchorFromParents($g, $xById);
            $cursor = max($cursor, $anchor);

            foreach ($g as $p) {
                $id = $p->getId();
                if (!$id) continue;

                while (isset($occupied[$cursor])) $cursor += 2;

                $xById[$id] = $cursor;
                $occupied[$cursor] = true;
                $placed[] = $p;

                $cursor += 2;
            }

            $cursor += 2; // espace entre fratries
        }

        // colle les conjoints côte à côte
        $this->couplePass($placed, $genOrder, $xById);

        return $placed;
    }

    private function groupAnchorFromParents(array $group, array $xById): int
    {
        // ancre = moyenne des ancres des membres (parents barycentre),
        // fallback raisonnable = très à droite mais pas infini
        $anchors = [];
        foreach ($group as $p) {
            $a = $this->avgXForParents($p, $xById);
            if ($a !== null) $anchors[] = $a;
        }
        if (!$anchors) return 9999; // ira à la fin
        return (int) round(array_sum($anchors) / count($anchors));
    }

    private function placeGenerationUp(array $genPeople, int $genOrder, array $childrenByParentId, array &$xById): array
    {
        // On trie les personnes de cette génération par ancrage "où sont mes enfants"
        usort($genPeople, function(Personne $a, Personne $b) use ($childrenByParentId, $xById) {
            $aa = $this->avgXForChildren($a, $childrenByParentId, $xById);
            $bb = $this->avgXForChildren($b, $childrenByParentId, $xById);

            $aa = $aa ?? 9999;
            $bb = $bb ?? 9999;

            if ($aa === $bb) {
                $ka = mb_strtolower(($a->getNom() ?? '').'|'.($a->getPrenom() ?? ''));
                $kb = mb_strtolower(($b->getNom() ?? '').'|'.($b->getPrenom() ?? ''));
                return $ka <=> $kb;
            }
            return $aa <=> $bb;
        });

        $placed = [];
        $occupied = [];
        $cursor = 0;

        foreach ($genPeople as $p) {
            $id = $p->getId();
            if (!$id) continue;

            $anchor = $this->avgXForChildren($p, $childrenByParentId, $xById);
            $anchor = $anchor !== null ? (int)round($anchor) : 9999;
            $cursor = max($cursor, $anchor);

            while (isset($occupied[$cursor])) $cursor += 2;

            $xById[$id] = $cursor;
            $occupied[$cursor] = true;
            $placed[] = $p;

            $cursor += 2;
        }

        $this->couplePass($placed, $genOrder, $xById);

        return $placed;
    }

    private function buildChildrenIndex(array $personnes): array
    {
        // parentId => Personne[]
        $childrenByParent = [];

        foreach ($personnes as $c) {
            $pere = $c->getPere();
            $mere = $c->getMere();

            if ($pere && $pere->getId()) $childrenByParent[$pere->getId()][] = $c;
            if ($mere && $mere->getId()) $childrenByParent[$mere->getId()][] = $c;
        }

        return $childrenByParent;
    }
}

