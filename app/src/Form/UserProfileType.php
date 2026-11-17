<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class UserProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'usuario@email.com'
                ],
                'label' => 'Email',
                'label_attr' => [
                    'class' => 'form-label'
                ]
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => false,
                'first_options' => [
                    'label' => 'Nueva contraseña',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Mínimo 6 caracteres (dejar en blanco para conservar la actual)',
                        'autocomplete' => 'new-password'
                    ],
                    'label_attr' => ['class' => 'form-label']
                ],
                'second_options' => [
                    'label' => 'Confirmar nueva contraseña',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Repite la nueva contraseña',
                        'autocomplete' => 'new-password'
                    ],
                    'label_attr' => ['class' => 'form-label']
                ],
                'invalid_message' => 'Las contraseñas no coinciden.',
                'constraints' => [
                    new Length([
                        'min' => 6,
                        'minMessage' => 'La contraseña debe tener al menos {{ limit }} caracteres.',
                        'max' => 4096,
                    ]),
                ],
            ])
            ->add('companyName', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nombre de la Empresa'
                ],
                'label' => 'Nombre de la Empresa',
                'label_attr' => [
                    'class' => 'form-label'
                ],
                'required' => false
            ])
            ->add('taxId', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'NIF/CIF de la empresa'
                ],
                'label' => 'NIF/CIF',
                'label_attr' => [
                    'class' => 'form-label'
                ],
                'required' => false
            ])
            ->add('companyAddress', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Dirección de la empresa'
                ],
                'label' => 'Dirección',
                'label_attr' => [
                    'class' => 'form-label'
                ],
                'required' => false
            ])
            ->add('locality', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Localidad'
                ],
                'label' => 'Localidad',
                'label_attr' => [
                    'class' => 'form-label'
                ],
                'required' => false
            ])
            ->add('zipCode', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Código postal'
                ],
                'label' => 'Código Postal',
                'label_attr' => [
                    'class' => 'form-label'
                ],
                'required' => false
            ])
            ->add('companyPhone', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Teléfono de la empresa'
                ],
                'label' => 'Teléfono',
                'label_attr' => [
                    'class' => 'form-label'
                ],
                'required' => false
            ])
            ->add('companyProvince', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Provincia'
                ],
                'label' => 'Provincia',
                'label_attr' => [
                    'class' => 'form-label'
                ],
                'required' => false
            ])
            ->add('companyCountry', TextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'País'
                ],
                'label' => 'País',
                'label_attr' => [
                    'class' => 'form-label'
                ],
                'required' => false
            ])
            ->add('companyEmail', EmailType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'email@empresa.com'
                ],
                'label' => 'Email de la Empresa',
                'label_attr' => [
                    'class' => 'form-label'
                ],
                'required' => false
            ])
            ->add('companyIban', TextType::class, [
                'attr' => [
                    'class' => 'form-control font-monospace',
                    'placeholder' => 'ES00 0000 0000 0000 0000 0000',
                    'maxlength' => '34'
                ],
                'label' => 'IBAN de la Empresa (Para Remesas SEPA)',
                'label_attr' => [
                    'class' => 'form-label'
                ],
                'required' => false
            ])
            ->add('companyBic', TextType::class, [
                'attr' => [
                    'class' => 'form-control font-monospace',
                    'placeholder' => 'XXXXXXXXXXX',
                    'maxlength' => '11'
                ],
                'label' => 'BIC / SWIFT',
                'label_attr' => [
                    'class' => 'form-label'
                ],
                'required' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
