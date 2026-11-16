<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\Subscription;
use App\Entity\SubscriptionType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Introduce el nombre'
                ],
                'label' => 'Nombre',
                'label_attr' => [
                    'class' => 'form-label small'
                ]
            ])
            ->add('surname', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Introduce los apellidos'
                ],
                'label' => 'Apellidos',
                'label_attr' => [
                    'class' => 'form-label small'
                ]
            ])
            ->add('address', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Introduce la dirección'
                ],
                'label' => 'Dirección',
                'label_attr' => [
                    'class' => 'form-label small'
                ]
            ])
            ->add('locality', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Introduce la localidad'
                ],
                'label' => 'Localidad',
                'label_attr' => [
                    'class' => 'form-label small'
                ]
            ])
            ->add('zipCode', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'CP'
                ],
                'label' => 'Código postal',
                'label_attr' => [
                    'class' => 'form-label small'
                ]
            ])
            ->add('birthDate', DateType::class, [
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ],
                'label' => 'Fecha de nacimiento',
                'label_attr' => [
                    'class' => 'form-label small'
                ]
            ])
            ->add('phone', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Introduce el teléfono'
                ],
                'label' => 'Teléfono',
                'label_attr' => [
                    'class' => 'form-label small'
                ]
            ])
            // Email y Facturable juntos para mejor visual
            ->add('email', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'cliente@email.com',
                    'type' => 'email'
                ],
                'label' => 'Email',
                'label_attr' => [
                    'class' => 'form-label small'
                ],
                'required' => false
            ])
            ->add('billable', CheckboxType::class, [
                'label' => 'Facturable',
                'label_attr' => [
                    'class' => 'form-check-label'
                ],
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'required' => false
            ])
            ->add('idNumber', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Introduce el DNI/NIE'
                ],
                'label' => 'DNI',
                'label_attr' => [
                    'class' => 'form-label small'
                ]
            ])
            ->add('iban', TextType::class, [
                'attr' => [
                    'class' => 'form-control font-monospace',
                    'placeholder' => 'ES00 0000 0000 0000 0000 0000',
                    'maxlength' => '34'
                ],
                'label' => 'Cuenta Bancaria (IBAN)',
                'label_attr' => [
                    'class' => 'form-label small'
                ],
                'required' => false
            ])
            ->add('startDate', DateType::class, [
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ],
                'label' => 'Fecha de inscripción',
                'label_attr' => [
                    'class' => 'form-label small'
                ]
            ])
            ->add('cardCode', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Escanea la tarjeta',
                    'readonly' => true,
                    'id' => 'cardCodeField'
                ],
                'label' => 'Código de tarjeta',
                'label_attr' => [
                    'class' => 'form-label small'
                ]
            ])
            ->add('photo', FileType::class, [
                'label' => 'Foto del cliente',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*'
                ],
                'label_attr' => [
                    'class' => 'form-label small'
                ]
            ])
            ->add('subscriptionType', EntityType::class, [
                'class' => SubscriptionType::class,
                'choice_label' => 'name',
                'mapped' => false,
                'required' => true,
                'label' => 'Tipo de suscripción',
                'attr' => [
                    'class' => 'form-control',
                    'data-subscription-type' => 'select' // Añadido para JS
                ],
                'label_attr' => [
                    'class' => 'form-label small'
                ]
            ])
            ->add('remainingPasses', null, [
                'mapped' => false,
                'required' => false,
                'label' => 'Pases restantes',
                'attr' => [
                    'class' => 'form-control',
                    'min' => '0'
                ],
                'label_attr' => [
                    'class' => 'form-label small'
                ]
            ])
            ->add('isPaid', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'label' => '¿Ha pagado este mes?',
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'label_attr' => [
                    'class' => 'form-check-label small'
                ]
            ])
            ->add('paymentUntil', DateType::class, [
                'mapped' => false,
                'widget' => 'single_text',
                'required' => false,
                'label' => 'Pago activo hasta',
                'attr' => [
                    'class' => 'form-control'
                ],
                'label_attr' => [
                    'class' => 'form-label small'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Client::class,
            'attr' => [
                'class' => 'needs-validation',
                'novalidate' => 'novalidate'
            ]
        ]);
    }
}