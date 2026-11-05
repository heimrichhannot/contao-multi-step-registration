# Changelog

## [Unreleased]

## [0.1.2] - 2026-11-05

- Fixed: Mandatory checkboxes now display the required marker and require confirmation in the browser, matching server-side validation. ([#4](https://github.com/heimrichhannot/contao-multi-step-registration/pull/4))

## [0.1.1] - 2026-08-27

- Fixed: Validation and security-check errors in the final registration step are displayed correctly with Symfony UX Turbo while retaining the entered data. ([#3](https://github.com/heimrichhannot/contao-multi-step-registration/pull/3))
- Changed: Clarified the backend label for the redirect page used after submitting the registration form.

## [0.1.0] - 2026-08-26

- Added: Configurable multi-step member registration for Contao 5.7 with sortable fields and German and English labels.
- Added: Account activation by email, member group assignment, home directories and configurable redirects.
- Added: Optional Symfony UX Turbo support for navigating registration steps without full-page reloads. ([#1](https://github.com/heimrichhannot/contao-multi-step-registration/pull/1))
- Fixed: Activation emails and administrator notifications are sent correctly using the configured sender. ([#2](https://github.com/heimrichhannot/contao-multi-step-registration/pull/2))
