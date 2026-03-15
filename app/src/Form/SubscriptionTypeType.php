<?php

namespace App\Form;

use App\Entity\SubscriptionType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Positive;

class SubscriptionTypeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ej: Mensual, Trimestral, Pase Diario...'
                ],
                'label_attr' => [
                    'class' => 'form-label'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'El nombre es obligatorio']),
                    new Length([
                        'min' => 3,
                        'max' => 50,
                        'minMessage' => 'El nombre debe tener al menos {{ limit }} caracteres',
                        'maxMessage' => 'El nombre no puede tener más de {{ limit }} caracteres'
                    ])
                ],
                'help' => 'Nombre descriptivo para el tipo de suscripción'
            ])
            ->add('isDailyPass', CheckboxType::class, [
                'label' => '¿Es un pase diario?',
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'label_attr' => [
                    'class' => 'form-check-label'
                ],
                'required' => false
            ])
            ->add('months', NumberType::class, [
                'label' => 'Duración (meses)',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'placeholder' => 'Dejar vacío si se usa días'
                ],
                'label_attr' => [
                    'class' => 'form-label'
                ],
                'required' => false,
                'constraints' => [
                    new Positive(['message' => 'La duración en meses debe ser un número positivo'])
                ],
                'help' => 'Número de meses de duración (dejar vacío si se usa días)'
            ])
            ->add('days', NumberType::class, [
                'label' => 'Duración (días)',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'placeholder' => 'Dejar vacío si se usa meses'
                ],
                'label_attr' => [
                    'class' => 'form-label'
                ],
                'required' => false,
                'constraints' => [
                    new Positive(['message' => 'La duración en días debe ser un número positivo'])
                ],
                'help' => 'Número de días de duración (dejar vacío si se usa meses)'
            ])
            ->add('price', MoneyType::class, [
                'label' => 'Precio',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '0.00'
                ],
                'label_attr' => [
                    'class' => 'form-label'
                ],
                'currency' => 'EUR',
                'constraints' => [
                    new NotBlank(['message' => 'El precio es obligatorio']),
                    new Positive(['message' => 'El precio debe ser un valor positivo'])
                ],
                'help' => 'Precio en euros (€)'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SubscriptionType::class,
            'attr' => [
                'class' => 'needs-validation',
                'novalidate' => 'novalidate'
            ]
        ]);
    }
}