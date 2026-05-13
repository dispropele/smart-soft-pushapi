<?php

namespace App\Admin;

class AdminFormAttributes
{
    public static function passportNumber(): array
    {
        return ['attr' => [
            'inputmode' => 'numeric',
            'pattern'   => '\d{6}',
            'maxlength' => '6',
        ]];
    }

    public static function passportSeries(): array
    {
        return ['attr' => [
            'inputmode' => 'numeric',
            'pattern'   => '\d{4}',
            'maxlength' => '4',
        ]];
    }

    public static function phoneDigits(): array
    {
        return ['attr' => [
            'inputmode' => 'numeric',
            'pattern'   => '\d{10,11}',
            'maxlength' => '11',
        ]];
    }

    public static function slugCode(): array
    {
        return ['attr' => [
            'pattern'   => '[a-z0-9_]*',
            'maxlength' => '50',
        ]];
    }

    public static function phoneMask(): array
    {
        return ['attr' => [
            'data-admin-mask' => 'phone',
            'placeholder'     => '+7 (999) 999-99-99',
            'inputmode'       => 'tel',
            'maxlength'       => '18',
        ]];
    }
}