<?php
namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Positive;

class SellItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('soldPrice', MoneyType::class, [
                'label'       => 'Фактическая цена продажи (₽)',
                'currency'    => 'RUB',
                'divisor'     => 1,
                'constraints' => [new Positive()],
                'attr'        => ['min' => 0.01, 'step' => '0.01'],
            ])
            ->add('notes', TextareaType::class, [
                'label'    => 'Примечание к продаже',
                'required' => false,
                'attr'     => ['rows' => 2],
            ]);
    }
}
