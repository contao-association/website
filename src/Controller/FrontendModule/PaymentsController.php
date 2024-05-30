<?php

declare(strict_types=1);

namespace App\Controller\FrontendModule;

use App\StripeHelper;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\Template;
use Oneup\ContaoSentryBundle\ErrorHandlingTrait;
use Stripe\Card;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsFrontendModule(category: 'user')]
class PaymentsController extends AbstractFrontendModuleController
{
    use ErrorHandlingTrait;

    public function __construct(
        private readonly Security $security,
        private readonly StripeHelper $stripeHelper,
        private readonly StripeClient $stripeClient,
        private readonly TranslatorInterface $translator,
    ) {
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response|null
    {
        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser) {
            return new Response();
        }

        $member = MemberModel::findByPk($user->id);

        if (null === $member) {
            return new Response();
        }

        if ('stripe_payment' === $request->request->get('FORM_SUBMIT')) {
            switch ($request->request->get('action')) {
                case 'delete':
                    $member->stripe_payment_method = '';
                    $member->save();
                    break;

                case 'setup':
                    throw new RedirectResponseException($this->setupIntent($member));
            }

            throw new RedirectResponseException($this->getPageModel()->getAbsoluteUrl());
        }

        if ($request->query->has('session_id')) {
            try {
                $session = $this->stripeClient->checkout->sessions->retrieve(
                    $request->query->get('session_id'),
                    ['expand' => ['setup_intent']],
                );
                $this->stripeHelper->storePaymentMethod($session);
            } catch (ApiErrorException) {
                // Ignore if session cannot be found, stil reload the page and remove the query argument
            }

            throw new RedirectResponseException($this->getPageModel()->getAbsoluteUrl());
        }

        if ($member->stripe_payment_method) {
            try {
                $paymentMethod = $this->stripeClient->paymentMethods->retrieve($member->stripe_payment_method);

                switch ($paymentMethod->type) {
                    case 'card':
                        /** @var Card $card */
                        $card = $paymentMethod->card;
                        $brand = $this->translator->trans('payment_card.'.$card->brand);
                        $template->paymentMethod = $this->translator->trans('payment_card', [
                            '{brand}' => $brand,
                            '{digits}' => $card->last4,
                            '{month}' => $this->translator->trans('MONTHS.'.$card->exp_month, [], 'contao_default'),
                            '{year}' => $card->exp_year,
                        ]);

                        // Card brand could not be translated
                        if ('payment_card.'.$card->brand === $brand) {
                            $this->sentryOrThrow(
                                "Unknown card brand \"{$card->brand}\" for payment method \"{$paymentMethod->type}\"",
                                null,
                                [
                                    'member' => $member->row(),
                                    'payment_method' => $paymentMethod->toArray(),
                                ],
                            );
                        }
                        break;

                    case 'sepa_debit':
                        $sepa = $paymentMethod->sepa_debit;
                        $template->paymentMethod = $this->translator->trans('payment_sepa_debit', [
                            '{digits}' => $sepa['last4'],
                        ]);
                        break;

                    default:
                        $this->sentryOrThrow(
                            "Unknown payment method \"{$paymentMethod->type}\"",
                            null,
                            [
                                'member' => $member->row(),
                                'payment_method' => $paymentMethod->toArray(),
                            ],
                        );
                }
            } catch (ApiErrorException $exception) {
                if (404 === $exception->getHttpStatus()) {
                    $member->stripe_payment_method = '';
                    $member->save();
                }
            }
        }

        $jumpTo = PageModel::findByPk($model->jumpTo);
        if ($jumpTo instanceof PageModel) {
            $template->linkHref = $jumpTo->getFrontendUrl();
            $template->linkTitle = $jumpTo->pageTitle ?: $jumpTo->title;
        }

        return $template->getResponse();
    }

    private function setupIntent(MemberModel $member): string
    {
        $customer = $this->stripeHelper->createOrUpdateCustomer($member);
        $session = $this->stripeClient->checkout->sessions->create([
            'mode' => 'setup',
            'customer' => $customer->id,
            'locale' => strtolower($member->language),
            'metadata' => [
                'contao_member_id' => $member->id,
            ],
            'payment_method_types' => ['card', 'sepa_debit'],
            'success_url' => $this->getPageModel()->getAbsoluteUrl().'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->getPageModel()->getAbsoluteUrl(),
        ]);

        return $session->url;
    }
}
