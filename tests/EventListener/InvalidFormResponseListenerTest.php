<?php

declare(strict_types=1);

namespace HeimrichHannot\MultiStepRegistration\Tests\EventListener;

use HeimrichHannot\MultiStepRegistration\EventListener\InvalidFormResponseListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class InvalidFormResponseListenerTest extends TestCase
{
    public function testItMarksInvalidMainResponsesAsUnprocessable(): void
    {
        $request = new Request();
        $request->attributes->set(InvalidFormResponseListener::REQUEST_ATTRIBUTE, true);
        $response = new Response();
        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        (new InvalidFormResponseListener())($event);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testItIgnoresUnmarkedResponses(): void
    {
        $response = new Response();
        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        (new InvalidFormResponseListener())($event);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testItIgnoresSubRequests(): void
    {
        $request = new Request();
        $request->attributes->set(InvalidFormResponseListener::REQUEST_ATTRIBUTE, true);
        $response = new Response();
        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::SUB_REQUEST,
            $response,
        );

        (new InvalidFormResponseListener())($event);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
