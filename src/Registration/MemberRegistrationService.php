<?php

declare(strict_types=1);

namespace HeimrichHannot\MultiStepRegistration\Registration;

use Contao\Config;
use Contao\ContentModel;
use Contao\CoreBundle\Event\MemberActivationMailEvent;
use Contao\CoreBundle\OptIn\OptInInterface;
use Contao\CoreBundle\OptIn\OptInToken;
use Contao\Email;
use Contao\Environment;
use Contao\FilesModel;
use Contao\Folder;
use Contao\Idna;
use Contao\MemberModel;
use Contao\OptInModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Versions;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MemberRegistrationService
{
    public function __construct(
        private readonly OptInInterface $optIn,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RouterInterface $router,
        private readonly TranslatorInterface $translator,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createMember(array $data, ContentModel $model, Request $request): ?RedirectResponse
    {
        if (($model->msrActivate ?? false) && isset($data['email']) && ($member = MemberModel::findUnactivatedByEmail((string) $data['email'])) !== null) {
            $this->resendActivationMail($member);

            return null;
        }

        $data['tstamp'] = time();
        $data['dateAdded'] = $data['tstamp'];
        $data['login'] = (bool) ($model->msrAllowLogin ?? false);
        $data['groups'] ??= $model->msrGroups ?? [];
        $data['disable'] = 1;

        if (\is_array($data['groups'])) {
            $data['groups'] = serialize($data['groups']);
        }

        $member = new MemberModel();
        $member->setRow($data);
        $member->save();

        $data['id'] = $member->id;

        if ($model->msrActivate ?? false) {
            $this->sendActivationMail($data, $model, $request);
        }

        if ($model->msrAssignDir ?? false) {
            $this->assignHomeDirectory($member, $data, $model);
        }

        foreach (($GLOBALS['TL_HOOKS']['createNewUser'] ?? []) as $callback) {
            System::importStatic($callback[0])->{$callback[1]}($member->id, $data, $model);
        }

        $versions = new Versions('tl_member', $member->id);
        $versions->setUsername($member->username);
        $versions->setEditUrl($this->router->generate('contao_backend', ['do' => 'member', 'act' => 'edit', 'id' => $member->id]));
        $versions->initialize();

        if (!($model->msrActivate ?? false)) {
            $this->sendAdminNotification($member->id, $data);
        }

        if ($target = PageModel::findById($model->msrJumpTo ?? null)) {
            return new RedirectResponse(System::getContainer()->get('contao.routing.content_url_generator')->generate($target));
        }

        return null;
    }

    /**
     * @return array{type: string, message: string, redirect: RedirectResponse|null}
     */
    public function activateAccount(string $token, ContentModel $model): array
    {
        $invalid = [
            'type' => 'error',
            'message' => $GLOBALS['TL_LANG']['MSC']['invalidToken'] ?? $this->translator->trans('frontend.invalid_token', domain: 'huh_multi_step_registration'),
            'redirect' => null,
        ];

        $optInToken = $this->optIn->find($token);

        if (!$optInToken || !$optInToken->isValid()) {
            return $invalid;
        }

        $related = $optInToken->getRelatedRecords();

        if (1 !== \count($related) || key($related) !== 'tl_member' || 1 !== \count($ids = current($related)) || !($member = MemberModel::findById($ids[0]))) {
            return $invalid;
        }

        if ($optInToken->getEmail() !== $member->email) {
            return [
                'type' => 'error',
                'message' => $GLOBALS['TL_LANG']['MSC']['tokenEmailMismatch'] ?? $this->translator->trans('frontend.token_email_mismatch', domain: 'huh_multi_step_registration'),
                'redirect' => null,
            ];
        }

        if (!$optInToken->isConfirmed()) {
            $member->disable = false;
            $member->save();
            $optInToken->confirm();

            foreach (($GLOBALS['TL_HOOKS']['activateAccount'] ?? []) as $callback) {
                System::importStatic($callback[0])->{$callback[1]}($member, $model);
            }

            $this->logger?->info('User account ID '.$member->id.' ('.Idna::decodeEmail($member->email).') has been activated');
        }

        $redirect = null;

        if ($target = PageModel::findById($model->msrRegJumpTo ?? null)) {
            $redirect = new RedirectResponse(System::getContainer()->get('contao.routing.content_url_generator')->generate($target));
        }

        return [
            'type' => 'confirm',
            'message' => $GLOBALS['TL_LANG']['MSC']['accountActivated'] ?? $this->translator->trans('frontend.account_activated', domain: 'huh_multi_step_registration'),
            'redirect' => $redirect,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function sendActivationMail(array $data, ContentModel $model, Request $request): void
    {
        $removeOn = new \DateTime('+'.System::getContainer()->getParameter('contao.registration.expiration').' days');
        $optInToken = $this->optIn->create('reg', (string) $data['email'], ['tl_member' => [$data['id']]]);

        if ($optInModel = OptInModel::findByToken($optInToken->getIdentifier())) {
            $optInModel->removeOn = $removeOn->getTimestamp();
            $optInModel->save();
        }

        if (!$optInToken instanceof OptInToken) {
            return;
        }

        $uri = $request->getUri();

        $tokenData = $data;
        $tokenData['activation'] = $optInToken->getIdentifier();
        $tokenData['domain'] = Idna::decode(Environment::get('host'));
        $tokenData['link'] = Idna::decode(str_contains($uri, '?') ? $uri.'&token='.$optInToken->getIdentifier() : $uri.'?token='.$optInToken->getIdentifier());

        $event = new MemberActivationMailEvent(
            MemberModel::findById($data['id']),
            $optInToken,
            \sprintf($GLOBALS['TL_LANG']['MSC']['emailSubject'], Idna::decode(Environment::get('host'))),
            (string) ($this->getModelValue($model, 'msrRegText') ?: $GLOBALS['TL_LANG']['MSC']['emailText']),
            $tokenData,
        );

        $this->eventDispatcher->dispatch($event);

        if ($event->shouldSendOptInToken()) {
            $text = System::getContainer()->get('contao.string.simple_token_parser')->parse($event->getText(), $event->getSimpleTokens());
            $optInToken->send($event->getSubject(), $text);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function assignHomeDirectory(MemberModel $member, array $data, ContentModel $model): void
    {
        $homeDir = FilesModel::findByUuid($model->msrHomeDir ?? null);

        if (null === $homeDir) {
            return;
        }

        $userDir = StringUtil::standardize((string) ($data['username'] ?? '')) ?: 'user_'.$member->id;
        $projectDir = System::getContainer()->getParameter('kernel.project_dir');

        while (is_dir($projectDir.'/'.$homeDir->path.'/'.$userDir)) {
            $userDir .= '_'.$member->id;
        }

        new Folder($homeDir->path.'/'.$userDir);

        if ($userDirModel = FilesModel::findByPath($homeDir->path.'/'.$userDir)) {
            $member->assignDir = true;
            $member->homeDir = $userDirModel->uuid;
            $member->save();
        }
    }

    private function resendActivationMail(MemberModel $member): void
    {
        if (!$member->disable) {
            return;
        }

        $models = OptInModel::findByRelatedTableAndIds('tl_member', [$member->id]);

        if (null === $models) {
            return;
        }

        foreach ($models as $model) {
            $token = $this->optIn->find($model->token);

            if ($token && $token->isValid() && !$token->isConfirmed()) {
                $token->send();

                return;
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function sendAdminNotification(int|string $id, array $data): void
    {
        $this->logger?->info('A new user (ID '.$id.') has registered on the website');

        if (!isset($GLOBALS['TL_ADMIN_EMAIL'])) {
            return;
        }

        $messageData = "\n\n";

        foreach ($data as $key => $value) {
            if (\in_array($key, ['id', 'password', 'tstamp', 'dateAdded'], true)) {
                continue;
            }

            $value = StringUtil::deserialize($value);

            if ('dateOfBirth' === $key && \strlen((string) $value)) {
                $value = \Contao\Date::parse(Config::get('dateFormat'), $value);
            }

            $messageData .= ($GLOBALS['TL_LANG']['tl_member'][$key][0] ?? $key).': '.(\is_array($value) ? implode(', ', $value) : $value)."\n";
        }

        $email = new Email();
        $email->from = $GLOBALS['TL_ADMIN_EMAIL'];
        $email->fromName = $GLOBALS['TL_ADMIN_NAME'] ?? null;
        $email->subject = \sprintf($GLOBALS['TL_LANG']['MSC']['adminSubject'], Idna::decode(Environment::get('host')));
        $email->text = \sprintf($GLOBALS['TL_LANG']['MSC']['adminText'], $id, $messageData."\n")."\n";
        $email->sendTo($GLOBALS['TL_ADMIN_EMAIL']);
    }

    private function getModelValue(ContentModel $model, string $field): mixed
    {
        $row = $model->row();

        return $row[$field] ?? null;
    }
}
