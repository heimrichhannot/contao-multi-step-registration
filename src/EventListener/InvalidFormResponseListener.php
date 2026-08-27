<?php

declare(strict_types=1);

namespace HeimrichHannot\MultiStepRegistration\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(KernelEvents::RESPONSE)]
final class InvalidFormResponseListener
{
    public const REQUEST_ATTRIBUTE = '_huh_multi_step_registration_invalid_form';

    public function __invoke(ResponseEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        if (true !== $event->getRequest()->attributes->get(self::REQUEST_ATTRIBUTE)) {
            return;
        }

        $event->getResponse()->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
