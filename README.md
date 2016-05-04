# Installation

Add `"wearedevelopers/email-verification-utility": "dev-master"` to the require block of your `composer.json` file and run `$ composer install` or `$ composer update`

# Usage

Add the following to your use block:

    use \WeAreDevelopers\Utils\Email;

Where you want verify an email address run the following, where `$toEmail` is the email address being validated and `$fromEmail` is an email address that you know exists, ideally one related to the project:

    Email::exists($toEmail, $fromEmail);

To block addresses that cannot be reliably verified, ie. yahoo.com email addresses, set the strict parameter to true:

    Email:exists($toEmail, $fromEmail, true);

To change the expected status codes from the mail exchange servers, eg. from 250 to 220:

    Email::exists($toEmail, $fromEmail, false, ['220']);

*Note: the status code is used in a regex (`/^$code/i`), thus if your status code is 22, it would match 22 & 220. To address this you could either add a trailing space, or extra symbols, eg. `'22\b'`*

Finally there is the `$getDetails` parameter, which changes the response to a `stdClass` object, with `valid` and `details` properties, where valid is a boolean, and details is an array of the responses from the mail exchange server, or any points of failure along the way:

    Email::exists($toEmail, $fromEmail, false, ['250'], true);

