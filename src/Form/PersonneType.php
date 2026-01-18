<?php

namespace App\Form;

use App\Entity\Personne;
use App\Entity\Generation;
use App\Repository\GenerationRepository;
use App\Repository\PersonneRepository;
use App\Util\GenerationRules;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class PersonneType extends AbstractType
{
    private GenerationRepository $generationRepository;
    private PersonneRepository $personneRepository;

    public function __construct(GenerationRepository $generationRepository, PersonneRepository $personneRepository)
    {
        $this->generationRepository = $generationRepository;
        $this->personneRepository = $personneRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $originalPartners = new ArrayCollection();

        $builder
            ->add('prenom', TextType::class, [
                'required' => true,
                'attr' => ['placeholder' => 'Prénom'],
            ])
            ->add('nom', TextType::class, [
                'required' => true,
                'attr' => ['placeholder' => 'Nom'],
            ])
            ->add('naissance', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('genre', ChoiceType::class, [
                'choices'  => ['Homme' => 'M', 'Femme' => 'F'],
                'expanded' => true,
                'multiple' => false,
                'label'    => 'Sexe',
            ])
            ->add('mort', null, [
                'required' => false,
                'label' => 'Décédé',
            ])
            ->add('generation', EntityType::class, [
                'class' => Generation::class,
                'choice_label' => 'generationName',
                'placeholder' => 'Sélectionner une génération',
                'required' => true,
                'query_builder' => function ($gr) {
                    return $gr->createQueryBuilder('g')
                        ->orderBy('g.displayOrder', 'ASC');
                },
            ])
            ->add('pere', EntityType::class, [
                'class' => Personne::class,
                'choice_label' => 'fullName',
                'placeholder' => 'Aucun',
                'required' => false,
                'query_builder' => function (PersonneRepository $pr) use ($options) {
                    $qb = $pr->createQueryBuilder('p')
                        ->where('p.genre = :genre')
                        ->setParameter('genre', 'M');
                    if (!empty($options['current_person']) && $options['current_person']->getId()) {
                        $qb->andWhere('p.id != :id')->setParameter('id', $options['current_person']->getId());
                    }
                    return $qb;
                },
            ])
            ->add('mere', EntityType::class, [
                'class' => Personne::class,
                'choice_label' => 'fullName',
                'placeholder' => 'Aucune',
                'required' => false,
                'query_builder' => function (PersonneRepository $pr) use ($options) {
                    $qb = $pr->createQueryBuilder('p')
                        ->where('p.genre = :genre')
                        ->setParameter('genre', 'F');
                    if (!empty($options['current_person']) && $options['current_person']->getId()) {
                        $qb->andWhere('p.id != :id')->setParameter('id', $options['current_person']->getId());
                    }
                    return $qb;
                },
            ])
            ->add('partenaires', EntityType::class, [
                'class'         => Personne::class,
                'choice_label'  => 'fullName',
                'multiple'      => true,
                'expanded'      => false,
                'required'      => false,
                'label'         => 'Conjoint(s)',
            ])
            ->add('enfants', EntityType::class, [
                'class'         => Personne::class,
                'choice_label'  => 'fullName',
                'multiple'      => true,
                'expanded'      => false,
                'required'      => false,
                'mapped'        => false,
                'label'         => 'Enfant(s)',
                'query_builder' => function (PersonneRepository $pr) use ($options) {
                    $qb = $pr->createQueryBuilder('p');
                    if (!empty($options['current_person']) && $options['current_person']->getId()) {
                        $qb->where('p.id != :id')->setParameter('id', $options['current_person']->getId());
                    }
                    return $qb;
                },
            ])
            ->add('save', SubmitType::class, ['label' => 'Ajouter']);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {
            $personne = $event->getData();
            if (!$personne instanceof Personne) {
                return;
            }
            $displayOrder = $personne->getGeneration()?->getDisplayOrder();
            $this->addEnfantsField($event->getForm(), $displayOrder, $options);
            $this->addPartenairesField($event->getForm(), $displayOrder, $options);
            $this->addPereField($event->getForm(), $displayOrder, $options);
            $this->addMereField($event->getForm(), $displayOrder, $options);
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options) {
            $data = $event->getData();
            $genId = $data['generation'] ?? null;
            $displayOrder = null;

            if ($genId) {
                $generation = $this->generationRepository->find($genId);
                $displayOrder = $generation?->getDisplayOrder();
            }

            $this->addEnfantsField($event->getForm(), $displayOrder, $options);
            $this->addPartenairesField($event->getForm(), $displayOrder, $options);
            $this->addPereField($event->getForm(), $displayOrder, $options);
            $this->addMereField($event->getForm(), $displayOrder, $options);
        });

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($originalPartners) {
            $personne = $event->getData();
            if (!$personne instanceof Personne) {
                return;
            }
            foreach ($personne->getPartenaires() as $partner) {
                if (!$originalPartners->contains($partner)) {
                    $originalPartners->add($partner);
                }
            }
        });

        // Synchroniser les relations bidirectionnelles (enfants, conjoints, parents)
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($originalPartners) {
            $personne = $event->getData();

            if (!$personne instanceof Personne) {
                return;
            }

            $form = $event->getForm();

            // 1. Gérer les enfants avec attribution automatique père/mère selon le genre
            if ($form->has('enfants')) {
                /** @var Personne[] $selectedChildren */
                $selectedChildren = $form->get('enfants')->getData();
                $parentGen = $personne->getGeneration()?->getDisplayOrder();

                foreach ($selectedChildren as $child) {
                    $childGen = $child->getGeneration()?->getDisplayOrder();
                    if (!GenerationRules::isValidChildGeneration($parentGen, $childGen)) {
                        $form->get('enfants')->addError(new FormError('Les enfants doivent appartenir a une generation plus jeune.'));
                        continue;
                    }
                    if ($personne->getGenre() === 'M') {
                        $child->setPere($personne);
                    } else {
                        $child->setMere($personne);
                    }
                }
            }

            // 2. Synchroniser les conjoints (relation bidirectionnelle)
            if ($form->has('partenaires')) {
                /** @var Personne[] $selectedPartners */
                $selectedPartners = $form->get('partenaires')->getData();
                $personGen = $personne->getGeneration()?->getDisplayOrder();
                
                // Ajouter la personne aux conjoints de chacun
                foreach ($selectedPartners as $partner) {
                    $partnerGen = $partner->getGeneration()?->getDisplayOrder();
                    if (!GenerationRules::isValidPartnerGeneration($personGen, $partnerGen)) {
                        $form->get('partenaires')->addError(new FormError('Les partenaires doivent appartenir a la meme generation.'));
                        continue;
                    }
                    if (!$partner->getPartenaires()->contains($personne)) {
                        $partner->addPartenaire($personne);
                    }
                }
                
                // Retirer les conjoints qui ne sont plus sélectionnés
                foreach ($originalPartners as $existingPartner) {
                    if (!$selectedPartners->contains($existingPartner)) {
                        $existingPartner->removePartenaire($personne);
                    }
                }
            }

            // 3. Synchroniser les parents (relation bidirectionnelle)
            // Si on définit un père, ajouter l'enfant au père
            if ($form->has('pere')) {
                $pere = $form->get('pere')->getData();
                $childGen = $personne->getGeneration()?->getDisplayOrder();
                if ($pere) {
                    $parentGen = $pere->getGeneration()?->getDisplayOrder();
                    if (!GenerationRules::isValidParentGeneration($childGen, $parentGen)) {
                        $form->get('pere')->addError(new FormError('Le pere doit appartenir a une generation au-dessus.'));
                    }
                    if (!$pere->getEnfantPere()->contains($personne)) {
                        $pere->addEnfantPere($personne);
                    }
                }
            }

            // Si on définit une mère, ajouter l'enfant à la mère
            if ($form->has('mere')) {
                $mere = $form->get('mere')->getData();
                $childGen = $personne->getGeneration()?->getDisplayOrder();
                if ($mere) {
                    $parentGen = $mere->getGeneration()?->getDisplayOrder();
                    if (!GenerationRules::isValidParentGeneration($childGen, $parentGen)) {
                        $form->get('mere')->addError(new FormError('La mere doit appartenir a une generation au-dessus.'));
                    }
                    if (!$mere->getEnfantMere()->contains($personne)) {
                        $mere->addEnfantMere($personne);
                    }
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Personne::class,
            'current_person' => null,
        ]);
    }

    private function addEnfantsField(FormInterface $form, ?int $displayOrder, array $options): void
    {
        $form->add('enfants', EntityType::class, [
            'class'         => Personne::class,
            'choice_label'  => 'fullName',
            'multiple'      => true,
            'expanded'      => false,
            'required'      => false,
            'mapped'        => false,
            'label'         => 'Enfant(s)',
            'query_builder' => function (PersonneRepository $pr) use ($options, $displayOrder) {
                $qb = $pr->createQueryBuilder('p')
                    ->leftJoin('p.generation', 'g');
                if ($displayOrder === null) {
                    $qb->where('1 = 0');
                } else {
                    $qb->where('g.displayOrder > :order')
                        ->setParameter('order', $displayOrder);
                }
                if (!empty($options['current_person']) && $options['current_person']->getId()) {
                    $qb->andWhere('p.id != :id')->setParameter('id', $options['current_person']->getId());
                }
                return $qb;
            },
        ]);
    }

    private function addPartenairesField(FormInterface $form, ?int $displayOrder, array $options): void
    {
        $form->add('partenaires', EntityType::class, [
            'class'         => Personne::class,
            'choice_label'  => 'fullName',
            'multiple'      => true,
            'expanded'      => false,
            'required'      => false,
            'label'         => 'Conjoint(s)',
            'query_builder' => function (PersonneRepository $pr) use ($options, $displayOrder) {
                $qb = $pr->createQueryBuilder('p')
                    ->leftJoin('p.generation', 'g');
                if ($displayOrder === null) {
                    $qb->where('1 = 0');
                } else {
                    $qb->where('g.displayOrder = :order')
                        ->setParameter('order', $displayOrder);
                }
                if (!empty($options['current_person']) && $options['current_person']->getId()) {
                    $qb->andWhere('p.id != :id')->setParameter('id', $options['current_person']->getId());
                }
                return $qb;
            },
        ]);
    }

    private function addPereField(FormInterface $form, ?int $displayOrder, array $options): void
    {
        $form->add('pere', EntityType::class, [
            'class' => Personne::class,
            'choice_label' => 'fullName',
            'placeholder' => 'Aucun',
            'required' => false,
            'query_builder' => function (PersonneRepository $pr) use ($options, $displayOrder) {
                $qb = $pr->createQueryBuilder('p')
                    ->leftJoin('p.generation', 'g')
                    ->where('p.genre = :genre')
                    ->setParameter('genre', 'M');
                if ($displayOrder === null) {
                    $qb->andWhere('1 = 0');
                } else {
                    $qb->andWhere('g.displayOrder < :order')
                        ->setParameter('order', $displayOrder);
                }
                if (!empty($options['current_person']) && $options['current_person']->getId()) {
                    $qb->andWhere('p.id != :id')->setParameter('id', $options['current_person']->getId());
                }
                return $qb;
            },
        ]);
    }

    private function addMereField(FormInterface $form, ?int $displayOrder, array $options): void
    {
        $form->add('mere', EntityType::class, [
            'class' => Personne::class,
            'choice_label' => 'fullName',
            'placeholder' => 'Aucune',
            'required' => false,
            'query_builder' => function (PersonneRepository $pr) use ($options, $displayOrder) {
                $qb = $pr->createQueryBuilder('p')
                    ->leftJoin('p.generation', 'g')
                    ->where('p.genre = :genre')
                    ->setParameter('genre', 'F');
                if ($displayOrder === null) {
                    $qb->andWhere('1 = 0');
                } else {
                    $qb->andWhere('g.displayOrder < :order')
                        ->setParameter('order', $displayOrder);
                }
                if (!empty($options['current_person']) && $options['current_person']->getId()) {
                    $qb->andWhere('p.id != :id')->setParameter('id', $options['current_person']->getId());
                }
                return $qb;
            },
        ]);
    }
}
