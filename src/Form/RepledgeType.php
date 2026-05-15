<?php

namespace App\Form;

use App\Entity\LoanTicket;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Positive;

class RepledgeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var LoanTicket $ticket */
        $ticket = $options['loan_ticket'];
        $totalDebt = $ticket->getTotalDebt();

        $builder
            ->add('paymentAmount', MoneyType::class, [
                'label' => 'Сумма внесённого платежа (₽)',
                'currency' => 'RUB',
                'divisor' => 1,
                'required' => true,
                'constraints' => [new Positive()],
                'attr' => ['min' => 0.01, 'step' => '0.01', 'max' => $totalDebt],
                'help' => sprintf(
                    'Максимум: %.2f ₽ (тело + проценты). При полной оплате билет закрывается.',
                    $totalDebt
                ),
            ]);

        // Add checkboxes for each pledged item
        foreach ($ticket->getPledgedItems() as $item) {
            $builder->add('redeem_' . $item->getId(), CheckboxType::class, [
                'label' => false,
                'required' => false,
                'mapped' => false,
            ]);
        }

        $builder
            ->add('extensionDays', IntegerType::class, [
                'label' => 'Продление на (дней)',
                'data' => 30,
                'attr' => ['min' => 1, 'max' => 365],
                'help' => 'Используется только при перезалоге (если сумма меньше полного долга)',
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Примечание',
                'required' => false,
                'attr' => ['rows' => 3],
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($ticket): void {
            $form = $event->getForm();
            if (!$form->isSubmitted() || !$form->isValid()) {
                return;
            }

            $payment = (float) ($form->get('paymentAmount')->getData() ?? 0);
            $max = $ticket->getTotalDebt();

            if ($payment > $max + 0.001) {
                $form->get('paymentAmount')->addError(new FormError(sprintf(
                    'Сумма не может превышать долг %.2f ₽ (тело займа + проценты).',
                    $max
                )));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['loan_ticket']);
        $resolver->setAllowedTypes('loan_ticket', LoanTicket::class);
        $resolver->setDefaults(['accrued_interest' => 0.0]);
    }
}
