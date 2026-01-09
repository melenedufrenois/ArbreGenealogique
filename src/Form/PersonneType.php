<?php

namespace App\Form;

use App\Entity\Personne;
use App\Entity\Generation;
use App\Repository\PersonneRepository;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class PersonneType extends AbstractType
{
    private PersonneRepository $personneRepository;

    public function __construct(PersonneRepository $personneRepository)
    {
        $this->personneRepository = $personneRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
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

        // Gestion des enfants avec attribution automatique père/mère selon le genre
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $personne = $event->getData();

            if (!$personne instanceof Personne) {
                return;
            }

            $form = $event->getForm();
            if (!$form->has('enfants')) {
                return;
            }

            /** @var Personne[] $selectedChildren */
            $selectedChildren = $form->get('enfants')->getData();

            foreach ($selectedChildren as $child) {
                if ($personne->getGenre() === 'M') {
                    $child->setPere($personne);
                } else {
                    $child->setMere($personne);
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
}
