<?php

declare(strict_types=1);

namespace HeimrichHannot\MultiStepRegistration\Controller\ContentElement;

use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\FormCaptcha;
use Contao\MemberModel;
use HeimrichHannot\MultiStepRegistration\Form\DcaFormFieldMapper;
use HeimrichHannot\MultiStepRegistration\Form\MemberRegistrationFlowType;
use HeimrichHannot\MultiStepRegistration\Registration\EditableMemberFieldProvider;
use HeimrichHannot\MultiStepRegistration\Registration\MemberRegistrationService;
use HeimrichHannot\MultiStepRegistration\Registration\RegistrationFlowData;
use HeimrichHannot\MultiStepRegistration\Registration\StepNormalizer;
use Symfony\Component\Form\Flow\DataStorage\SessionDataStorage;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsContentElement(self::TYPE, category: 'member', template: 'content_element/multi_step_registration')]
class MultiStepRegistrationElementController extends AbstractContentElementController
{
    public const TYPE = 'huh_multi_step_registration';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly FormFactoryInterface $formFactory,
        private readonly RequestStack $requestStack,
        private readonly EditableMemberFieldProvider $fieldProvider,
        private readonly StepNormalizer $stepNormalizer,
        private readonly DcaFormFieldMapper $fieldMapper,
        private readonly MemberRegistrationService $registrationService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $this->framework->initialize();
        \Contao\System::loadLanguageFile('tl_member');
        $this->framework->getAdapter(Controller::class)->loadDataContainer('tl_member');

        if ($token = $request->query->getString('token')) {
            if (str_starts_with($token, 'reg-')) {
                $activation = $this->registrationService->activateAccount($token, $model);

                if ($activation['redirect'] instanceof RedirectResponse) {
                    return $activation['redirect'];
                }

                $template->set('form', null);
                $template->set('message', $activation);

                return $template->getResponse();
            }
        }

        $availableFields = $this->fieldProvider->getOptions();
        $steps = $this->stepNormalizer->normalize($model->msrSteps ?? [], $availableFields);
        $stepFields = array_values(array_unique(array_merge(...array_map(static fn ($step): array => $step->fields, $steps))));

        if (!$stepFields) {
            $template->set('form', null);
            $template->set('message', [
                'type' => 'error',
                'message' => $this->translator->trans('frontend.no_fields_configured', domain: 'huh_multi_step_registration'),
            ]);

            return $template->getResponse();
        }

        $this->removeExpiredDuplicateRegistration($model, $request);

        $dcaFields = array_intersect_key($GLOBALS['TL_DCA']['tl_member']['fields'] ?? [], array_flip($stepFields));
        $data = new RegistrationFlowData();
        $constraints = [];
        $attributes = [];

        foreach ($dcaFields as $field => $config) {
            $constraints[$field] = $this->fieldMapper->createConstraints((string) $field, $config);
            $attributes[$field] = $this->fieldMapper->createAttributes($config);
        }

        $flow = $this->formFactory->createNamed('multi_step_registration_'.$model->id, MemberRegistrationFlowType::class, $data, [
            'steps' => $steps,
            'dca_fields' => $dcaFields,
            'constraints_by_field' => $constraints,
            'attr_by_field' => $attributes,
            'data_storage' => new SessionDataStorage('huh_multi_step_registration_'.$model->id, $this->requestStack),
            'step_property_path' => 'currentStep',
        ]);

        $flow->handleRequest($request);

        if ($flow->isSubmitted() && $flow->isValid() && $flow->isFinished() && $this->isPasswordEqualToUsername($flow->getData())) {
            $flow->addError(new FormError($GLOBALS['TL_LANG']['ERR']['passwordName'] ?? $this->translator->trans('frontend.password_matches_username', domain: 'huh_multi_step_registration')));
        }

        if ($flow->isSubmitted() && $flow->isValid() && $flow->isFinished() && !$this->isCaptchaValid($model)) {
            $flow->addError(new FormError($GLOBALS['TL_LANG']['ERR']['captcha'] ?? $this->translator->trans('frontend.captcha_invalid', domain: 'huh_multi_step_registration')));
        }

        if ($flow->isSubmitted() && $flow->isValid() && $flow->isFinished()) {
            $submittedData = $flow->getData();

            if ($submittedData instanceof RegistrationFlowData) {
                $values = $this->fieldMapper->normalizeSubmittedValues($dcaFields, $submittedData->values);
                $flow->reset();

                if ($redirect = $this->registrationService->createMember($values, $model, $request)) {
                    return $redirect;
                }

                $template->set('form', null);
                $template->set('message', [
                    'type' => 'confirm',
                    'message' => ($model->msrActivate ?? false)
                        ? $this->translator->trans('frontend.activation_mail_sent', domain: 'huh_multi_step_registration')
                        : $this->translator->trans('frontend.registration_complete', domain: 'huh_multi_step_registration'),
                ]);

                return $template->getResponse();
            }
        }

        $stepForm = $flow->getStepForm();
        $template->set('form', $stepForm->createView());
        $template->set('captcha', $this->shouldShowCaptcha($model, $stepForm) ? $this->createCaptcha($model)->parse() : null);
        $template->set('message', null);
        $template->set('steps', $steps);

        return $template->getResponse();
    }

    private function removeExpiredDuplicateRegistration(ContentModel $model, Request $request): void
    {
        if (!$request->isMethod('POST')) {
            return;
        }

        $email = $request->request->all('multi_step_registration_'.$model->id);
        $email = $this->findPostedValue($email, 'email');

        if (null === $email) {
            return;
        }

        if ($member = MemberModel::findExpiredRegistrationByEmail((string) $email)) {
            $member->delete();
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function findPostedValue(array $data, string $field): mixed
    {
        foreach ($data as $key => $value) {
            if ($key === $field) {
                return $value;
            }

            if (\is_array($value) && null !== ($found = $this->findPostedValue($value, $field))) {
                return $found;
            }
        }

        return null;
    }

    private function shouldShowCaptcha(ContentModel $model, mixed $flow): bool
    {
        return !($model->msrDisableCaptcha ?? false) && method_exists($flow, 'getCursor') && $flow->getCursor()->isLastStep();
    }

    private function isCaptchaValid(ContentModel $model): bool
    {
        if ($model->msrDisableCaptcha ?? false) {
            return true;
        }

        $captcha = $this->createCaptcha($model);
        $captcha->validate();

        return !$captcha->hasErrors();
    }

    private function createCaptcha(ContentModel $model): FormCaptcha
    {
        $dca = [
            'name' => 'multi_step_registration_captcha_'.$model->id,
            'label' => $GLOBALS['TL_LANG']['MSC']['securityQuestion'] ?? 'Security question',
            'inputType' => 'captcha',
            'eval' => ['mandatory' => true, 'required' => true],
        ];

        return new FormCaptcha(FormCaptcha::getAttributesFromDca($dca, $dca['name']));
    }

    private function isPasswordEqualToUsername(mixed $data): bool
    {
        if (!$data instanceof RegistrationFlowData) {
            return false;
        }

        $password = $data->values['password'] ?? null;
        $username = $data->values['username'] ?? null;

        return \is_string($password) && \is_string($username) && '' !== $password && hash_equals($username, $password);
    }
}
