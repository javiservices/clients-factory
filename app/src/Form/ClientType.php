<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\Subscription;
use App\Entity\SubscriptionType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('clientNumber', IntegerType::class, [
                'label' => 'Nº de Socio / ID Cliente',
                'required' => false,
                'attr' => [
                    'class' => 'form-control font-monospace fw-bold',
                    'placeholder' => 'Ej: 4780',
                    'min' => 1
                ],
                'label_attr' => [
                    'class' => 'form-label small fw-bold'
                ],
                'help' => 'ID de socio correlativo. Se autocalcula automáticamente, pero puedes modificarlo para sincronizar con la otra aplicación.',
                'help_attr' => [
                    'class' => 'form-text text-muted'
                ]
            ])
            ->add('name', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Introduce el nombre'
                ],
                'label' => 'Nombre',
                'label_attr' => [
                    'class' => 'form-label small required'
                ],
                'required' => true,
                'empty_data' => '',
            ])
            ->add('surname', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Introduce los apellidos'
                ],
                'label' => 'Apellidos',
                'label_attr' => [
                    'class' => 'form-label small'
                ],
                'required' => false,
                'empty_data' => '',
            ])
            ->add('address', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Introduce la dirección'
                ],
                'label' => 'Dirección',
                'label_attr' => [
                    'class' => 'form-label small'
                ],
                'required' => false,
            ])
            ->add('locality', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Introduce la localidad'
                ],
                'label' => 'Localidad',
                'label_attr' => [
                    'class' => 'form-label small'
                ],
                'required' => false,
            ])
            ->add('zipCode', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'CP'
                ],
                'label' => 'Código postal',
                'label_attr' => [
                    'class' => 'form-label small'
                ],
                'required' => false,
            ])
            ->add('birthDate', DateType::class, [
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ],
                'label' => 'Fecha de nacimiento',
                'label_attr' => [
                    'class' => 'form-label small'
                ],
                'required' => false,
            ])
            ->add('phone', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Introduce el teléfono'
                ],
                'label' => 'Teléfono',
                'label_attr' => [
                    'class' => 'form-label small'
                ],
                'required' => false,
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
                'label' => 'DNI / NIE',
                'label_attr' => [
                    'class' => 'form-label small required'
                ],
                'required' => true,
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
                    'class' => 'form-label small required'
                ],
                'required' => true,
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
                ],
                'required' => false,
            ])
            ->add('dailyAccessLimit', IntegerType::class, [
                'label' => 'Accesos al día',
                'required' => false,
                'empty_data' => '1',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '1 (Por defecto)',
                    'min' => 0,
                    'max' => 99,
                ],
                'label_attr' => [
                    'class' => 'form-label small fw-semibold'
                ],
                'help' => 'Por defecto 1. Configura más si el socio entrena varias veces al día. 0 = Ilimitado.',
                'help_attr' => [
                    'class' => 'form-text text-muted small'
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
                'data' => $options['default_subscription_type'] ?? null,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('st')
                        ->addSelect("CASE WHEN st.name = 'SALA-FITNESS' THEN 0 WHEN st.name LIKE '%SALA-FITNESS%' THEN 1 ELSE 2 END AS HIDDEN ord")
                        ->orderBy('ord', 'ASC')
                        ->addOrderBy('st.name', 'ASC');
                },
                'choice_attr' => function (?SubscriptionType $choice) {
                    if (!$choice) {
                        return [];
                    }
                    return [
                        'data-price' => number_format((float) ($choice->getPrice() ?? 0), 2, '.', ''),
                        'data-months' => (string) ($choice->getMonths() ?? ''),
                        'data-days' => (string) ($choice->getDays() ?? ''),
                        'data-is-daily-pass' => $choice->isDailyPass() ? '1' : '0',
                    ];
                },
                'label' => 'Tipo de suscripción',
                'attr' => [
                    'class' => 'form-control',
                    'data-subscription-type' => 'select' // Añadido para JS
                ],
                'label_attr' => [
                    'class' => 'form-label small required'
                ]
            ])
            ->add('paymentAmount', NumberType::class, [
                'mapped' => false,
                'required' => false,
                'scale' => 2,
                'label' => 'Importe a cobrar (€)',
                'attr' => [
                    'class' => 'form-control',
                    'step' => '0.01',
                    'min' => '0',
                    'placeholder' => '50.00',
                    'id' => 'paymentAmountField'
                ],
                'label_attr' => [
                    'class' => 'form-label small'
                ],
                'help' => 'Importe a facturar (ej: 50 € mes completo o 30 € medio mes).',
                'help_attr' => [
                    'class' => 'form-text text-muted'
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
            ->add('isUnlimited', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Acceso ilimitado / Permanente (sin fecha de fin)',
                'attr' => [
                    'class' => 'form-check-input',
                    'id' => 'isUnlimitedCheckbox',
                    'role' => 'switch',
                ],
                'label_attr' => [
                    'class' => 'form-check-label small fw-semibold text-primary',
                ],
                'help' => 'Si se activa, el socio tendrá acceso permanente sin fecha de caducidad.',
                'help_attr' => [
                    'class' => 'form-text text-muted small',
                ],
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
            ])
            ->add('welcomeMessage', TextareaType::class, [
                'required' => false,
                'label' => 'Mensaje de bienvenida en el torno',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 2,
                    'placeholder' => 'Ej: ¡A por todas hoy!, Felicidades por tu cumpleaños, Pasa por recepción...'
                ],
                'label_attr' => [
                    'class' => 'form-label small'
                ],
                'help' => 'Mensaje personalizado que se mostrará en pantalla cuando este socio pase por el torno.',
                'help_attr' => [
                    'class' => 'form-text text-muted'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Client::class,
            'default_subscription_type' => null,
            'attr' => [
                'class' => 'needs-validation',
                'novalidate' => 'novalidate'
            ]
        ]);
    }
}