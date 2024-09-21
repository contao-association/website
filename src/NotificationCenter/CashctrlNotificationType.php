<?php

declare(strict_types=1);

namespace App\NotificationCenter;

use Terminal42\NotificationCenterBundle\NotificationType\NotificationTypeInterface;
use Terminal42\NotificationCenterBundle\Token\Definition\AnythingTokenDefinition;
use Terminal42\NotificationCenterBundle\Token\Definition\EmailTokenDefinition;
use Terminal42\NotificationCenterBundle\Token\Definition\Factory\TokenDefinitionFactoryInterface;
use Terminal42\NotificationCenterBundle\Token\Definition\FileTokenDefinition;
use Terminal42\NotificationCenterBundle\Token\Definition\TextTokenDefinition;

class CashctrlNotificationType implements NotificationTypeInterface
{
    public const NAME = 'cashctrl';

    public function __construct(private readonly TokenDefinitionFactoryInterface $factory)
    {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getTokenDefinitions(): array
    {
        return [
            $this->factory->create(EmailTokenDefinition::class, 'member_email', 'cashctrl.member_email'),
            $this->factory->create(TextTokenDefinition::class, 'membership_label', 'cashctrl.membership_label'),
            $this->factory->create(TextTokenDefinition::class, 'invoice_number', 'cashctrl.invoice_number'),
            $this->factory->create(TextTokenDefinition::class, 'invoice_date', 'cashctrl.invoice_date'),
            $this->factory->create(TextTokenDefinition::class, 'invoice_due_days', 'cashctrl.invoice_due_days'),
            $this->factory->create(TextTokenDefinition::class, 'invoice_due_date', 'cashctrl.invoice_due_date'),
            $this->factory->create(TextTokenDefinition::class, 'invoice_total', 'cashctrl.invoice_total'),
            $this->factory->create(TextTokenDefinition::class, 'payment_status', 'cashctrl.payment_status'),
            $this->factory->create(TextTokenDefinition::class, 'payment_first', 'cashctrl.payment_first'),
            $this->factory->create(TextTokenDefinition::class, 'payment_interval', 'cashctrl.payment_interval'),
            $this->factory->create(TextTokenDefinition::class, 'payment_link', 'cashctrl.payment_link'),
            $this->factory->create(TextTokenDefinition::class, 'payment_date', 'cashctrl.payment_date'),
            $this->factory->create(TextTokenDefinition::class, 'payment_total', 'cashctrl.payment_total'),
            $this->factory->create(AnythingTokenDefinition::class, 'member_*', 'cashctrl.member_*'),
            $this->factory->create(FileTokenDefinition::class, 'invoice_pdf', 'cashctrl.invoice_pdf'),
        ];
    }
}
