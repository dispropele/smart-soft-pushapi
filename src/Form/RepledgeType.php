<?php
namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Positive;

class RepledgeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('paymentAmount', MoneyType::class, [
                'label'    => 'Сумма внесённого платежа (₽)',
                'currency' => 'RUB',
                'divisor'  => 1,
                'required' => true,
                'constraints' => [new Positive()],
                'attr' => ['min' => 0.01, 'step' => '0.01'],
                'help'  => 'Сначала гасятся проценты, остаток идёт на уменьшение тела займа.',
            ])
            ->add('extensionDays', IntegerType::class, [
                'label'   => 'Продление на (дней)',
                'data'    => 30,
                'attr'    => ['min' => 1, 'max' => 365],
            ])
            ->add('notes', TextareaType::class, [
                'label'    => 'Примечание',
                'required' => false,
                'attr'     => ['rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['accrued_interest' => 0.0]);
    }
}
