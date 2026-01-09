<?php

namespace App\Form;

use App\Entity\Generation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints\Unique;

class GenerationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('generationName', TextType::class, [
                'label' => 'Nom de la génération',
                'attr' => ['placeholder' => 'Ex: Ancêtres, Parents, Enfants...'],
            ])
            ->add('displayOrder', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'help' => 'Plus petit = plus haut dans l\'arbre. Chaque numéro d\'ordre doit être unique.',
                'attr' => ['min' => 0],
            ])
            ->add('save', SubmitType::class, ['label' => 'Enregistrer']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Generation::class,
        ]);
    }
}
